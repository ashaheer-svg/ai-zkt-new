<?php
// ── Output ────────────────────────────────────────────────────────────────────
function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function money(float $v): string
{
    return number_format($v, 2);
}

function fdate(?string $d, string $fmt = 'd M Y'): string
{
    if (!$d) return '—';
    return date($fmt, strtotime($d));
}

function status_badge(string $status): string
{
    $labels = STATUS_LABELS;
    $badges = STATUS_BADGE;
    $cls    = $badges[$status] ?? 'badge-gray';
    $lbl    = $labels[$status] ?? ucfirst($status);
    return '<span class="badge ' . e($cls) . '">' . e($lbl) . '</span>';
}

function disb_badge(string $status): string
{
    $lbl = DISB_STATUS_LABELS[$status] ?? ucfirst($status);
    $cls = DISB_STATUS_BADGE[$status]  ?? 'badge-gray';
    return '<span class="badge ' . e($cls) . '">' . e($lbl) . '</span>';
}

function role_label(string $role): string
{
    return ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}

// ── Flash Messages ────────────────────────────────────────────────────────────
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ── Redirect ──────────────────────────────────────────────────────────────────
function redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never
{
    if ($flashType && $flashMsg) flash($flashType, $flashMsg);
    header('Location: ' . $url);
    exit;
}

// ── Pagination ────────────────────────────────────────────────────────────────
function paginate(PDO $pdo, string $sql, array $params, int $page, int $perPage = PER_PAGE): array
{
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ')';
    $stmt     = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total    = (int)$stmt->fetchColumn();
    $pages    = max(1, (int)ceil($total / $perPage));
    $page     = max(1, min($page, $pages));
    $offset   = ($page - 1) * $perPage;

    $stmt = $pdo->prepare($sql . " LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return compact('rows', 'total', 'page', 'pages', 'perPage');
}

// ── UUID for filenames ────────────────────────────────────────────────────────
function uuid(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ── IP helper ─────────────────────────────────────────────────────────────────
function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}
