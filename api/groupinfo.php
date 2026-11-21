<?php
// Returns the whole Group page.
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$groupId = trim($_POST["group_id"] ?? '');

if ($t == '' or $groupId == '') die("0");

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
    "SELECT role FROM group_members WHERE group_id = ? 
    AND user_id = ?", [$groupId, $user["id"]]
)->fetch();
if (!$member) die("0");

// Get the group
$group = query(
    "SELECT name, description, invite_code FROM study_groups WHERE id = ?",
    [$groupId]
)->fetch();
if (!$group) die("0");

// Get the members of the group.
$members = query(
    "SELECT users.id AS user_id, users.display_name, group_members.role
    FROM group_members
    JOIN users ON users.id = group_members.user_id
    WHERE group_members.group_id = ? 
    ORDER BY (group_members.role = 'leader') DESC, users.display_name",
    [$groupId]
)->fetchAll();

$notes = query(
    "SELECT notes.id, notes.title, notes.author_id, users.display_name AS
    author, notes.version, COALESCE(notes.updated_at, notes.created_at) AS
    updated_at
        FROM notes
        LEFT JOIN users ON users.id = notes.author_id
        WHERE notes.group_id = ?
        ORDER BY notes.id DESC",
        [$groupId]
)->fetchAll();

// Add quiz list and get_leaderboard($groupId).
$quizzes = [];
$leaderboard = [];

header('Content-Type: application/json');
echo json_encode([
    "group" => $group,
    "me" => ["user_id" => $user["id"], "role" => $member["role"]],
    "members" => $members,
    "notes" => $notes,
    "quizzes" => $quizzes,
    "leaderboard" => $leaderboard
]);
?>
