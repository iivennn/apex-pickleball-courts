<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Shared database connection (PDO with prepared statements everywhere). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = defined('DB_DSN') ? DB_DSN : 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<h1>Database connection failed</h1><p>Check the settings in <code>config.php</code> and make sure you imported <code>database/schema.sql</code>.</p>');
        }
    }
    return $pdo;
}

/** Escape text for HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- CSRF protection ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------- Flash messages ----------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '" role="status">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Formatting ----------
function money($amount): string
{
    return '₱' . number_format((float) $amount, 0);
}

function fmt_hour(int $hour): string
{
    return date('g:i A', mktime($hour, 0));
}

function fmt_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

function fmt_date(string $date, string $format = 'D, M j'): string
{
    return date($format, strtotime($date));
}

/** Turn a list of hours like [17,18,20] into "5:00–7:00 PM, 8:00–9:00 PM". */
function fmt_slots(array $hours): string
{
    sort($hours);
    $ranges = [];
    foreach ($hours as $h) {
        $last = count($ranges) - 1;
        if ($last >= 0 && $ranges[$last][1] === $h) {
            $ranges[$last][1] = $h + 1;
        } else {
            $ranges[] = [$h, $h + 1];
        }
    }
    return implode(', ', array_map(fn($r) => fmt_hour($r[0]) . ' – ' . fmt_hour($r[1]), $ranges));
}

function generate_reference(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $ref = 'APX-';
    for ($i = 0; $i < 6; $i++) {
        $ref .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $ref;
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function level_class(string $level): string
{
    return [
        'Beginner' => 'tag-green',
        'Intermediate' => 'tag-yellow',
        'Advanced' => 'tag-orange',
    ][$level] ?? 'tag-gray';
}

function payment_label(string $method): string
{
    return ['gcash' => 'GCash / Maya', 'card' => 'Card', 'venue' => 'Pay at venue'][$method] ?? $method;
}

// ---------- Admin auth ----------
function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('login.php');
    }
}
