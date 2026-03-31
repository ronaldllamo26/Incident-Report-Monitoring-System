<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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