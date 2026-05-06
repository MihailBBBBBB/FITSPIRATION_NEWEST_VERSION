<?php

function ensurePasswordStorageCapacity(PDO $pdo): void {
    static $alreadyChecked = false;

    if ($alreadyChecked) {
        return;
    }

    $stmt = $pdo->prepare("SHOW COLUMNS FROM registration LIKE 'password'");
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        throw new RuntimeException('Password column was not found.');
    }

    $columnType = strtolower((string) ($column['Type'] ?? ''));
    if (preg_match('/varchar\((\d+)\)/', $columnType, $matches) === 1) {
        $currentLength = (int) $matches[1];
        if ($currentLength < 255) {
            $pdo->exec("ALTER TABLE registration MODIFY password VARCHAR(255) NULL");
        }
    }

    $alreadyChecked = true;
}