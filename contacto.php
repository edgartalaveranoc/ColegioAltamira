<?php
require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['web'])) { header('Location: index.html'); exit; }
$n = trim($_POST['nombre'] ?? ''); $m = trim($_POST['email'] ?? ''); $t = trim($_POST['telefono'] ?? '');
$mo = $_POST['motivo'] ?? ''; $ms = trim($_POST['mensaje'] ?? '');
if ($n && filter_var($m, FILTER_VALIDATE_EMAIL) && $ms && in_array($mo, ['Admisión', 'Consulta general', 'Visita guiada'], true)) {
    db()->prepare('INSERT INTO mensajes (nombre,email,telefono,motivo,mensaje) VALUES (?,?,?,?,?)')
        ->execute([mb_substr($n, 0, 100), $m, mb_substr($t, 0, 30), $mo, mb_substr($ms, 0, 2000)]);
    header('Location: index.html?ok=1#contacto');
} else {
    header('Location: index.html?error=1#contacto');
}
