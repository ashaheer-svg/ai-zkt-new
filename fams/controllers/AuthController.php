<?php
/**
 * Auth Controller
 * 
 * Manages the user authentication lifecycle.
 * Handles credential verification, role-based entry point redirection, 
 * and secure session termination.
 */
class AuthController
{
    /**
     * Handles user login.
     * Validates credentials against the database and establishes a session.
     * Redirects to the appropriate dashboard based on user privileges.
     * 
     * @param PDO $pdo
     * @param Auth $auth
     * @param Logger $logger
     */
    public static function login(PDO $pdo, Auth $auth, Logger $logger): void
    {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($auth->login($username, $password)) {
                $logger->activity($auth->id(), 'login', 'user', $auth->id());
                
                // Management users go to Dashboard; Field staff go to Applications list
                if ($auth->hasRole([ROLE_SYSADMIN, ROLE_OVERALL_INCHARGE])) {
                    redirect('index.php?page=dashboard');
                } else {
                    redirect('index.php?page=applications');
                }
            }
            $error = 'Invalid username or password.';
        }

        require __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Terminates the user session and clears authentication cookies.
     * 
     * @param PDO $pdo
     * @param Auth $auth
     * @param Logger $logger
     */
    public static function logout(PDO $pdo, Auth $auth, Logger $logger): void
    {
        if ($auth->isLoggedIn()) {
            $logger->activity($auth->id(), 'logout', 'user', $auth->id());
        }
        $auth->logout();
        redirect('index.php?page=login', 'success', 'You have been logged out.');
    }
}
