<?php
require __DIR__ . '/config.php';
$ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
function login_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if (user()) {
    if ($ajax) login_json(['ok' => true, 'redirect' => 'panel.php']);
    header('Location: panel.php'); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        if ($ajax) login_json(['ok' => false, 'error' => 'Sesión expirada. Cierra el aviso y vuelve a intentarlo.'], 419);
        csrf_check();
    }
    $st = db()->prepare('SELECT * FROM usuarios WHERE username = ? AND activo = 1');
    $st->execute([strtolower(trim($_POST['usuario'] ?? ''))]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
        unset($_SESSION['intentos'], $_SESSION['bloqueo']);
        if ($ajax) login_json(['ok' => true, 'redirect' => 'panel.php']);
        header('Location: panel.php'); exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
    if ($ajax) login_json(['ok' => false, 'error' => $error], 422);
}
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar · Colegio Altamira</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script>try{var t=localStorage.getItem('tema');if(!t)t=matchMedia('(prefers-color-scheme:dark)').matches?'oscuro':'claro';document.documentElement.setAttribute('data-tema',t)}catch(e){}</script>
</head><body class="auth">
<main class="auth-box">
  <button class="theme-btn" type="button" aria-label="Cambiar a modo oscuro">🌙</button>
  <a class="brand" href="index.html"><span class="brand-mark">A</span> Colegio Altamira</a>
  <h1>Ingresa a tu portal</h1>
  <p class="muted">Alumnos, profesores, familias, personal académico y administración.</p>
  <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Usuario<input type="text" name="usuario" autocomplete="username" required></label>
    <label>Contraseña<input type="password" name="password" id="pw" autocomplete="current-password" required></label>
    <label class="check"><input type="checkbox" id="showpw"> Mostrar contraseña</label>
    <button class="btn btn-bus" type="submit">Ingresar</button>
  </form>
  <a class="muted" href="index.html">Volver al sitio</a>
</main>
<script src="assets/js/main.js"></script>
</body></html>
