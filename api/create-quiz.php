<?php
// Generates a quiz from some of the group's notes with Gemini and saves it.
// Returns { quiz_id, question_count }, "0" for bad input, {"error": "generation"} if Gemini failed,
// or {"error": "cooldown", "wait": seconds} if the group is already generating or just generated one.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

set_time_limit(200);   // PHP's default 30 s isn't enough: each model tried can take up to 60 s.

$t = trim($_POST["ticket"] ?? '');
$groupId = trim($_POST["group_id"] ?? '');
$title = trim($_POST["title"] ?? '');
$count = (int)($_POST["question_count"] ?? 0);
$noteIds = json_decode($_POST["note_ids"] ?? '', true);   // [12, 15]

if ($t == '' or !ctype_digit($groupId) or $title == '' or strlen($title) > 200) die("0");
if (!is_array($noteIds) or count($noteIds) === 0) die("0");
foreach ($noteIds as $id) if (!is_int($id)) die("0");
$count = max(1, min(20, $count));

// Get the user.
$user = query(
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ?
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

$member = query(
    "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?",
    [$groupId, $user["id"]]
)->fetch();
if (!$member) die("0");

// "AND group_id = ?" means notes from other groups are never loaded.
$noteIds = array_values(array_unique($noteIds));
$placeholders = implode(",", array_fill(0, count($noteIds), "?"));
$notes = query(
    "SELECT id, title, content, version FROM notes WHERE group_id = ? AND id IN ($placeholders)",
    array_merge([$groupId], $noteIds)
)->fetchAll();
if (count($notes) === 0) die("0");

$sources = [];
$noteText = "";
foreach ($notes as $note) {
    $sources[] = ["note_id" => $note["id"], "version" => $note["version"]];
    $noteText .= "### " . $note["title"] . "\n" . $note["content"] . "\n\n";
}
// Too much text for one request. Two notes at the 200 KB upload limit still fit.
if (strlen($noteText) > 500000) die("0");

header('Content-Type: application/json');

// One generation at a time per group, and at most one per minute.
// Claim the group for 200 s (the longest a generation can run). Only one UPDATE can match the row,
// so two clicks at the same moment can't both start. On success the cooldown becomes 60 s from
// the end; on failure it's cleared so the group can retry right away.
$claimed = query(
    "UPDATE study_groups SET quiz_cooldown_until = datetime('now', '+200 seconds')
    WHERE id = ? AND (quiz_cooldown_until IS NULL OR quiz_cooldown_until <= datetime('now'))",
    [$groupId]
)->rowCount();
if ($claimed === 0) {
    $wait = query(
        "SELECT strftime('%s', quiz_cooldown_until) - strftime('%s', 'now') FROM study_groups WHERE id = ?",
        [$groupId]
    )->fetchColumn();
    die(json_encode(["error" => "cooldown", "wait" => max(1, (int)$wait)]));
}

// Clears the claim and reports a Gemini failure.
function fail_generation($groupId) {
    query("UPDATE study_groups SET quiz_cooldown_until = NULL WHERE id = ?", [$groupId]);
    die(json_encode(["error" => "generation"]));
}

$prompt = "You write multiple-choice quizzes for university students from their study notes.
Write exactly $count questions that test understanding of the notes below. Use only facts that are in the notes.
Each question has 4 options with ids \"a\", \"b\", \"c\", \"d\", exactly one correct answer, and a one-sentence explanation of why it is correct.
Vary which letter is correct. Write in the same language as the notes.

Example of one question:
{\"prompt\": \"What does HTTP stand for?\", \"options\": [{\"id\": \"a\", \"text\": \"HyperText Transfer Protocol\"}, {\"id\": \"b\", \"text\": \"High Transfer Text Process\"}, {\"id\": \"c\", \"text\": \"Host Text Transfer Program\"}, {\"id\": \"d\", \"text\": \"Hyperlink Transfer Protocol\"}], \"answer\": \"a\", \"explanation\": \"HTTP stands for HyperText Transfer Protocol.\"}

NOTES:
$noteText";

// Gemini's structured output: the reply is forced to be JSON in this shape.
$schema = [
    "type" => "OBJECT",
    "properties" => [
        "questions" => [
            "type" => "ARRAY",
            "items" => [
                "type" => "OBJECT",
                "properties" => [
                    "prompt" => ["type" => "STRING"],
                    "options" => [
                        "type" => "ARRAY",
                        "items" => [
                            "type" => "OBJECT",
                            "properties" => ["id" => ["type" => "STRING"], "text" => ["type" => "STRING"]],
                            "required" => ["id", "text"]
                        ]
                    ],
                    "answer" => ["type" => "STRING"],
                    "explanation" => ["type" => "STRING"]
                ],
                "required" => ["prompt", "options", "answer", "explanation"]
            ]
        ]
    ],
    "required" => ["questions"]
];

$body = json_encode([
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => [
        "responseMimeType" => "application/json",
        "responseSchema" => $schema,
        "thinkingConfig" => ["thinkingLevel" => "low"]   // Less "thinking" first: ~14 s instead of ~20 s for 5 questions.
    ]
]);

// models kept "experiencing high demand" (503) or rate limited (429), so if the main model is busy, try another one.
foreach (array_unique([GEMINI_MODEL, "gemini-3.5-flash", "gemini-3.5-flash-lite"]) as $model) {
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,   // 3 models x 60 s stays under the 200 s limit above.
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-goog-api-key: " . GEMINI_API_KEY],
        CURLOPT_POSTFIELDS => $body
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($status !== 503 and $status !== 429) break;
    error_log("create-quiz: $model busy (HTTP $status), trying the next model");
}

if ($response === false or $status !== 200) {
    error_log("create-quiz: Gemini HTTP $status " . substr((string)$response, 0, 500));
    fail_generation($groupId);
}

$text = json_decode($response, true)["candidates"][0]["content"]["parts"][0]["text"] ?? null;
$quiz = json_decode((string)$text, true);
if (!is_array($quiz) or !is_array($quiz["questions"] ?? null)) fail_generation($groupId);

// Keep only well-formed questions and number them q1, q2, ... ourselves.
$questions = [];
foreach ($quiz["questions"] as $q) {
    if (!is_array($q) or !is_string($q["prompt"] ?? null) or trim($q["prompt"]) === '') continue;

    $options = [];
    foreach ($q["options"] ?? [] as $o) {
        if (is_string($o["id"] ?? null) and is_string($o["text"] ?? null) and trim($o["text"]) !== '') {
            $options[] = ["id" => $o["id"], "text" => $o["text"]];
        }
    }
    $ids = array_column($options, "id");
    if (count($options) < 2 or count(array_unique($ids)) !== count($ids)) continue;
    if (!in_array($q["answer"] ?? null, $ids, true)) continue;

    $questions[] = [
        "id" => "q" . (count($questions) + 1),
        "type" => "mcq",
        "prompt" => $q["prompt"],
        "options" => $options,
        "answer" => $q["answer"],
        "explanation" => is_string($q["explanation"] ?? null) ? $q["explanation"] : ""
    ];
    if (count($questions) === $count) break;
}
if (count($questions) === 0) fail_generation($groupId);

query(
    "INSERT INTO quizzes (group_id, created_by, title, question_count, content) VALUES (?, ?, ?, ?, ?)",
    [$groupId, $user["id"], $title, count($questions), json_encode(["sources" => $sources, "questions" => $questions])]
);
$quizId = (int)db()->lastInsertId();

query("UPDATE study_groups SET quiz_cooldown_until = datetime('now', '+60 seconds') WHERE id = ?", [$groupId]);

echo json_encode(["quiz_id" => $quizId, "question_count" => count($questions)]);
?>
