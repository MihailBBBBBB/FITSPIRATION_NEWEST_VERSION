<?php

function loadFitspirationEnv(string $envPath): void {
    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        $separatorPosition = strpos($trimmedLine, '=');
        if ($separatorPosition === false) {
            continue;
        }

        $name = trim(substr($trimmedLine, 0, $separatorPosition));
        $value = trim(substr($trimmedLine, $separatorPosition + 1));

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

loadFitspirationEnv(dirname(__DIR__) . '/.env');

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


    