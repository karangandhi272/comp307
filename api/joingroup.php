<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');
$group_code = trim($_POST["groupcode"] ?? '');


if ($t == '' or $group_code == '') {
    die("0"); 
}

$db = db(); 

$group =  query("SELECT * FROM study_groups WHERE invite_code = ? ", [$group_code])->fetch();
if ($group){
    $user = query("SELECT users.* FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ? AND sessions.expires_at > datetime('now')", [$t])->fetch();
    if (!$user) die("0");
    $exists = query("SELECT * FROM group_members WHERE group_id = ? AND user_id = ? ", [$group["id"], $user["id"]])->fetch();

    if ($exists) {
        die("0");
    }
    query("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)", [$group["id"], $user["id"], "member"]);
    $data =  query("SELECT * FROM study_groups WHERE invite_code = ? ", [$group_code])->fetch();
    header('Content-Type: application/json');
    echo json_encode($data);
}
else {
    die("0");
}

?>
