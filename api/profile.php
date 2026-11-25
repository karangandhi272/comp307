<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');


if ($t == '') {
    die("0"); 
}

$db = db(); 

$user = query("SELECT users.id, users.email, users.display_name, users.created_at FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ? AND sessions.expires_at > datetime('now')", [$t])->fetch();
if (!$user) die("0");

$user["groups"] = query("SELECT study_groups.id, study_groups.name, group_members.role FROM group_members JOIN study_groups ON study_groups.id = group_members.group_id WHERE group_members.user_id = ? ORDER BY study_groups.name", [$user["id"]])->fetchAll();
$user["quizzes_taken"] = query("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND submitted_at IS NOT NULL", [$user["id"]])->fetchColumn();

header('Content-Type: application/json');
echo json_encode($user);
