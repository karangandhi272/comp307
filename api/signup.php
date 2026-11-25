<?php
require_once __DIR__ . '/db.php';
$email = trim($_POST["email"] ?? '');
$pass = trim($_POST["pass"] ?? '');
$name = trim($_POST["name"] ?? '');

if ($email == '' or $pass == '' or $name == '') {
    die("0"); 
}

$db = db(); 
$row =  query("SELECT * FROM users WHERE email = ?", [$email])->fetch();
if ($row){
    die("0");
}

// Store a salted hash, never the password itself.
query("INSERT INTO users (display_name, pass_hash, email) VALUES (?, ?, ?)", [$name, password_hash($pass, PASSWORD_DEFAULT), $email]);
$userId = db()->lastInsertId();
$t =  bin2hex(random_bytes(32));
query("INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, datetime('now', '+1 month'))", [$t, $userId]);
echo $t;

?>