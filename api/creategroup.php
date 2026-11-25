<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');
$groupname = trim($_POST["groupname"] ?? '');
$description = trim($_POST["description"] ?? '');


if ($t == '' or $groupname == '') {
    die("0"); 
}

$db = db(); 

$user = query("SELECT users.* FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ? AND sessions.expires_at > datetime('now')", [$t])->fetch();
if (!$user) die("0");

// Create the group and add the creator as leader together, so a group never exists without a leader.
$db->beginTransaction();
$groupcode = bin2hex(random_bytes(4));
query("INSERT INTO  study_groups (name,description,invite_code,created_by ) VALUES (?, ?, ?, ?)", [$groupname, $description, $groupcode,$user["id"]]);

$data =  query("SELECT * FROM study_groups WHERE invite_code = ? ", [$groupcode])->fetch();
query("INSERT INTO  group_members (group_id, user_id, role) VALUES (?, ?, ?)", [$data["id"], $user["id"], "leader"]);
$db->commit();

header('Content-Type: application/json');
echo json_encode($data);
