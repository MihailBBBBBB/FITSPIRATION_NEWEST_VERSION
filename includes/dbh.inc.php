<?php

function parseFitspirationDatabaseUrl(string $databaseUrl): ?array {
    $parts = parse_url($databaseUrl);
    if ($parts === false || !isset($parts['host'])) {
        return null;
    }

    $databaseName = '';
    if (isset($parts['path'])) {
        $databaseName = trim($parts['path'], '/');
    }

    return [
        'host' => (string) $parts['host'],
        'port' => isset($parts['port']) ? (string) $parts['port'] : '3306',
        'name' => $databaseName,
        'user' => isset($parts['user']) ? rawurldecode((string) $parts['user']) : '',
        'password' => isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '',
    ];
}

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

$databaseUrl = getenv('FITSPIRATION_DB_URL');
$databaseConfig = ($databaseUrl !== false && $databaseUrl !== '')
    ? parseFitspirationDatabaseUrl($databaseUrl)
    : null;

$dbhost = $databaseConfig['host'] ?? (getenv('FITSPIRATION_DB_HOST') ?: 'localhost');
$dbname = $databaseConfig['name'] ?? (getenv('FITSPIRATION_DB_NAME') ?: 'fitspiration');
$dbport = $databaseConfig['port'] ?? (getenv('FITSPIRATION_DB_PORT') ?: '3306');
$dbusername = $databaseConfig['user'] ?? (getenv('FITSPIRATION_DB_USER') ?: 'root');
$dbpassword = $databaseConfig['password'] ?? getenv('FITSPIRATION_DB_PASSWORD');

if ($dbpassword === false) {
    $dbpassword = '';
}

$dsn = sprintf('mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4', $dbhost, $dbname, $dbport);

try {
    $pdo = new PDO ($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log(sprintf(
        'Database connection failed for host=%s port=%s db=%s user=%s: %s',
        $dbhost,
        $dbport,
        $dbname,
        $dbusername,
        $e->getMessage()
    ));

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed." . PHP_EOL);
    } else {
        http_response_code(500);
        echo 'Database connection failed.';
    }

    exit();
}


    