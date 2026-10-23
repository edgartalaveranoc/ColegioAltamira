<?php
require_once __DIR__ . '/comun.php';

$aviso = ''; $error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear_usuario') {
            $nombre = trim($_POST['nombre'] ?? '');
            $username = strtolower(preg_replace('/\s+/', '', $_POST['username'] ?? ''));
            $rol = $_POST['rol'] ?? 'alumno';
            $clave = trim($_POST['clave'] ?? '') ?: $username;
            if ($nombre === '' || $username === '' || !in_array($rol, ['admin', 'profesor', 'alumno', 'familia', 'personal'], true)) throw new RuntimeException('Datos incompletos.');
            db()->prepare('INSERT INTO usuarios (nombre, username, email, password_hash, rol) VALUES (?,?,?,?,?)')
                ->execute([$nombre, $username, $username . '@altamira.edu', password_hash($clave, PASSWORD_DEFAULT), $rol]);
            $aviso = 'Usuario creado correctamente.';
        } elseif ($accion === 'cambiar_rol') {
            $id = (int)($_POST['id'] ?? 0); $rol = $_POST['rol'] ?? '';
            if ($id && in_array($rol, ['admin', 'profesor', 'alumno', 'familia', 'personal'], true)) {
                db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
                $aviso = 'Rol actualizado.';
            }
        } elseif ($accion === 'alternar') {
            db()->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            $aviso = 'Estado de la cuenta actualizado.';
        } elseif ($accion === 'asignar') {
            $p = (int)($_POST['profesor_id'] ?? 0); $c = (int)($_POST['curso_id'] ?? 0); $a = (int)($_POST['aula_id'] ?? 0);
            if ($p && $c && $a) {
                db()->prepare('INSERT IGNORE INTO asignaciones (profesor_id, curso_id, aula_id) VALUES (?,?,?)')->execute([$p, $c, $a]);
                $aviso = 'Asignación docente registrada.';
            }
        } elseif ($accion === 'matricular') {
            $nombre = trim($_POST['nombre'] ?? '');
            $dni = preg_replace('/\D/', '', $_POST['dni'] ?? '');
            $aulaId = (int)($_POST['aula_id'] ?? 0);
            $padreId = (int)($_POST['padre_id'] ?? 0) ?: null;
            if ($nombre === '' || strlen($dni) < 8 || !$aulaId) throw new RuntimeException('Datos incompletos.');
            $aula = db()->prepare('SELECT grado, nivel FROM aulas WHERE id = ?');
            $aula->execute([$aulaId]);
            $a = $aula->fetch();
            db()->prepare('INSERT INTO usuarios (nombre, username, email, password_hash, rol) VALUES (?,?,?,?,?)')
                ->execute([$nombre, $dni, $dni . '@altamira.edu', password_hash($dni, PASSWORD_DEFAULT), 'alumno']);
            db()->prepare('INSERT INTO alumnos (usuario_id, grado, nivel, aula_id, padre_id) VALUES (?,?,?,?,?)')
                ->execute([(int)db()->lastInsertId(), $a['grado'], $a['nivel'], $aulaId, $padreId]);
            $aviso = 'Matrícula registrada. Usuario y clave: ' . $dni . '.';
        } elseif ($accion === 'publicar') {
            $t = trim($_POST['titulo'] ?? ''); $r = trim($_POST['resumen'] ?? ''); $c = $_POST['categoria'] ?? 'Aviso';
            if ($t !== '' && $r !== '' && in_array($c, ['Aviso', 'Noticia', 'Evento'], true)) {
                db()->prepare('INSERT INTO noticias (titulo,resumen,categoria,autor_id) VALUES (?,?,?,?)')->execute([mb_substr($t, 0, 160), mb_substr($r, 0, 300), $c, $u['id']]);
                $aviso = 'Comunicado publicado.';
            } else $error = 'Completa el título y el resumen.';
        } elseif ($accion === 'incidencia') {
            $esp = trim($_POST['espacio'] ?? ''); $desc = trim($_POST['descripcion'] ?? '');
            if ($esp !== '' && $desc !== '') {
                db()->prepare("INSERT INTO incidencias (espacio,descripcion,estado,reportado_por,fecha) VALUES (?,?,'Abierta',?,CURDATE())")->execute([$esp, $desc, $u['id']]);
                $aviso = 'Incidencia reportada.';
            }
        } elseif ($accion === 'resolver') {
            db()->prepare("UPDATE incidencias SET estado = 'Resuelta' WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
            $aviso = 'Incidencia marcada como resuelta.';
        }
    } catch (Throwable $ex) {
        $error = 'No se pudo completar la operación. Revisa los datos (¿usuario o DNI duplicado?).';
    }
}

