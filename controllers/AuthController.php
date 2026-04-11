<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {

    private User $user;

    public function __construct() {
        $this->user = new User();
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';

        // ── PORTAL DETECTION ──────────────────────────────
        $portal = $_POST['portal'] ?? $_GET['portal'] ?? '';

        if (empty($portal)) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, '/irms/portal/') !== false) {
                $portal = 'staff';
            }
        }

        // Basic validation
        if (empty($email) || empty($password)) {
            $this->redirectWithError('Punan ang lahat ng fields.', $portal);
            return;
        }

        $user = $this->user->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->redirectWithError('Mali ang email o password.', $portal);
            return;
        }

        // ── PORTAL VALIDATION ─────────────────────────────
        if ($portal === 'staff' && $user['role'] === 'citizen') {
            $this->redirectWithError(
                'Walang access sa Staff Portal. Gamitin ang Citizen login.',
                'staff'
            );
            return;
        }

        if ($portal !== 'staff' && in_array($user['role'], ['admin', 'responder'])) {
            header('Location: /irms/portal/login.php?error=' .
                   urlencode('Para sa mga staff, mag-login dito sa Staff Portal.'));
            exit;
        }

        // ── SET SESSION ───────────────────────────────────
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        // ── AUDIT LOG — login ─────────────────────────────
        global $pdo;
        logAudit(
            $pdo,
            $user['id'],
            'user_login',
            'user',
            $user['id'],
            $user['name'] . ' nag-login via ' . ($portal ?: 'citizen') . ' portal'
        );

        // ── REDIRECT ──────────────────────────────────────
        redirectByRole();
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $name     = trim($_POST['name']             ?? '');
        $email    = trim($_POST['email']            ?? '');
        $password = $_POST['password']              ?? '';
        $confirm  = $_POST['confirm_password']      ?? '';
        $phone    = trim($_POST['phone']            ?? '');
        $address  = trim($_POST['address']          ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $this->redirectWithError('Punan ang lahat ng required fields.', 'citizen', 'register');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithError('Hindi valid ang email address.', 'citizen', 'register');
            return;
        }
        if (strlen($password) < 8) {
            $this->redirectWithError('Dapat 8 characters minimum ang password.', 'citizen', 'register');
            return;
        }
        if ($password !== $confirm) {
            $this->redirectWithError('Hindi magkapareho ang password.', 'citizen', 'register');
            return;
        }
        if ($this->user->emailExists($email)) {
            $this->redirectWithError('Ginagamit na ang email na yan.', 'citizen', 'register');
            return;
        }

        $created = $this->user->create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'phone'    => $phone,
            'address'  => $address,
        ]);

        if ($created) {
            header('Location: /irms/citizen/login.php?success=registered');
        } else {
            $this->redirectWithError('May error sa pagre-register. Subukan ulit.', 'citizen', 'register');
        }
        exit;
    }

    public function logout(): void {
        // ── AUDIT LOG — logout ────────────────────────────
        if (isLoggedIn()) {
            global $pdo;
            logAudit(
                $pdo,
                $_SESSION['user_id'] ?? null,
                'user_logout',
                'user',
                $_SESSION['user_id'] ?? null,
                ($_SESSION['name'] ?? 'Unknown') . ' nag-logout'
            );
        }

        session_destroy();
        header('Location: /irms/index.php');
        exit;
    }

    private function redirectWithError(
        string $msg,
        string $portal = 'citizen',
        string $page   = 'login'
    ): void {
        if ($portal === 'staff') {
            $path = '/irms/portal/login.php';
        } else {
            $path = '/irms/citizen/' . $page . '.php';
        }
        header('Location: ' . $path . '?error=' . urlencode($msg));
        exit;
    }
}

// ── ROUTE ──────────────────────────────────────────────
$action     = $_GET['action'] ?? $_POST['action'] ?? '';
$controller = new AuthController();

match($action) {
    'login'    => $controller->login(),
    'register' => $controller->register(),
    'logout'   => $controller->logout(),
    default    => null
};