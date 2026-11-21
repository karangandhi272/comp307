<?php
// Edit a note. It only saves if nobody else saved since you 
// last opened it (version check).
require_once __DIR__ . '/db.php';

$t = trim($_POST["ticket"] ?? '');
$noteId = trim($_POST["note_id"] ?? '');
$title = trim($_POST["title"] ?? '');
$content = $_POST["content"] ?? '';    
$version = trim($_POST["version"] ?? '');

if ($t == '' or $noteId == '' or $title == '' or $content == '' or $version == '') die("0");

if (!ctype_digit($version)) die("0");

if (strlen($content) > 200000 or strlen($title) > 200) die("0");

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
$note = query("SELECT group_id FROM notes WHERE id = ?", [$noteId])->fetch();
if (!$note) die("0");

// Any member of the group can edit its notes.
$member = query(
    "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?",
    [$note["group_id"], $user["id"]]
)->fetch();
if (!$member) die("0");

// Save edit and revision together. 
db()->beginTransaction();

// Only update it if the note is still at the version the user began with. 
$updated = query(
    "UPDATE notes
    SET title = ?, content = ?, version = version + 1,
    updated_at = CURRENT_TIMESTAMP
    WHERE id = ? AND version = ?",
    [$title, $content, $noteId, $version]
)->rowCount();

if ($updated === 0) {
    db()->rollBack();   // Somebody else saved it first, the note is no longer at $version.
    die("0");
}

$newVersion = (int)$version + 1;

query(
    "INSERT INTO note_revisions (note_id, version, content) VALUES (?, ?, ?)",
    [$noteId, $newVersion, $content]
);

db()->commit();

header('Content-Type: application/json');
echo json_encode(["version" => $newVersion]);
?>