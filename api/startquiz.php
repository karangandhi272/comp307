<?php
// Starts a quiz attempt. Returns the questions without answers or explanations.
require_once __DIR__ . '/quiz_lib.php';

$t = trim($_POST["ticket"] ?? '');
$quizId = trim($_POST["quiz_id"] ?? '');

if ($t == '' or !ctype_digit($quizId)) die("0");

// Get the user.
$user = query(
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ?
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

$quiz = query("SELECT * FROM quizzes WHERE id = ?", [$quizId])->fetch();
if (!$quiz) die("0");

// Only members of the quiz's group can take it.
$member = query(
    "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?",
    [$quiz["group_id"], $user["id"]]
)->fetch();
if (!$member) die("0");

query(
    "INSERT INTO quiz_attempts (quiz_id, user_id, max_score) VALUES (?, ?, ?)",
    [$quiz["id"], $user["id"], $quiz["question_count"]]
);

header('Content-Type: application/json');
echo json_encode([
    "attempt_id" => (int)db()->lastInsertId(),
    "title" => $quiz["title"],
    "questions" => strip_answers(json_decode($quiz["content"], true))
]);
?>
