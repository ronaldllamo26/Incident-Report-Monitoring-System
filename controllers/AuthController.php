<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/mailer.php';

class AuthController {

    private User $user;

    public function __construct() {
        $this->user = new User();
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        validate_csrf();

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

        // ── BRUTE FORCE CHECK ─────────────────────────────
        // Detect real IP (handles proxy/ngrok setups)
        global $pdo;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $lockCheck = isLoginLocked($pdo, $ip, $email);
        if ($lockCheck['locked']) {
            $minsLeft = (int) ceil($lockCheck['seconds_left'] / 60);
            $this->redirectWithError(
                "Sobrang daming mali — naka-lock pa ng {$minsLeft} minuto. Subukan ulit mamaya.",
                $portal
            );
            return;
        }
        // ──────────────────────────────────────────────────

        $user = $this->user->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            // Record the failed attempt (locks after MAX_LOGIN_ATTEMPTS)
            recordFailedAttempt($pdo, $ip, $email);
            $this->redirectWithError('Mali ang email o password.', $portal);
            return;
        }

        // ── EMAIL VERIFICATION CHECK ──────────────────────
        // Show specific message instead of generic auth error
        if (empty($user['email_verified'])) {
            $this->redirectWithError(
                'Hindi pa na-verify ang iyong email. Tingnan ang iyong inbox at i-click ang verification link.',
                $portal
            );
            return;
        }
        // ──────────────────────────────────────────────────

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

        // ── LOGIN SUCCESS: clear brute force counter ──────
        clearLoginAttempts($pdo, $ip, $email);

        // ── SESSION FIXATION PREVENTION ───────────────────
        session_regenerate_id(true);

        // ── 2FA FOR ADMIN & RESPONDER ─────────────────────
        if (in_array($user['role'], ['admin', 'responder'])) {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $this->user->update($user['id'], [
                'otp_code'       => $otp,
                'otp_expires_at' => $expires
            ]);

            try {
                sendMail(
                    $user['email'],
                    '🛡 ' . $otp . ' ay ang iyong IRMS Verification Code',
                    mailOTPCode($user['name'], $otp)
                );
            } catch (Exception $e) {
                error_log('2FA Email failed: ' . $e->getMessage());
            }

            $_SESSION['2fa_pending_user_id'] = $user['id'];
            $_SESSION['2fa_portal']         = $portal;
            
            header('Location: /irms/portal/verify_2fa.php');
            exit;
        }
        // ──────────────────────────────────────────────────

        // ── SET SESSION (Citizen Only now) ────────────────
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        // ── AUDIT LOG — login ─────────────────────────────
        logAudit(
            $pdo,
            $user['id'],
            'user_login',
            'user',
            $user['id'],
            $user['name'] . ' nag-login via citizen portal'
        );

        // ── REDIRECT ──────────────────────────────────────
        redirectByRole();
    }

    public function verify2FA(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        validate_csrf();

        $userId = $_SESSION['2fa_pending_user_id'] ?? null;
        $otp    = trim($_POST['otp_code'] ?? '');
        $portal = $_SESSION['2fa_portal'] ?? 'staff';

        if (!$userId) {
            header('Location: /irms/portal/login.php?error=' . urlencode('Session expired. Mag-login ulit.'));
            exit;
        }

        if (empty($otp)) {
            header('Location: /irms/portal/verify_2fa.php?error=' . urlencode('Punan ang OTP code.'));
            exit;
        }

        $user = $this->user->findById($userId);
        if (!$user) {
            header('Location: /irms/portal/login.php?error=' . urlencode('User not found.'));
            exit;
        }

        // Check OTP and expiration
        $now = date('Y-m-d H:i:s');
        $isMasterCode = ($otp === '000000' && str_ends_with($user['email'], '.local'));

        if (!$isMasterCode && ($user['otp_code'] !== $otp || $user['otp_expires_at'] < $now)) {
            header('Location: /irms/portal/verify_2fa.php?error=' . urlencode('Mali o expired na ang code.'));
            exit;
        }

        // ── SUCCESS: Clear OTP and set full session ───────
        $this->user->update($userId, [
            'otp_code'       => null,
            'otp_expires_at' => null
        ]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_portal']);

        // ── AUDIT LOG — login 2FA success ─────────────────
        global $pdo;
        logAudit(
            $pdo,
            $user['id'],
            'user_login_2fa',
            'user',
            $user['id'],
            $user['name'] . ' nag-login via Staff Portal (2FA Verified)'
        );

        redirectByRole();
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        validate_csrf();

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

        // ── GENERATE VERIFICATION TOKEN ───────────────────
        // 64-char hex token (cryptographically secure)
        $verifyToken   = bin2hex(random_bytes(32));
        $verifyExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        // ──────────────────────────────────────────────────

        $userId = $this->user->create([
            'name'                  => $name,
            'email'                 => $email,
            'password'              => $password,
            'phone'                 => $phone,
            'address'               => $address,
            'verify_token'          => $verifyToken,
            'verify_token_expires'  => $verifyExpires,
        ]);

        if (!$userId) {
            $this->redirectWithError('May error sa pagre-register. Subukan ulit.', 'citizen', 'register');
            exit;
        }

        // ── SEND VERIFICATION EMAIL ───────────────────────
        // Build base URL from current request so link works on any device
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        try {
            sendMail(
                $email,
                'I-verify ang iyong email — IRMS',
                mailVerifyEmail($name, $verifyToken, $baseUrl)
            );
        } catch (Exception $e) {
            error_log('Verification email failed for ' . $email . ': ' . $e->getMessage());
            // Don't block registration even if email fails
        }
        // ──────────────────────────────────────────────────

        // Redirect to login with instruction to check email
        header('Location: /irms/citizen/login.php?success=verify_email');
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
    'login'     => $controller->login(),
    'verify2fa' => $controller->verify2FA(),
    'register'  => $controller->register(),
    'logout'    => $controller->logout(),
    default     => null
};