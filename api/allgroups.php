<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');


if ($t == '') {
    die("0"); 
}

$db = db(); 
$user = query("SELECT users.* FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ?", [$t])->fetch();
if (!$user) die("0");
$groups =  query("SELECT group_id FROM group_members WHERE user_id = ? ", [$user["id"]])->fetchAll();


$out = [];
header('Content-Type: application/json');
foreach ($groups as $r) {
    $out[] = (query("SELECT * FROM study_groups WHERE id = ? ", [$r["group_id"]])->fetch());
}

echo json_encode($out);



?>
