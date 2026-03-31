<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private User $user;

    public function __construct() {
        $this->user = new User();
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Basic validation
        if (empty($email) || empty($password)) {
            $this->redirectWithError('login', 'Punan ang lahat ng fields.');
            return;
        }

        $user = $this->user->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->redirectWithError('login', 'Mali ang email o password.');
            return;
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        redirectByRole();
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            $this->redirectWithError('register', 'Punan ang lahat ng required fields.');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirectWithError('register', 'Hindi valid ang email address.');
            return;
        }

        if (strlen($password) < 8) {
            $this->redirectWithError('register', 'Dapat 8 characters minimum ang password.');
            return;
        }

        if ($password !== $confirm) {
            $this->redirectWithError('register', 'Hindi magkapareho ang password.');
            return;
        }

        if ($this->user->emailExists($email)) {
            $this->redirectWithError('register', 'Ginagamit na ang email na yan.');
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
            $this->redirectWithError('register', 'May error sa pagre-register. Subukan ulit.');
        }
        exit;
    }

    public function logout(): void {
        session_destroy();
        header('Location: /irms/citizen/login.php');
        exit;
    }

    private function redirectWithError(string $page, string $msg): void {
        header('Location: /irms/citizen/' . $page . '.php?error=' . urlencode($msg));
        exit;
    }
}

// Route based on action
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$controller = new AuthController();

match($action) {
    'login'    => $controller->login(),
    'register' => $controller->register(),
    'logout'   => $controller->logout(),
    default    => null
};