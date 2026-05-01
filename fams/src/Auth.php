<?php
class Auth
{
    public function __construct(private PDO $pdo) {}

    // ── Session ───────────────────────────────────────────────────────────────
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

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

    public function logout(): void
    {
        session_destroy();
    }

    public function user(): ?array
    {
        return $_SESSION['user_obj'] ?? null;
    }

    public function id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public function role(): string
    {
        return $_SESSION['user_role'] ?? '';
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array)$roles;
        return in_array($this->role(), $roles, true);
    }

    // ── Village Scope ─────────────────────────────────────────────────────────
    /** Returns array of village IDs the current user is assigned to. Empty = no restriction. */
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

    public function isInVillage(int $villageId): bool
    {
        $villages = $this->myVillages();
        if (empty($villages)) return true; // unrestricted
        return in_array($villageId, $villages, true);
    }

    // ── Application Access ────────────────────────────────────────────────────
    public function canViewApplication(array $app): bool
    {
        // Privileged apps only visible to 1.c and sysadmin
        if ($app['is_privileged'] && !$this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return false;
        }
        // Global roles
        if ($this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return true;
        }
        // Village-scoped roles — check village assignment
        return $this->isInVillage((int)$app['village_id']);
    }

    public function canEditApplication(array $app): bool
    {
        if ($app['is_privileged'] && !$this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN])) {
            return false;
        }

        $status = $app['status'];

        // If not validated yet
        if ($status === STATUS_PENDING_VALIDATION || $status === STATUS_DRAFT) {
            return $this->hasRole([ROLE_DATA_ENTRY, ROLE_VILLAGE_INCHARGE, ROLE_SYSADMIN])
                && $this->isInVillage((int)$app['village_id']);
        }

        // Once validated, only 1.c (Overall Incharge) or Sysadmin can edit
        return $this->hasRole([ROLE_OVERALL_INCHARGE, ROLE_SYSADMIN]);
    }

    // ── Access Guard ──────────────────────────────────────────────────────────
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            redirect('index.php?page=login');
        }
    }

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
