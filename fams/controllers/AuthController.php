<?php
class AuthController
{
    public static function login(PDO $pdo, Auth $auth, Logger $logger): void
    {
        if ($auth->isLoggedIn()) redirect('index.php?page=dashboard');

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($auth->login($username, $password)) {
                $logger->activity($auth->id(), 'login', 'user', $auth->id());
                redirect('index.php?page=dashboard');
            }
            $error = 'Invalid username or password.';
        }

        require __DIR__ . '/../views/auth/login.php';
    }

    public static function logout(PDO $pdo, Auth $auth, Logger $logger): void
    {
        if ($auth->isLoggedIn()) {
            $logger->activity($auth->id(), 'logout', 'user', $auth->id());
        }
        $auth->logout();
        redirect('index.php?page=login', 'success', 'You have been logged out.');
    }
}
