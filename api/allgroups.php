<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');


if ($t == '') {
    die("0"); 
}

$db = db(); 
$user = query("SELECT users.* FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ? AND sessions.expires_at > datetime('now')", [$t])->fetch();
if (!$user) die("0");
// Each of the user's groups with their role in it and how many members it has.
$out = query(
    "SELECT g.id, g.name, g.description, g.invite_code, gm.role,
        (SELECT COUNT(*) FROM group_members m WHERE m.group_id = g.id) AS member_count
    FROM group_members gm
    JOIN study_groups g ON g.id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.name",
    [$user["id"]]
)->fetchAll();

header('Content-Type: application/json');
echo json_encode($out);



?>
