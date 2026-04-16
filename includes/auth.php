<?php
require_once __DIR__ . '/Firewall.php';
Firewall::protect();
header("X-Firewall: Iron-Dome-Active");

if (session_status() === PHP_SESSION_NONE) {
    // ── SECURE SESSION COOKIE FLAGS ───────────────────────────
    // Must be set BEFORE session_start() to take effect.
    //
    //  HttpOnly   → JavaScript cannot read the session cookie
    //               (blocks XSS-based cookie theft)
    //  SameSite   → Cookie not sent on cross-site requests
    //               (extra CSRF layer on top of our CSRF tokens)
    //  Secure     → Cookie only sent over HTTPS
    //               (auto-disabled on localhost for dev comfort)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    session_set_cookie_params([
        'lifetime' => 0,            // Session cookie (expires on browser close)
        'path'     => '/',
        'domain'   => '',           // Current domain only
        'secure'   => $isHttps,     // HTTPS only when deployed
        'httponly' => true,         // No JS access
        'samesite' => 'Strict',     // No cross-site sending
    ]);
    // ─────────────────────────────────────────────────────────

    session_start();
}
require_once __DIR__ . '/functions.php';

/**
 * Check kung kasama ang IP sa Ban List
 */
function checkIpBan(): void {
    global $pdo;
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }
    $stmt = $pdo->prepare("SELECT id FROM banned_ips WHERE ip_address = ?");
    $stmt->execute([$ip]);
    if ($stmt->fetch()) {
        http_response_code(403);
        die("<h2>403 Forbidden</h2><p>Pasadya at paulit-ulit na paglabag sa alituntunin. Ang iyong connection ay permanenteng na-ban sa paggamit ng system.</p>");
    }
}


/**
 * Check kung logged in ang user
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Requirement check bago maka-access ng page.
 * Itatapon ang user sa tamang login page base sa URL.
 */
function requireLogin(): void {
    checkIpBan();
    if (!isLoggedIn()) {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        
        // Pag nasa /portal/ folder, sa portal login ang bagsak
        if (strpos($url, '/portal/') !== false) {
            header('Location: /irms/portal/login.php');
        } else {
            header('Location: /irms/citizen/login.php');
        }
        exit;
    }
}

/**
 * Strict role checking (Admin, Responder, Citizen)
 */
function requireRole(string|array $allowedRoles): void {
    checkIpBan();
    requireLogin();
    
    $allowedRoles = (array) $allowedRoles;
    $userRole = $_SESSION['role'] ?? '';

    if (!in_array($userRole, $allowedRoles)) {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        
        // Redirect with error message pag bawal siya rito
        if (strpos($url, '/portal/') !== false) {
            header('Location: /irms/portal/login.php?error=unauthorized');
        } else {
            header('Location: /irms/citizen/login.php?error=unauthorized');
        }
        exit;
    }
}

/**
 * Get current user session data
 */
function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name']    ?? 'Guest',
        'role' => $_SESSION['role']    ?? '',
    ];
}

/**
 * Eto yung logic pagkatapos ng successful login process
 */
function redirectByRole(): void {
    // Siguraduhin na may role bago mag-switch
    $role = $_SESSION['role'] ?? '';

    switch ($role) {
        case 'admin':
            header('Location: /irms/portal/admin/dashboard.php'); 
            break;
        case 'responder':
            header('Location: /irms/portal/responder/dashboard.php'); 
            break;
        case 'citizen':
        default:
            // Check kung citizen talaga o kung walang role, back to main
            if ($role === 'citizen') {
                header('Location: /irms/citizen/dashboard.php');
            } else {
                header('Location: /irms/index.php');
            }
            break;
    }
    exit;
}