<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');

// No token provided — invalid link
if (empty($token) || strlen($token) !== 64) {
    header('Location: /irms/citizen/login.php?error=' .
           urlencode('Invalid na verification link. Subukan ulit mag-register o humingi ng bagong link.'));
    exit;
}

$userModel = new User();
$result    = $userModel->verifyByToken($token);

if ($result === 'verified') {
    // ── Verification successful ─────────────────────────
    header('Location: /irms/citizen/login.php?success=verified');

} elseif ($result === 'expired') {
    // ── Token found but expired ─────────────────────────
    header('Location: /irms/citizen/login.php?error=' .
           urlencode('Expired na ang verification link. Mag-register ulit o humingi ng bagong link.'));

} else {
    // ── Token not found / already used ─────────────────
    header('Location: /irms/citizen/login.php?error=' .
           urlencode('Hindi valid ang verification link. Baka na-verify na ang account mo — subukang mag-login.'));
}
exit;
