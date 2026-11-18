<?php
// Database helpers. Use in any API file with:
//   require_once __DIR__ . '/db.php';

// Opens comp307.db once and reuses the same connection.
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../comp307.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);          // throw on SQL errors
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);    // rows as ['col' => value]
        $pdo->exec('PRAGMA foreign_keys = ON');                                // enforce links + cascades
    }
    return $pdo;
}

// Runs any SQL with ? placeholders and returns the statement.
//   query('DELETE FROM sessions WHERE token = ?', [$token]);
function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
