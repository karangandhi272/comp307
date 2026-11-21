<?php
// Leave a group.
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$groupId = trim($_POST["group_id"] ?? '');

if ($t == '' or $groupId == '') die("0");

// Get the user making the request to leave.
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

header('Content-Type: application/json');

// Case that the requester is the leader.
if ($member["role"] == "leader") {
    $leaders = (int) query(
        "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND role = 'leader'",
        [$groupId]
    )->fetchColumn();
    
    // The group only has 1 leader, so can either delete the group or make them promote someone first.
    if ($leaders === 1) {
        $count = (int) query(
            "SELECT COUNT(*) FROM group_members WHERE group_id = ?",
            [$groupId]
        )->fetchColumn();

        // Only 1 member left (the leader), so delete the group itself.
        if ($count === 1) {
            query("DELETE FROM study_groups WHERE id = ?", [$groupId]);
            echo json_encode(["deleted_group" => true]);
            exit;
        }

        // Promote someone to be the new leader. Otherwise, the group would have no leader.
        echo json_encode(["error" => "promote"]);
        exit;
    } 
}

// Remove the user from the group, but their notes remain.
query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
[$groupId, $user["id"]]
);

echo json_encode(["left" => true]);
?>