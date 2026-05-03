<?php
/**
 * Global Helper Functions
 * 
 * Contains utility functions for output escaping, formatting, security (CSRF),
 * session messaging, and common UI components like pagination and sorting.
 */

// ── Output & Formatting ──────────────────────────────────────────────────────

/**
 * Escapes a string for safe HTML output.
 * 
 * @param mixed $v Value to escape
 * @return string HTML-safe string
 */
function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a float as a standard money string (2 decimal places).
 * 
 * @param float $v
 * @return string
 */
function money(float $v): string
{
    return number_format($v, 2);
}

/**
 * Formats a date string into a human-readable format.
 * 
 * @param string|null $d Date string (e.g., from DB)
 * @param string $fmt Target format
 * @return string Formatted date or placeholder
 */
function fdate(?string $d, string $fmt = 'd M Y'): string
{
    if (!$d) return '—';
    return date($fmt, strtotime($d));
}

/**
 * Renders an HTML status badge for an application.
 * 
 * @param string $status Internal status key
 * @return string HTML span with badge classes
 */
function status_badge(string $status): string
{
    $labels = STATUS_LABELS;
    $badges = STATUS_BADGE;
    $cls    = $badges[$status] ?? 'badge-gray';
    $lbl    = $labels[$status] ?? ucfirst($status);
    return '<span class="badge ' . e($cls) . '">' . e($lbl) . '</span>';
}

/**
 * Renders an HTML status badge for a disbursement.
 * 
 * @param string $status Internal disbursement status key
 * @return string HTML span with badge classes
 */
function disb_badge(string $status): string
{
    $lbl = DISB_STATUS_LABELS[$status] ?? ucfirst($status);
    $cls = DISB_STATUS_BADGE[$status]  ?? 'badge-gray';
    return '<span class="badge ' . e($cls) . '">' . e($lbl) . '</span>';
}

/**
 * Returns a human-readable label for a role identifier.
 * 
 * @param string $role
 * @return string
 */
function role_label(string $role): string
{
    return ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

// ── CSRF Protection ──────────────────────────────────────────────────────────

/**
 * Generates or retrieves the current CSRF token from the session.
 * 
 * @return string
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns a hidden HTML input field containing the CSRF token.
 * 
 * @return string
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verifies the CSRF token on POST requests. Dies on mismatch.
 * Should be called at the start of any state-changing action.
 */
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

/**
 * Stores a flash message in the session for the next request.
 * 
 * @param string $type alert type (success, error, warning, info)
 * @param string $msg
 */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/**
 * Renders all pending flash messages and clears them from the session.
 * 
 * @return string HTML alert blocks
 */
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

// ── Redirection ───────────────────────────────────────────────────────────────

/**
 * Performs a header redirect and terminates execution.
 * 
 * @param string $url
 * @param string|null $flashType Optional flash type to set
 * @param string|null $flashMsg Optional flash message to set
 */
function redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never
{
    if ($flashType && $flashMsg) flash($flashType, $flashMsg);
    header('Location: ' . $url);
    exit;
}

// ── Pagination Logic ──────────────────────────────────────────────────────────

/**
 * Executes a paginated SQL query.
 * 
 * @param PDO $pdo
 * @param string $sql The base SQL query (without LIMIT/OFFSET)
 * @param array $params Query parameters
 * @param int $page Current page number
 * @param int $perPage Items per page
 * @return array ['rows', 'total', 'page', 'pages', 'perPage']
 */
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

// ── Utilities ─────────────────────────────────────────────────────────────────

/**
 * Generates a pseudo-v4 UUID. Useful for safe, non-guessable filenames.
 * 
 * @return string
 */
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

/**
 * Retrieves the client's IP address, handling proxy headers if present.
 * 
 * @return string
 */
function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

// ── UI Components ─────────────────────────────────────────────────────────────

/**
 * Renders a sortable column header link with up/down indicator icons.
 * 
 * @param string $label Visible text
 * @param string $field Database column to sort by
 * @return string HTML link
 */
function sort_link(string $label, string $field): string
{
    $params = $_GET;
    $currentSort = $params['sort'] ?? '';
    $currentOrder = strtoupper($params['order'] ?? 'ASC');
    
    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $params['sort'] = $field;
    $params['order'] = $newOrder;
    $params['p'] = 1; // Reset to page 1 on sort
    
    $url = 'index.php?' . http_build_query($params);
    $icon = '';
    if ($currentSort === $field) {
        $icon = ($currentOrder === 'ASC') ? ' ▴' : ' ▾';
    }
    
    return '<a href="' . e($url) . '" class="sort-link">' . e($label) . $icon . '</a>';
}

/**
 * Renders a standard pagination control with "..." for large ranges.
 * 
 * @param array $p Pagination metadata from paginate()
 * @return string HTML controls
 */
function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) return '';
    
    $html = '<div class="pagination">';
    $params = $_GET;
    
    // Prev link
    if ($p['page'] > 1) {
        $params['p'] = $p['page'] - 1;
        $html .= '<a href="index.php?'.http_build_query($params).'" class="page-link">&laquo;</a>';
    }

    // Individual page links with logic to hide excessive pages
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i > 3 && $i < $p['pages'] - 2 && abs($i - $p['page']) > 2) {
            if (!str_ends_with($html, '<span class="pager-dots">...</span>')) $html .= '<span class="pager-dots">...</span>';
            continue;
        }
        $params['p'] = $i;
        $active = ($i === $p['page']) ? 'active' : '';
        $html .= '<a href="index.php?'.http_build_query($params).'" class="page-link '.$active.'">'.$i.'</a>';
    }

    // Next link
    if ($p['page'] < $p['pages']) {
        $params['p'] = $p['page'] + 1;
        $html .= '<a href="index.php?'.http_build_query($params).'" class="page-link">&raquo;</a>';
    }
    
    $html .= '</div>';
    return $html;
}
