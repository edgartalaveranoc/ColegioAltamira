<?php
declare(strict_types=1);
const DB_HOST = 'localhost';
const DB_NAME = 'colegio_altamira';
const DB_USER = 'root';
const DB_PASS = '';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function db(): PDO {
    static $pdo = null;
    return $pdo ??= new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Sesión expirada. Recarga la página e inténtalo de nuevo.'); }
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_role(string ...$roles): array {
    $u = user();
    if (!$u) { header('Location: login.php'); exit; }
    if (!in_array($u['rol'], $roles, true)) { http_response_code(403); exit('Acceso denegado.'); }
    return $u;
}
