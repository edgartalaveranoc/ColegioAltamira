<?php
require_once __DIR__ . '/comun.php';

$misAulas = db()->prepare('SELECT DISTINCT au.id, au.nombre, au.grado, au.nivel FROM asignaciones asg JOIN aulas au ON au.id = asg.aula_id WHERE asg.profesor_id = ? ORDER BY au.nombre');
$misAulas->execute([$u['id']]);
$misAulas = $misAulas->fetchAll();
$misCursoIds = [];
$rst = db()->prepare('SELECT DISTINCT curso_id FROM asignaciones WHERE profesor_id = ?');
$rst->execute([$u['id']]);
foreach ($rst->fetchAll() as $r) $misCursoIds[] = (int)$r['curso_id'];
$misCursos = $misCursoIds ? db()->query('SELECT id,nombre FROM cursos WHERE id IN (' . implode(',', $misCursoIds) . ') ORDER BY nombre')->fetchAll() : [];
$misAulaIds = array_column($misAulas, 'id');

$fAula = (int)($_GET['aula'] ?? 0);
if ($fAula && !in_array($fAula, array_map('intval', $misAulaIds), true)) $fAula = 0;
$q = trim($_GET['q'] ?? '');

$aviso = ''; $error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    try {
        $alumnoOk = function (int $id): bool {
            global $u;
            $st = db()->prepare('SELECT COUNT(*) FROM alumnos al JOIN asignaciones asg ON asg.aula_id = al.aula_id WHERE al.usuario_id = ? AND asg.profesor_id = ?');
            $st->execute([$id, $u['id']]);
            return (int)$st->fetchColumn() > 0;
        };
        if ($accion === 'nota') {
            $alumno = (int)($_POST['alumno_id'] ?? 0);
            $nota = (float)($_POST['nota'] ?? -1);
            $periodo = $_POST['periodo'] ?? 'Bimestre I';
            $tipo = $_POST['tipo'] ?? 'Continua';
            $peso = max(1, min(5, (int)($_POST['peso'] ?? 1)));
            if ($alumnoOk($alumno) && $nota >= 0 && $nota <= 20 && in_array($tipo, ['Continua', 'Parcial', 'Final'], true)) {
                db()->prepare('INSERT INTO notas (alumno_id, curso_id, profesor_id, periodo, tipo, nota, peso, fecha) VALUES (?,?,?,?,?,?,?,CURDATE())')
                    ->execute([$alumno, (int)($_POST['curso_id'] ?? 0), $u['id'], mb_substr($periodo, 0, 20), $tipo, $nota, $peso]);
                $aviso = 'Nota registrada.';
            } else $error = 'Revisa el alumno, el curso y la nota (0 a 20).';
        } elseif ($accion === 'editar_nota') {
            $nota = (float)($_POST['nota'] ?? -1);
            if ($nota >= 0 && $nota <= 20) {
                db()->prepare('UPDATE notas SET nota = ? WHERE id = ? AND profesor_id = ?')->execute([$nota, (int)($_POST['id'] ?? 0), $u['id']]);
                $aviso = 'Nota actualizada.';
            } else $error = 'La nota debe estar entre 0 y 20.';
        } elseif ($accion === 'borrar_nota') {
            db()->prepare('DELETE FROM notas WHERE id = ? AND profesor_id = ?')->execute([(int)($_POST['id'] ?? 0), $u['id']]);
            $aviso = 'Nota eliminada.';
        } elseif ($accion === 'revisar') {
            db()->prepare("UPDATE entregas e JOIN tareas t ON t.id = e.tarea_id SET e.estado = 'Calificada' WHERE e.id = ? AND t.profesor_id = ?")->execute([(int)($_POST['id'] ?? 0), $u['id']]);
            $aviso = 'Entrega marcada como revisada.';
        } elseif ($accion === 'asistencia') {
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $aulaAsist = (int)($_POST['aula_id'] ?? 0);
            $estados = $_POST['estado'] ?? [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && in_array($aulaAsist, array_map('intval', $misAulaIds), true) && is_array($estados)) {
                $st = db()->prepare('INSERT INTO asistencia (alumno_id, fecha, estado, registrado_por) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE estado = VALUES(estado)');
                $validos = ['Puntual', 'Tardanza', 'Falta justificada', 'Falta injustificada'];
                foreach ($estados as $alumnoId => $estado) {
                    if (in_array($estado, $validos, true) && $alumnoOk((int)$alumnoId)) $st->execute([(int)$alumnoId, $fecha, $estado, $u['id']]);
                }
                $aviso = 'Asistencia del ' . $fecha . ' guardada.';
                $_GET['fecha'] = $fecha;
                $_GET['aula'] = $aulaAsist;
            } else $error = 'Selecciona un aula y una fecha válidas.';
        } elseif ($accion === 'observacion') {
            $alumno = (int)($_POST['alumno_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'Conducta';
            $detalle = trim($_POST['detalle'] ?? '');
            if ($alumnoOk($alumno) && $detalle !== '' && in_array($tipo, ['Conducta', 'Reconocimiento'], true)) {
                db()->prepare('INSERT INTO observaciones (alumno_id, profesor_id, tipo, detalle, fecha) VALUES (?,?,?,?,CURDATE())')
                    ->execute([$alumno, $u['id'], $tipo, mb_substr($detalle, 0, 300)]);
                $aviso = 'Observación registrada.';
            } else $error = 'Completa el alumno y el detalle.';
        } elseif ($accion === 'tarea') {
            $aula = (int)($_POST['aula_id'] ?? 0);
            $curso = (int)($_POST['curso_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $limite = $_POST['fecha_limite'] ?? '';
            if (in_array($aula, array_map('intval', $misAulaIds), true) && $curso && $titulo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $limite)) {
                db()->prepare('INSERT INTO tareas (curso_id, aula_id, profesor_id, titulo, descripcion, recurso, fecha_limite) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$curso, $aula, $u['id'], mb_substr($titulo, 0, 160), mb_substr(trim($_POST['descripcion'] ?? ''), 0, 400), mb_substr(trim($_POST['recurso'] ?? ''), 0, 255), $limite]);
                $aviso = 'Tarea publicada.';
            } else $error = 'Completa los datos de la tarea.';
        }
    } catch (Throwable $ex) {
        $error = 'No se pudo completar la operación. Revisa los datos.';
    }
}

$horario = db()->prepare('SELECT h.dia, h.hora, c.nombre curso, au.nombre aula FROM horarios h JOIN cursos c ON c.id = h.curso_id JOIN aulas au ON au.id = h.aula_id WHERE h.profesor_id = ? ORDER BY FIELD(h.dia,\'Lunes\',\'Martes\',\'Miércoles\',\'Jueves\',\'Viernes\'), h.hora');
$horario->execute([$u['id']]);
$horario = $horario->fetchAll();

$sqlAlumnos = 'SELECT u.id, u.nombre, u.username, al.grado, al.nivel, au.nombre aula, au.id aula_id
               FROM alumnos al JOIN usuarios u ON u.id = al.usuario_id JOIN aulas au ON au.id = al.aula_id
               WHERE au.id IN (' . (implode(',', array_map('intval', $misAulaIds)) ?: '0') . ')';
$params = [];
if ($fAula) { $sqlAlumnos .= ' AND au.id = ?'; $params[] = $fAula; }
if ($q !== '') { $sqlAlumnos .= ' AND (u.nombre LIKE ? OR u.username LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sqlAlumnos .= ' ORDER BY au.nombre, u.nombre LIMIT 80';
$st = db()->prepare($sqlAlumnos); $st->execute($params); $alumnos = $st->fetchAll();
$totalAlumnos = count($alumnos);

$aulaAsist = $fAula ?: (int)($misAulaIds[0] ?? 0);
$fFecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFecha)) $fFecha = date('Y-m-d');
$alumnosAsist = [];
if ($aulaAsist) {
    $st = db()->prepare('SELECT u.id, u.nombre FROM alumnos al JOIN usuarios u ON u.id = al.usuario_id WHERE al.aula_id = ? ORDER BY u.nombre');
    $st->execute([$aulaAsist]);
    $alumnosAsist = $st->fetchAll();
}
$asistHoy = [];
$st = db()->prepare("SELECT alumno_id, estado FROM asistencia WHERE fecha = ? AND alumno_id IN (SELECT al.usuario_id FROM alumnos al JOIN asignaciones asg ON asg.aula_id = al.aula_id WHERE asg.profesor_id = ?)");
$st->execute([$fFecha, $u['id']]);
foreach ($st->fetchAll() as $a) $asistHoy[(int)$a['alumno_id']] = $a['estado'];

// Estadísticas de asistencia para gráica
$estadosCount = ['Puntual' => 0, 'Tardanza' => 0, 'Falta justificada' => 0, 'Falta injustificada' => 0];
foreach ($asistHoy as $estado) { if (isset($estadosCount[$estado])) $estadosCount[$estado]++; }

$notas = db()->prepare('SELECT n.id, n.nota, n.periodo, n.tipo, n.fecha, u.nombre alumno, c.nombre curso FROM notas n JOIN usuarios u ON u.id = n.alumno_id JOIN cursos c ON c.id = n.curso_id WHERE n.profesor_id = ? ORDER BY n.fecha DESC, n.id DESC LIMIT 12');
$notas->execute([$u['id']]);
$notas = $notas->fetchAll();
$numNotas = (int)db()->query('SELECT COUNT(*) FROM notas WHERE profesor_id = ' . (int)$u['id'])->fetchColumn();

$tareas = db()->prepare('SELECT t.*, c.nombre curso, au.nombre aula, (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id) entregas FROM tareas t JOIN cursos c ON c.id = t.curso_id JOIN aulas au ON au.id = t.aula_id WHERE t.profesor_id = ? ORDER BY t.fecha_limite LIMIT 10');
$tareas->execute([$u['id']]);
$tareas = $tareas->fetchAll();
$tareasActivas = 0;
foreach ($tareas as $t) if ($t['fecha_limite'] >= date('Y-m-d')) $tareasActivas++;

$observaciones = db()->prepare('SELECT o.*, u.nombre alumno FROM observaciones o JOIN usuarios u ON u.id = o.alumno_id WHERE o.profesor_id = ? ORDER BY o.fecha DESC, o.id DESC LIMIT 8');
$observaciones->execute([$u['id']]);
$observaciones = $observaciones->fetchAll();

$entregas = db()->prepare("SELECT e.id, e.comentario, e.entregado, u.nombre alumno, t.titulo, c.nombre curso FROM entregas e JOIN usuarios u ON u.id = e.alumno_id JOIN tareas t ON t.id = e.tarea_id JOIN cursos c ON c.id = t.curso_id WHERE t.profesor_id = ? AND e.estado = 'Enviada' ORDER BY e.entregado DESC LIMIT 10");
$entregas->execute([$u['id']]);
$entregas = $entregas->fetchAll();

$promCurso = [];
foreach ($misCursos as $c) {
    $st = db()->prepare('SELECT ROUND(AVG(nota),1) p FROM notas WHERE profesor_id = ? AND curso_id = ?');
    $st->execute([$u['id'], (int)$c['id']]);
    $promCurso[$c['nombre']] = (float)$st->fetchColumn();
}

dash_start($u, 'Panel docente', 'Año escolar 2026 · Gestión pedagógica, evaluación y seguimiento de tus aulas', [['#resumen', 'Resumen'], ['#alumnos', 'Alumnado'], ['#notas', 'Notas'], ['#asistencia', 'Asistencia'], ['#tareas', 'Tareas']]);
if ($aviso) alerta('ok', $aviso);
if ($error) alerta('err', $error);
?>
<section class="kpis" id="resumen">
  <div class="kpi"><strong><?= count($misAulas) ?></strong><span>Aulas asignadas</span></div>
  <div class="kpi"><strong><?= $totalAlumnos ?></strong><span>Alumnos en la vista</span></div>
  <div class="kpi"><strong><?= $numNotas ?></strong><span>Notas registradas</span></div>
  <div class="kpi"><strong><?= $tareasActivas ?></strong><span>Tareas activas</span></div>
</section>

<section class="panel">
  <h2>Horario semanal</h2>
  <?php if (!$horario): ?><p class="muted">Aún no tienes cursos asignados. La dirección académica puede asignarte cursos y aulas.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Día</th><th>Hora</th><th>Curso</th><th>Aula</th></tr>
    <?php foreach ($horario as $h): ?><tr><td><?= e($h['dia']) ?></td><td><?= e(substr($h['hora'], 0, 5)) ?></td><td><?= e($h['curso']) ?></td><td><?= e($h['aula']) ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Promedio por curso</h2>
  <?php $hayProm = false; foreach ($promCurso as $cn => $pv) if ($pv > 0) $hayProm = true; ?>
  <?php if (!$hayProm): ?>
  <p class="muted">Aún no hay notas registradas.</p>
  <?php else: ?>
  <div class="chart-wrap">
    <canvas id="graficaNotas" aria-label="Gráfica de promedios por curso" role="img"></canvas>
  </div>
  <div class="chart-legend">
    <?php $colores = ['#0E7C66', '#3D8FB0', '#F2B33D', '#E0715A', '#0A2E3A', '#58A8C6'];
      $i = 0; foreach ($promCurso as $cn => $pv): if ($pv <= 0) continue; ?>
    <span><i style="background:<?= $colores[$i % count($colores)] ?>"></i><?= e($cn) ?>: <?= e($pv) ?></span>
    <?php $i++; endforeach; ?>
  </div>
  <?php endif; ?>
  <script>
  <?php if ($hayProm): ?>
  const ctx = document.getElementById('graficaNotas').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys(array_filter($promCurso, fn($v) => $v > 0))) ?>,
      datasets: [{
        label: 'Promedio general (20)',
        data: <?= json_encode(array_values(array_filter($promCurso, fn($v) => $v > 0))) ?>,
        backgroundColor: <?= json_encode(array_map(function($i) {
          $colors = ['#3CB371', '#4682B4', '#DAA520', '#CD853F', '#2F4F4F', '#708090'];
          return $colors[$i % count($colors)];
        }, range(0, count(array_filter($promCurso, fn($v) => $v > 0)) - 1))) ?>,
        borderColor: '#fff',
        borderWidth: 1,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { 
          beginAtZero: true, 
          max: 20, 
          grid: { color: 'rgba(0,0,0,0.08)' }, 
          ticks: { stepSize: 5, color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() }
        },
        x: { grid: { display: false }, ticks: { color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() } }
      },
      title: { display: true, text: 'Promedio por curso', color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim(), font: { size: 13 } }
    }
  });
  <?php endif; ?>
  </script>
  <div class="chart-legend">
    <?php $colores = ['#0E7C66', '#3D8FB0', '#F2B33D', '#E0715A', '#0A2E3A', '#58A8C6'];
      $i = 0; foreach ($promCurso as $cn => $pv): if ($pv <= 0) continue; ?>
    <span><i style="background:<?= $colores[$i % count($colores)] ?>"></i><?= e($cn) ?>: <?= e($pv) ?></span>
    <?php $i++; endforeach; ?>
  </div>
