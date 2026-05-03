<?php
/**
 * Authentication and Authorization Service
 * 
 * Handles user sessions, password verification, mobile API token validation,
 * and role-based access control (RBAC) including village-level scoping.
 */
class Auth
{
    /**
     * @param PDO $pdo Database connection for user and token lookups
     */
    public function __construct(private PDO $pdo) {}

    // ── Session Management ───────────────────────────────────────────────────

    /**
     * Checks if a user is currently logged into the web session.
     * 
     * @return bool True if session exists
     */
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Authenticates a user via username and password.
     * Initializes the web session upon success.
     * 
     * @param string $username
     * @param string $password
     * @return bool True if authentication succeeded
     */
    public function login(string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = ? AND is_active = 1'
        );
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_obj']  = $user;
        return true;
    }

    /**
     * Authenticates a mobile client via a security token.
     * Mimics a web session to ensure standard role checks work correctly.
     * 
     * @param string $token The API security token
     * @return bool True if token is valid and not expired
     */
    public function loginByToken(string $token): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, t.expires_at 
            FROM users u
            JOIN api_tokens t ON t.user_id = u.id
            WHERE t.token = ? AND u.is_active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) return false;

        // Check expiry
        if (strtotime($user['expires_at']) < time()) return false;

        // Populate session-like data for consistency with hasRole() etc
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_obj']  = $user;

        // Update last used timestamp
        $this->pdo->prepare("UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE token = ?")
                  ->execute([$token]);

        return true;
    }

    /**
     * Destroys the current user session.
     */
    public function logout(): void
    {
        session_destroy();
    }

    /**
     * Returns the full user data array from the session.
     * 
     * @return array|null
     */
    public function user(): ?array
    {
        return $_SESSION['user_obj'] ?? null;
    }

    /**
     * Returns the current user's numeric ID.
     * 
     * @return int
     */
    public function id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    /**
     * Returns the current user's role identifier.
     * 
     * @return string (e.g., '1.a', '1.b')
     */
    public function role(): string
    {
        return $_SESSION['user_role'] ?? '';
    }

    /**
     * Checks if the current user has one of the specified roles.
     * 
     * @param string|array $roles Single role or array of allowed roles
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array)$roles;
        return in_array($this->role(), $roles, true);
    }

    // ── Village Scope Logic ──────────────────────────────────────────────────

    /** 
     * Returns an array of village IDs assigned to the current user.
     * Users with ROLE_SYSADMIN or ROLE_OVERALL_INCHARGE return an empty array (unrestricted).
     * 
     * @return array List of Village IDs
     */
    public function myVillages(): array
    {
        if ($this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return []; // no restriction
        }
        static $cache = null;
        if ($cache !== null) return $cache;

        $stmt = $this->pdo->prepare(
            'SELECT village_id FROM user_villages WHERE user_id = ?'
        );
        $stmt->execute([$this->id()]);
        $cache = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $cache;
    }

    /**
     * Checks if the current user has access to a specific village.
     * 
     * @param int $villageId
     * @return bool
     */
    public function isInVillage(int $villageId): bool
    {
        if ($this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return true; // unrestricted
        }
        $villages = $this->myVillages();
        if (empty($villages)) return false; // default-closed: No assignments = No access
        return in_array((int)$villageId, array_map('intval', $villages));
    }

    // ── Application Access Guard ─────────────────────────────────────────────

    /**
     * Determines if the user can VIEW a specific application.
     * 
     * @param array $app Application data array
     * @return bool
     */
    public function canViewApplication(array $app): bool
    {
        // Privileged apps only visible to 1.c and sysadmin
        if ($app['is_privileged'] && !$this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return false;
        }
        // Check village-scoped access (handles global roles internally)
        return $this->isInVillage((int)$app['village_id']);
    }

    /**
     * Determines if the user can EDIT a specific application.
     * 
     * @param array $app Application data array
     * @return bool
     */
    public function canEditApplication(array $app): bool
    {
        if ($app['is_privileged'] && !$this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return false;
        }

        $status = $app['status'];

        // If in pre-validation or draft state
        if ($status === STATUS_PENDING_VALIDATION || $status === STATUS_DRAFT) {
            return $this->hasRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_SYSADMIN])
                && $this->isInVillage((int)$app['village_id']);
        }

        // Once validated/approved, only 1.c (Overall Incharge) or Sysadmin can edit
        return $this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
    }

    // ── Access Guards (Middleware-like) ──────────────────────────────────────

    /**
     * Enforces a logged-in session. Redirects to login if missing.
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('index.php?page=login');
        }
    }

    /**
     * Enforces specific role requirements. Displays 403 Access Denied if failed.
     * 
     * @param string|array $roles Allowed roles
     */
    public function requireRole(string|array $roles): void
    {
        $this->requireLogin();
        if (!$this->hasRole($roles)) {
            http_response_code(403);
            die("
                <div style='font-family:sans-serif; text-align:center; padding: 50px;'>
                    <h2>403 — Access Denied</h2>
                    <p>You do not have permission to access this page.</p>
                    <a href='index.php'>Back to Home</a>
                </div>
            ");
        }
    }
}
