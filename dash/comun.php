<?php
// Cabecera y pie compartidos por los dashboards por rol.
function dash_roles(): array {
    return ['admin' => 'Administración', 'profesor' => 'Panel docente', 'alumno' => 'Portal del alumno', 'familia' => 'Portal de familias', 'personal' => 'Personal académico'];
}

function dash_start(array $u, string $titulo, string $sub, array $nav = []): void {
    $roles = dash_roles();
    ?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($titulo) ?> · Colegio Altamira</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>try{var t=localStorage.getItem('tema');if(!t)t=matchMedia('(prefers-color-scheme:dark)').matches?'oscuro':'claro';document.documentElement.setAttribute('data-tema',t)}catch(e){}</script>
</head><body class="dash">
<header class="dash-top">
  <a class="brand" href="index.html"><span class="brand-mark">A</span> Colegio Altamira</a>
  <?php if ($nav): ?><nav class="dash-nav"><?php foreach ($nav as [$href, $label]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>
  <span class="dash-user"><?= e($u['nombre']) ?> · <?= e($roles[$u['rol']] ?? 'Portal') ?></span>
  <button class="theme-btn" type="button" aria-label="Cambiar a modo oscuro">🌙</button>
  <a class="btn btn-line" href="logout.php">Cerrar sesión</a>
</header>
<main class="dash-main">
  <div class="page-head"><div><h1><?= e($titulo) ?></h1><p><?= e($sub) ?></p></div></div>
<?php }

function dash_end(): void { ?>
</main>
<footer class="foot"><span>© 2026 Colegio Altamira · Av. Los Educadores 450, Lima</span><span>(01) 555-0140 · informes@altamira.edu</span></footer>
<script src="assets/js/main.js"></script>
</body></html>
<?php }

function alerta(string $tipo, string $texto): void {
    echo '<p class="alert' . ($tipo === 'ok' ? ' ok' : '') . '" role="status">' . e($texto) . '</p>';
}
