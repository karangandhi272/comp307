<?php
// Quiz helpers shared by groupinfo.php, startquiz.php and submitquiz.php.
require_once __DIR__ . '/db.php';

// Returns the questions with the answers and explanations removed, safe to send to the browser.
function strip_answers($content) {
    foreach ($content['questions'] as &$q) unset($q['answer'], $q['explanation']);
    return $content['questions'];
}

// Compares the user's answers ({"q1": "a", ...}) with the quiz. Returns [score, results].
function grade($content, $answers) {
    if (!is_array($answers)) $answers = [];
    $score = 0;
    $results = [];
    foreach ($content['questions'] as $q) {
        $picked  = $answers[$q['id']] ?? null;
        $correct = ($picked === $q['answer']);
        if ($correct) $score++;
        $results[] = [
            'id'          => $q['id'],
            'prompt'      => $q['prompt'],
            'picked'      => $picked,
            'answer'      => $q['answer'],
            'correct'     => $correct,
            'explanation' => $q['explanation'] ?? '',
        ];
    }
    return [$score, $results];
}

// --> [{ id, title, question_count, created_at, best_score }]  (best_score is null if the user hasn't taken it)
function get_quiz_list($groupId, $userId) {
    return query(
        "SELECT q.id, q.title, q.question_count, q.created_at, MAX(a.score) AS best_score
        FROM quizzes q
        LEFT JOIN quiz_attempts a
            ON a.quiz_id = q.id AND a.user_id = ? AND a.submitted_at IS NOT NULL
        WHERE q.group_id = ?
        GROUP BY q.id
        ORDER BY q.created_at DESC, q.id DESC",
        [$userId, $groupId]
    )->fetchAll();
}

// --> [{ user_id, display_name, points, first_points, quizzes_taken, attempts, possible, notes }] sorted by points, includes members with 0.
// points = sum of each member's BEST score per quiz, so retaking a quiz can't farm points
// first_points = sum of each member's FIRST submitted score per quiz
// attempts = submitted attempts including retakes, possible = total questions across the group's quizzes
function get_leaderboard($groupId) {
    return query(
        "SELECT u.id AS user_id, u.display_name,
            COALESCE(SUM(s.best_score), 0) AS points,
            COALESCE(SUM(s.first_score), 0) AS first_points,
            COUNT(s.quiz_id) AS quizzes_taken,
            COALESCE(SUM(s.attempts), 0) AS attempts,
            (SELECT COALESCE(SUM(question_count), 0) FROM quizzes WHERE group_id = gm.group_id) AS possible,
            (SELECT COUNT(*) FROM notes n WHERE n.group_id = gm.group_id AND n.author_id = u.id) AS notes
        FROM group_members gm
        JOIN users u ON u.id = gm.user_id
        LEFT JOIN (
            SELECT a.user_id, a.quiz_id, MAX(a.score) AS best_score, COUNT(*) AS attempts,
                (SELECT f.score FROM quiz_attempts f
                 WHERE f.user_id = a.user_id AND f.quiz_id = a.quiz_id AND f.submitted_at IS NOT NULL
                 ORDER BY f.id LIMIT 1) AS first_score
            FROM quiz_attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            WHERE q.group_id = ? AND a.submitted_at IS NOT NULL
            GROUP BY a.user_id, a.quiz_id
        ) s ON s.user_id = gm.user_id
        WHERE gm.group_id = ?
        GROUP BY u.id, u.display_name
        ORDER BY points DESC, u.display_name",
        [$groupId, $groupId]
    )->fetchAll();
}
?>
