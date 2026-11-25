<?php
require_once __DIR__ . '/db.php';
$email = trim($_POST["email"] ?? '');
$pass = trim($_POST["pass"] ?? '');

if ($email == '' or $pass == '') {
    die("0"); 
}

$db = db(); 

// Look the user up by email only, then check the password against the stored hash.
$row =  query("SELECT * FROM users WHERE email = ?", [$email])->fetch();
if ($row and password_verify($pass, $row["pass_hash"])) {
    $t =  bin2hex(random_bytes(32)); 
    query("INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, datetime('now', '+1 month'))", [$t, $row["id"]]);
    echo $t;
}
else {
    echo "0";
}

?>