$conteos = db()->query('SELECT rol, COUNT(*) c FROM usuarios WHERE activo = 1 GROUP BY rol')->fetchAll(PDO::FETCH_KEY_PAIR);
$totalAulas = (int)db()->query('SELECT COUNT(*) FROM aulas')->fetchColumn();

$asistHoy = db()->query('SELECT estado, COUNT(*) c FROM asistencia WHERE fecha = CURDATE() GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);
$totalAsist = array_sum($asistHoy);
$pctAsist = $totalAsist ? (int)round((($asistHoy['Puntual'] ?? 0) + ($asistHoy['Tardanza'] ?? 0)) * 100 / $totalAsist) : null;

$pagosEstado = [];
foreach (db()->query('SELECT estado, COUNT(*) c, COALESCE(SUM(monto),0) m FROM pagos GROUP BY estado')->fetchAll() as $p) $pagosEstado[$p['estado']] = $p;
$totalPagos = array_sum(array_column($pagosEstado, 'c'));
$pctPagado = $totalPagos ? (int)round(($pagosEstado['Pagado']['c'] ?? 0) * 100 / $totalPagos) : 0;

$incAbiertas = (int)db()->query("SELECT COUNT(*) FROM incidencias WHERE estado <> 'Resuelta'")->fetchColumn();
$porGrado = db()->query("SELECT al.grado g, ROUND(AVG(n.nota),1) p FROM notas n JOIN alumnos al ON al.usuario_id = n.alumno_id GROUP BY al.nivel, al.grado ORDER BY FIELD(al.nivel,'Primaria','Secundaria'), al.grado")->fetchAll();
$maxProm = 20.0;

$usuarios = db()->query('SELECT id,nombre,username,rol,activo FROM usuarios ORDER BY FIELD(rol,\'admin\',\'personal\',\'profesor\',\'alumno\',\'familia\'), nombre')->fetchAll();
$aulasList = db()->query('SELECT id,nombre,grado,nivel FROM aulas ORDER BY nombre')->fetchAll();
$cursos = db()->query('SELECT id,nombre FROM cursos ORDER BY nombre')->fetchAll();
$profesores = db()->query("SELECT id,nombre FROM usuarios WHERE rol = 'profesor' AND activo = 1 ORDER BY nombre")->fetchAll();
$padres = db()->query("SELECT id,nombre FROM usuarios WHERE rol = 'familia' AND activo = 1 ORDER BY nombre")->fetchAll();
$asignaciones = db()->query('SELECT asg.id, p.nombre prof, c.nombre curso, au.nombre aula FROM asignaciones asg JOIN usuarios p ON p.id = asg.profesor_id JOIN cursos c ON c.id = asg.curso_id JOIN aulas au ON au.id = asg.aula_id ORDER BY au.nombre, c.nombre')->fetchAll();
$mensajes = db()->query('SELECT * FROM mensajes ORDER BY creado DESC LIMIT 8')->fetchAll();
$incidencias = db()->query('SELECT i.*, u.nombre rep FROM incidencias i LEFT JOIN usuarios u ON u.id = i.reportado_por ORDER BY i.fecha DESC, i.id DESC LIMIT 10')->fetchAll();
$personal = db()->query("SELECT u.nombre, u.rol, ap.estado FROM usuarios u LEFT JOIN asistencia_personal ap ON ap.usuario_id = u.id AND ap.fecha = CURDATE() WHERE u.rol IN ('profesor','personal') AND u.activo = 1 ORDER BY u.nombre LIMIT 14")->fetchAll();

dash_start($u, $u['rol'] === 'admin' ? 'Administración general' : 'Personal académico', 'Año escolar 2026 · Gestión global, comunidad y operaciones del colegio', [['#resumen', 'Resumen'], ['#usuarios', 'Usuarios'], ['#academico', 'Académico'], ['#admisiones', 'Admisiones'], ['#comunicados', 'Comunicados'], ['#operaciones', 'Operaciones']]);
if ($aviso) alerta('ok', $aviso);
if ($error) alerta('err', $error);
?>

<section class="kpis" id="resumen">
  <div class="kpi"><strong><?= (int)($conteos['alumno'] ?? 0) ?></strong><span>Alumnos matriculados</span></div>
  <div class="kpi"><strong><?= (int)($conteos['profesor'] ?? 0) ?></strong><span>Docentes activos</span></div>
  <div class="kpi"><strong><?= $pctAsist === null ? '—' : $pctAsist . '%' ?></strong><span>Asistencia general hoy</span></div>
  <div class="kpi"><strong><?= $pctPagado ?>%</strong><span>Pensiones al día</span></div>
  <div class="kpi"><strong><?= $totalAulas ?></strong><span>Aulas en operación</span></div>
  <div class="kpi"><strong><?= $incAbiertas ?></strong><span>Incidencias abiertas</span></div>
</section>

<section class="panel">
  <h2>Rendimiento académico por grado</h2>
  <?php if (!$porGrado): ?><p class="muted">Aún no hay notas registradas.</p><?php else: foreach ($porGrado as $g): ?>
  <div class="bar-row">
    <span><?= e($g['g']) ?></span>
    <div class="bar-track"><div class="bar-fill" style="width:<?= max(4, (int)round((float)$g['p'] * 100 / $maxProm)) ?>%"></div></div>
    <strong><?= e($g['p']) ?></strong>
  </div>
  <?php endforeach; endif; ?>
  <p class="mini muted">Promedio general sobre 20, calculado con las evaluaciones registradas.</p>
</section>

<section class="panel" id="usuarios">
  <h2>Gestión de usuarios y permisos</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="crear_usuario">
    <label>Nombre completo<input name="nombre" required maxlength="100"></label>
    <label>Usuario / DNI<input name="username" required maxlength="60"></label>
    <label>Rol<select name="rol"><option value="alumno">Alumno</option><option value="profesor">Profesor</option><option value="familia">Padre de familia</option><option value="personal">Personal académico</option><option value="admin">Administrador</option></select></label>
    <label>Contraseña<input name="clave" placeholder="Igual al usuario" maxlength="60"></label>
    <button class="btn btn-bus btn-sm">Crear cuenta</button>
  </form>
  <div class="table-wrap" style="margin-top:1rem;max-height:420px;overflow:auto">
    <table>
      <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
      <?php foreach ($usuarios as $x): ?>
      <tr>
        <td><?= e($x['nombre']) ?></td><td><?= e($x['username']) ?></td>
        <td>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="cambiar_rol"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
            <select name="rol" class="mini-select">
              <?php foreach (dash_roles() as $rk => $rl): ?><option value="<?= e($rk) ?>"<?= $x['rol'] === $rk ? ' selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-line btn-sm">Guardar</button>
          </form>
        </td>
        <td><span class="badge <?= $x['activo'] ? 'ok' : 'bad' ?>"><?= $x['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
        <td>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="alternar"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
            <button class="btn btn-line btn-sm"><?= $x['activo'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<section class="panel" id="academico">
  <h2>Asignación de docentes</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="asignar">
    <label>Profesor<select name="profesor_id" required><?php foreach ($profesores as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Curso<select name="curso_id" required><?php foreach ($cursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Aula<select name="aula_id" required><?php foreach ($aulasList as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?> · <?= e($a['grado']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-bus btn-sm">Asignar</button>
  </form>
  <div class="table-wrap" style="margin-top:1rem">
    <table>
      <tr><th>Aula</th><th>Curso</th><th>Docente</th></tr>
      <?php foreach ($asignaciones as $a): ?><tr><td><?= e($a['aula']) ?></td><td><?= e($a['curso']) ?></td><td><?= e($a['prof']) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
</section>

<section class="panel" id="admisiones">
  <h2>Admisiones y matrículas</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="matricular">
    <label>Postulante<input name="nombre" required maxlength="100"></label>
    <label>DNI<input name="dni" required inputmode="numeric" maxlength="8" minlength="8"></label>
    <label>Aula<select name="aula_id" required><?php foreach ($aulasList as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['aula'] ?? $a['nombre']) ?> · <?= e($a['grado']) ?> (<?= e($a['nivel']) ?>)</option><?php endforeach; ?></select></label>
    <label>Apoderado<select name="padre_id"><option value="0">Sin registrar</option><?php foreach ($padres as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-bus btn-sm">Matricular</button>
  </form>
  <h3 style="margin-top:1.4rem">Consultas recibidas</h3>
  <?php if (!$mensajes): ?><p class="muted">Aún no hay consultas en el formulario de contacto.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Fecha</th><th>Nombre</th><th>Motivo</th><th>Contacto</th><th>Mensaje</th></tr>
    <?php foreach ($mensajes as $m): ?>
    <tr><td><?= e(substr($m['creado'], 0, 10)) ?></td><td><?= e($m['nombre']) ?></td><td><?= e($m['motivo']) ?></td>
        <td><?= e($m['email']) ?><br><?= e($m['telefono']) ?></td><td><?= e($m['mensaje']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="comunicados">
  <h2>Comunicados oficiales</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="publicar">
    <label>Título<input name="titulo" maxlength="160" required></label>
    <label>Resumen<textarea name="resumen" maxlength="300" rows="2" required></textarea></label>
    <label>Tipo<select name="categoria"><option>Aviso</option><option>Noticia</option><option>Evento</option></select></label>
    <button class="btn btn-bus btn-sm">Publicar comunicado</button>
  </form>
  <ul class="feed" style="margin-top:1rem">
    <?php foreach (db()->query('SELECT titulo,categoria,publicado FROM noticias ORDER BY publicado DESC LIMIT 5') as $n): ?>
    <li><span class="tag"><?= e($n['categoria']) ?></span> <strong><?= e($n['titulo']) ?></strong><br><span class="muted"><?= e(substr($n['publicado'], 0, 10)) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="panel" id="operaciones">
  <h2>Recursos humanos e infraestructura</h2>
  <div class="grid-2">
    <div>
      <h3>Asistencia del personal hoy</h3>
      <div class="table-wrap"><table>
        <tr><th>Nombre</th><th>Rol</th><th>Estado</th></tr>
        <?php foreach ($personal as $p): ?>
        <tr><td><?= e($p['nombre']) ?></td><td><?= e(dash_roles()[$p['rol']] ?? $p['rol']) ?></td>
            <td><span class="badge <?= $p['estado'] === 'Presente' ? 'ok' : ($p['estado'] ? 'warn' : '') ?>"><?= e($p['estado'] ?? 'Sin registro') ?></span></td></tr>
        <?php endforeach; ?>
      </table></div>
    </div>
    <div>
      <h3>Reportar incidencia</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="incidencia">
        <label>Espacio<input name="espacio" placeholder="Aula, laboratorio, patio…" required maxlength="80"></label>
        <label>Descripción<textarea name="descripcion" rows="2" required maxlength="300"></textarea></label>
        <button class="btn btn-bus btn-sm">Reportar</button>
      </form>
    </div>
  </div>
  <h3 style="margin-top:1.4rem">Incidencias registradas</h3>
  <div class="table-wrap"><table>
    <tr><th>Fecha</th><th>Espacio</th><th>Descripción</th><th>Estado</th><th></th></tr>
    <?php foreach ($incidencias as $i): ?>
    <tr><td><?= e($i['fecha']) ?></td><td><?= e($i['espacio']) ?></td><td><?= e($i['descripcion']) ?></td>
        <td><span class="badge <?= $i['estado'] === 'Resuelta' ? 'ok' : 'warn' ?>"><?= e($i['estado']) ?></span></td>
        <td><?php if ($i['estado'] !== 'Resuelta'): ?>
          <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-line btn-sm">Resolver</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>
<?php dash_end(); ?>
