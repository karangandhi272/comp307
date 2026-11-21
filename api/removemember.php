<?php
// Remove a member from a group. This can only be done by a leader and a leader
// cannot be removed. 
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$groupId = trim($_POST["group_id"] ?? '');
$targetId = trim($_POST['user_id'] ?? '');

if ($t == '' or $groupId == '' or $targetId == '') die("0");

// Get the user making the request to remove a member.
$user = query(  
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ? 
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

// Ensure the requester is a member of the group.
$member = query(
    "SELECT role FROM group_members WHERE group_id = ? 
    AND user_id = ?", [$groupId, $user["id"]]
)->fetch();
if (!$member) die("0");

// Ensure that the requester is a leader.
if ($member["role"] !== "leader") die("0");

// The target user must be in the group.
$target = query(
    "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?",
    [$groupId, $targetId]
)->fetch();
if (!$target) die("0");

// A leader can't be removed.
if ($target["role"] === "leader") die("0");

// Remove the target user from the group, but their notes remain.
query(
    "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
    [$groupId, $targetId]
);

header('Content-Type: application/json');
echo json_encode(["removed" => true]);
?>