</section>

<section class="panel" id="alumnos">
  <div class="panel-head">
    <h2>Alumnado de tus aulas</h2>
    <form method="get" class="inline-form">
      <select name="aula" aria-label="Filtrar por aula">
        <option value="0">Todas mis aulas</option>
        <?php foreach ($misAulas as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $fAula === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['nombre']) ?> · <?= e($a['grado']) ?></option><?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o DNI" aria-label="Buscar alumno">
      <button class="btn btn-line btn-sm">Filtrar</button>
    </form>
  </div>
  <?php if (!$alumnos): ?><p class="muted">No hay alumnos que coincidan con el filtro.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Estudiante</th><th>Usuario (DNI)</th><th>Grado</th><th>Nivel</th><th>Aula</th></tr>
    <?php foreach ($alumnos as $a): ?>
    <tr><td><?= e($a['nombre']) ?></td><td><?= e($a['username']) ?></td><td><?= e($a['grado']) ?></td><td><?= e($a['nivel']) ?></td><td><?= e($a['aula']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="notas">
  <h2>Cuaderno de notas digital</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="nota">
    <label>Alumno<select name="alumno_id" required>
      <?php foreach ($alumnos as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?> · <?= e($a['aula']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Curso<select name="curso_id" required><?php foreach ($misCursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Periodo<select name="periodo"><option>Bimestre I</option><option>Bimestre II</option><option>Bimestre III</option><option>Trimestre I</option><option>Trimestre II</option></select></label>
    <label>Tipo<select name="tipo"><option>Continua</option><option>Parcial</option><option>Final</option></select></label>
    <label>Nota (0–20)<input type="number" name="nota" min="0" max="20" step="0.5" required></label>
    <label>Peso<input type="number" name="peso" min="1" max="5" value="1"></label>
    <button class="btn btn-bus btn-sm">Registrar nota</button>
  </form>
  <?php if (!$notas): ?><p class="muted" style="margin-top:1rem">Aún no registraste notas.</p><?php else: ?>
  <div class="table-wrap" style="margin-top:1rem"><table>
    <tr><th>Fecha</th><th>Alumno</th><th>Curso</th><th>Periodo</th><th>Tipo</th><th>Nota</th><th></th></tr>
    <?php foreach ($notas as $n): ?>
    <tr><td><?= e($n['fecha']) ?></td><td><?= e($n['alumno']) ?></td><td><?= e($n['curso']) ?></td><td><?= e($n['periodo']) ?></td><td><?= e($n['tipo']) ?></td>
        <td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="editar_nota"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="number" name="nota" value="<?= e($n['nota']) ?>" min="0" max="20" step="0.5" class="mini-input"><button class="btn btn-line btn-sm">Guardar</button></form></td>
        <td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="borrar_nota"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-line btn-sm">Eliminar</button></form></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="asistencia">
  <h2>Asistencia y conducta</h2>
  <h3>Registro diario de asistencia</h3>
  <form method="get" class="form-inline">
    <label>Aula<select name="aula" required>
      <?php foreach ($misAulas as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $aulaAsist === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['nombre']) ?> · <?= e($a['grado']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Fecha<input type="date" name="fecha" value="<?= e($fFecha) ?>" required></label>
    <button class="btn btn-line btn-sm">Ver lista</button>
  </form>
  <?php if ($alumnosAsist): ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="asistencia">
    <input type="hidden" name="aula_id" value="<?= (int)$aulaAsist ?>"><input type="hidden" name="fecha" value="<?= e($fFecha) ?>">
    <div class="table-wrap"><table>
      <tr><th>Alumno</th><th>Estado</th></tr>
      <?php foreach ($alumnosAsist as $a): ?>
      <tr><td><?= e($a['nombre']) ?></td>
          <td><select name="estado[<?= (int)$a['id'] ?>]" class="mini-select">
            <?php foreach (['Puntual', 'Tardanza', 'Falta justificada', 'Falta injustificada'] as $es): ?>
            <option<?= ($asistHoy[(int)$a['id']] ?? '') === $es ? ' selected' : '' ?>><?= $es ?></option>
            <?php endforeach; ?>
          </select></td></tr>
      <?php endforeach; ?>
    </table></div>
    <button class="btn btn-bus btn-sm" style="margin-top:.8rem">Guardar asistencia del <?= e($fFecha) ?></button>
  </form>
  <?php else: ?><p class="muted">Selecciona un aula con alumnos.</p><?php endif; ?>
  <?php if ($alumnosAsist): ?>
  <div class="chart-wrap">
    <canvas id="graficaAsistencia" aria-label="Gráfica de asistencia del día" role="img"></canvas>
  </div>
  <div class="chart-legend">
    <span><i style="background:#38bdf8"></i>Puntual</span>
    <span><i style="background:#f6a823"></i>Tardanza</span>
    <span><i style="background:#2d6a4f"></i>Falta justificada</span>
    <span><i style="background:#e0715a"></i>Falta injustificada</span>
  </div>
  <script>
    const ctx = document.getElementById('graficaAsistencia').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Puntual', 'Tardanza', 'Falta justificada', 'Falta injustificada'],
        datasets: [{
          data: [<?= $estadosCount['Puntual'] ?>, <?= $estadosCount['Tardanza'] ?>, <?= $estadosCount['Falta justificada'] ?>, <?= $estadosCount['Falta injustificada'] ?>],
          backgroundColor: ['#2E7D32', '#F9A825', '#5D4037', '#C62828'],
          borderWidth: 3,
          borderColor: '#fff',
          hoverBorderColor: '#fff',
          hoverOffset: 12
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { 
          legend: { display: false },
          tooltip: { 
            callbacks: {
              label: function(context) {
                return context.label + ': ' + context.parsed + ' registros';
              }
            }
          }
        },
        cutout: '60%'
      }
    });
  </script>
  <?php endif; ?>
  <h3 style="margin-top:1.4rem">Nueva observación</h3>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="observacion">
    <label>Alumno<select name="alumno_id" required><?php foreach ($alumnosAsist as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Tipo<select name="tipo"><option>Conducta</option><option>Reconocimiento</option></select></label>
    <label>Detalle<input name="detalle" required maxlength="300"></label>
    <button class="btn btn-bus btn-sm">Registrar</button>
  </form>
  <?php if ($observaciones): ?>
  <div class="table-wrap" style="margin-top:1rem"><table>
    <tr><th>Fecha</th><th>Alumno</th><th>Tipo</th><th>Detalle</th></tr>
    <?php foreach ($observaciones as $o): ?><tr><td><?= e($o['fecha']) ?></td><td><?= e($o['alumno']) ?></td><td><?= e($o['tipo']) ?></td><td><?= e($o['detalle']) ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Entregas por revisar</h2>
  <?php if (!$entregas): ?><p class="muted">No hay entregas pendientes de revisión.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Alumno</th><th>Curso</th><th>Tarea</th><th>Entrega</th><th>Comentario</th><th></th></tr>
    <?php foreach ($entregas as $e): ?>
    <tr><td><?= e($e['alumno']) ?></td><td><?= e($e['curso']) ?></td><td><?= e($e['titulo']) ?></td><td><?= e($e['entregado']) ?></td><td><?= e($e['comentario'] ?? '—') ?></td>
        <td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="revisar"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="btn btn-bus btn-sm">Revisada</button></form></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="tareas">
  <h2>Tareas y materiales</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="tarea">
    <label>Aula<select name="aula_id" required><?php foreach ($misAulas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?> · <?= e($a['grado']) ?></option><?php endforeach; ?></select></label>
    <label>Curso<select name="curso_id" required><?php foreach ($misCursos as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?></select></label>
    <label>Título<input name="titulo" required maxlength="160"></label>
    <label>Entrega<input type="date" name="fecha_limite" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>" required></label>
    <label>Recurso<input name="recurso" placeholder="PDF o enlace" maxlength="255"></label>
    <label>Descripción<input name="descripcion" maxlength="400"></label>
    <button class="btn btn-bus btn-sm">Publicar tarea</button>
  </form>
  <?php if ($tareas): ?>
  <div class="table-wrap" style="margin-top:1rem"><table>
    <tr><th>Título</th><th>Curso</th><th>Aula</th><th>Límite</th><th>Entregas</th></tr>
    <?php foreach ($tareas as $t): ?><tr><td><?= e($t['titulo']) ?></td><td><?= e($t['curso']) ?></td><td><?= e($t['aula']) ?></td><td><?= e($t['fecha_limite']) ?></td><td><?= (int)$t['entregas'] ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php dash_end(); ?>
