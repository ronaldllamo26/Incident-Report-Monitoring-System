<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
} else {
    header('Location: /irms/views/auth/login.php');
    exit;
}