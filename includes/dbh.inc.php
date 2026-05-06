<?php

$dbhost = getenv('FITSPIRATION_DB_HOST') ?: 'localhost';
$dbname = getenv('FITSPIRATION_DB_NAME') ?: 'fitspiration';
$dbport = getenv('FITSPIRATION_DB_PORT') ?: '3306';
$dbusername = getenv('FITSPIRATION_DB_USER') ?: 'root';
$dbpassword = getenv('FITSPIRATION_DB_PASSWORD');

if ($dbpassword === false) {
    $dbpassword = '';
}

$dsn = sprintf('mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4', $dbhost, $dbname, $dbport);

try {
    $pdo = new PDO ($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed." . PHP_EOL);
    } else {
        http_response_code(500);
        echo 'Database connection failed.';
    }

    exit();
}


    