<?php
// Delete a note.
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$noteId = trim($_POST["note_id"] ?? '');

if ($t == '' or $noteId == '') die("0");

// Get the user.
$user = query(  
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ? 
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

// Load the note.
$note = query(
    "SELECT group_id, author_id FROM notes WHERE id = ?",
    [$noteId]
)->fetch();
if (!$note) die("0");

// Ensure the user is a member of the group to be able to load a note.
$member = query(
    "SELECT role FROM group_members WHERE group_id = ? 
    AND user_id = ?", [$note["group_id"], $user["id"]]
)->fetch();
if (!$member) die("0");

$isAuthor = ($note["author_id"] == $user["id"]);
$isLeader = ($member["role"] === "leader");
if (!$isAuthor && !$isLeader) die("0");

// Delete the note. 
query("DELETE FROM notes WHERE id = ?", [$noteId]);

header('Content-Type: application/json');
echo json_encode(["deleted" => true]);
?>