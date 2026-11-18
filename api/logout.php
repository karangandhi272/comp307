<?php
require_once __DIR__ . '/db.php';
$t = trim($_POST["ticket"] ?? '');

if ($t == "") {
    die("0"); 
}

$db = db(); 
$row = query("SELECT users.* FROM sessions JOIN users ON users.id = sessions.user_id WHERE sessions.token = ?", [$t])->fetch();
if ($row) {
    query("DELETE FROM sessions WHERE token = ? ", [$t]);
    echo "1";
}
else {
    echo "0";
}

?>