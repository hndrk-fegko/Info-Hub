<?php

declare(strict_types=1);

function seedAuthenticatedApiSession(string $email = 'test@example.com'): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION['authenticated'] = true;
    $_SESSION['auth_time'] = time();
    $_SESSION['csrf_token'] = 'test_csrf_token_123';
    $_SESSION['auth_email'] = $email;
}

function runApiRequest(array $get = [], array $post = [], array $files = [], string $method = 'GET', array $server = []): array {
    $originalGet = $_GET ?? [];
    $originalPost = $_POST ?? [];
    $originalFiles = $_FILES ?? [];
    $originalStatus = http_response_code();

    $trackedServerKeys = array_unique(array_merge([
        'REQUEST_METHOD',
        'HTTP_HOST',
        'SERVER_NAME',
        'HTTP_X_CSRF_TOKEN',
    ], array_keys($server)));

    $originalServer = [];
    foreach ($trackedServerKeys as $key) {
        $originalServer[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
    }

    $response = [
        'output' => '',
        'json' => null,
        'status' => 200,
    ];

    try {
        $_GET = $get;
        $_POST = $post;
        $_FILES = $files;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HOST'] = $server['HTTP_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
        $_SERVER['SERVER_NAME'] = $server['SERVER_NAME'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }

        http_response_code(200);
        ob_start();
        $oldLevel = error_reporting(E_ALL & ~E_WARNING);
        include __DIR__ . '/../backend/api/endpoints.php';
        error_reporting($oldLevel);
        $response['output'] = (string) ob_get_clean();
        restore_error_handler();

        $response['json'] = json_decode($response['output'], true);
        $response['status'] = http_response_code();
    } finally {
        $_GET = $originalGet;
        $_POST = $originalPost;
        $_FILES = $originalFiles;

        foreach ($trackedServerKeys as $key) {
            if ($originalServer[$key] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $originalServer[$key];
            }
        }

        restore_error_handler();
        http_response_code(is_int($originalStatus) ? $originalStatus : 200);
    }

    return $response;
}