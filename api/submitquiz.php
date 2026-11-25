<?php
// Grades a quiz attempt. Returns the score and, for each question, what was picked,
// the right answer and the explanation.
require_once __DIR__ . '/quiz_lib.php';

$t = trim($_POST["ticket"] ?? '');
$attemptId = trim($_POST["attempt_id"] ?? '');
$answers = json_decode($_POST["answers"] ?? '', true);   // {"q1": "a", "q2": "b", ...}

if ($t == '' or !ctype_digit($attemptId) or !is_array($answers)) die("0");

// Get the user.
$user = query(
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ?
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

// The attempt must belong to this user and not be submitted yet.
$attempt = query(
    "SELECT quiz_attempts.id, quiz_attempts.max_score, quizzes.content
    FROM quiz_attempts
    JOIN quizzes ON quizzes.id = quiz_attempts.quiz_id
    WHERE quiz_attempts.id = ? AND quiz_attempts.user_id = ?
    AND quiz_attempts.submitted_at IS NULL",
    [$attemptId, $user["id"]]
)->fetch();
if (!$attempt) die("0");

[$score, $results] = grade(json_decode($attempt["content"], true), $answers);

// "submitted_at IS NULL" again so a double-click can't submit the same attempt twice.
$updated = query(
    "UPDATE quiz_attempts SET answers = ?, score = ?, submitted_at = CURRENT_TIMESTAMP
    WHERE id = ? AND submitted_at IS NULL",
    [json_encode($answers), $score, $attempt["id"]]
)->rowCount();
if ($updated === 0) die("0");

header('Content-Type: application/json');
echo json_encode([
    "score" => $score,
    "max_score" => $attempt["max_score"],
    "results" => $results
]);
?>
