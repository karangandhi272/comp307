<?php
// Upload a new note to a group.
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$groupId = trim($_POST["group_id"] ?? '');
$title = trim($_POST["title"] ?? '');
$content = $_POST["content"] ?? '';     // No trim: keep the file as it was uploaded.

if ($t == '' or $groupId == '' or $title == '' or $content == '') {
    die("0");
}

if (strlen($content) > 200000 or strlen($title) > 200) {    // Maximum content size 200KB.
    die("0");
}

// Get the user.
$user = query(  
    "SELECT users.id FROM sessions
    JOIN users ON users.id = sessions.user_id
    WHERE sessions.token = ? 
    AND sessions.expires_at > datetime('now')",
    [$t]
)->fetch();
if (!$user) die("0");

// Ensure the user is a member of the group to be able to upload a note.
$member = query(
    "SELECT role FROM group_members WHERE group_id = ? 
    AND user_id = ?", [$groupId, $user["id"]]
)->fetch();
if (!$member) die("0");

// Save the note to the database.
query(
    "INSERT INTO notes (group_id, author_id, title, content) 
    VALUES (?, ?, ?, ?)", [$groupId, $user["id"], $title, $content]
);
$noteId = db()->lastInsertId();

// Save version 1 of the notes to the history.
query(
    "INSERT INTO note_revisions (note_id, version, content) VALUES (?, 1, ?)",
    [$noteId, $content]
);

header('Content-Type: application/json');
echo json_encode(["note_id" => (int)$noteId]);
?>