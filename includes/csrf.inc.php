<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCsrfToken(): string {
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function getRequestCsrfToken(): string {
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }

    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    return '';
}

function isValidCsrfToken(?string $token = null): bool {
    $submittedToken = $token ?? getRequestCsrfToken();
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    return is_string($submittedToken)
        && $submittedToken !== ''
        && is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $submittedToken);
}

function requireValidCsrfToken(bool $expectsJson = false): void {
    if (isValidCsrfToken()) {
        return;
    }

    http_response_code(403);

    if ($expectsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token.',
            'error' => 'invalid_csrf',
        ]);
        exit();
    }

    echo 'Invalid CSRF token.';
    exit();
}