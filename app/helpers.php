<?php
declare(strict_types=1);
/** Helper global: DB, escaping, CSRF, flash, auth, format. */

function cfg(string $section, string $key = '')
{
    $c = $GLOBALS['config'][$section];
    return $key === '' ? $c : $c[$key];
}

/** Koneksi PDO singleton. Prepared statements asli (anti SQL-injection). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'], $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

/** Escape output (anti XSS). */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Base URL otomatis (mendukung docroot = public/ maupun subfolder). */
function base_url(): string
{
    static $b = null;
    if ($b === null) {
        $s = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // /x/public/admin/a.php -> /x/public/_ ; /x/public/a.php tetap -> lalu buang segmen terakhir => /x/public
        $s = preg_replace('#/(admin|api)/[^/]+$#', '/_', $s);
        $b = rtrim(preg_replace('#/[^/]*$#', '', $s), '/');
    }
    return $b;
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

/** Ambil & bersihkan input teks. */
function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim(strip_tags($v)) : $default;
}
function input_int(string $key, int $default = 0): int { return (int)filter_var(input($key), FILTER_VALIDATE_INT, ['options' => ['default' => $default]]); }
function input_float(string $key, float $default = 0.0): float { return (float)filter_var(input($key), FILTER_VALIDATE_FLOAT, ['options' => ['default' => $default]]); }

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    $t = $_POST['_csrf'] ?? '';
    $s = $_SESSION['_csrf'] ?? '';
    // Token & sesi WAJIB non-kosong (hash_equals('', '') bernilai true -> bypass).
    if (!is_string($t) || $t === '' || $s === '' || !hash_equals($s, $t)) {
        http_response_code(419);
        exit('Sesi tidak valid (CSRF). Silakan muat ulang halaman.');
    }
}
/** Panggil di awal handler POST. */
function is_post(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { csrf_verify(); return true; }
    return false;
}

// ---------- Flash ----------
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flash_pull(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

// ---------- Auth & Policy ----------
const ROLE_LABELS = [
    'admin_inventaris' => 'Admin Inventaris',
    'sales'            => 'Sales Executive',
    'service_advisor'  => 'Service Advisor',
    'manager'          => 'Manajer Penjualan',
];
function auth_user(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string ...$roles): array
{
    $u = auth_user();
    if (!$u) { flash('warning', 'Silakan login terlebih dahulu.'); redirect('login.php'); }
    if ($roles && !in_array($u['role'], $roles, true)) { http_response_code(403); exit('403 - Anda tidak berhak mengakses halaman ini.'); }
    return $u;
}
function has_role(string ...$roles): bool { $u = auth_user(); return $u && in_array($u['role'], $roles, true); }

// ---------- Validasi umum ----------
const VIN_REGEX = '/^[A-HJ-NPR-Z0-9]{17}$/';
function valid_phone(string $p): bool { return (bool)preg_match('/^(\+62|62|0)8[0-9]{8,12}$/', $p); }
function valid_date(string $d): bool { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }

/** Label badge status. */
function badge(string $status): string
{
    $map = ['ready'=>'success','booked'=>'warning','delivered'=>'secondary','cold'=>'info','warm'=>'warning','hot'=>'danger',
            'won'=>'success','lost'=>'secondary','active'=>'primary','cancelled'=>'secondary','requested'=>'info','confirmed'=>'primary',
            'done'=>'success','in_progress'=>'warning'];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'light') . '">' . e(strtoupper(str_replace('_', ' ', $status))) . '</span>';
}
