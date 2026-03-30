<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /irms/views/auth/login.php');
        exit;
    }
}

function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /irms/views/auth/login.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name']    ?? '',
        'role' => $_SESSION['role']    ?? '',
    ];
}

function redirectByRole(): void {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /irms/views/admin/dashboard.php'); break;
        case 'responder':
            header('Location: /irms/views/responder/dashboard.php'); break;
        default:
            header('Location: /irms/views/citizen/dashboard.php'); break;
    }
    exit;
}