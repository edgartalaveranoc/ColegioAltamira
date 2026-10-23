<?php
require_once __DIR__ . '/comun.php';

$st = db()->prepare('SELECT al.usuario_id, al.grado, al.nivel, al.aula_id, au.nombre aula, au.piso, t.nombre tutor FROM alumnos al LEFT JOIN aulas au ON au.id = al.aula_id LEFT JOIN usuarios t ON t.id = au.tutor_id WHERE al.usuario_id = ?');
$st->execute([$u['id']]);
$alumno = $st->fetch();

$aviso = ''; $error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $alumno) {
    csrf_check();
    if (($_POST['accion'] ?? '') === 'entregar') {
        $tareaId = (int)($_POST['tarea_id'] ?? 0);
        $comentario = mb_substr(trim($_POST['comentario'] ?? ''), 0, 300);
        $st = db()->prepare('SELECT COUNT(*) FROM tareas WHERE id = ? AND aula_id = ?');
        $st->execute([$tareaId, (int)$alumno['aula_id']]);
        if ((int)$st->fetchColumn() > 0) {
            db()->prepare('INSERT INTO entregas (tarea_id, alumno_id, comentario) VALUES (?,?,?) ON DUPLICATE KEY UPDATE comentario = VALUES(comentario), entregado = CURRENT_TIMESTAMP')
                ->execute([$tareaId, $u['id'], $comentario]);
            $aviso = 'Tarea entregada correctamente.';
        } else $error = 'No se pudo entregar la tarea.';
    }
}

dash_start($u, 'Portal del alumno', 'Año escolar 2026 · Tu avance académico, tareas y novedades', [['#resumen', 'Resumen'], ['#horario', 'Horario'], ['#tareas', 'Tareas'], ['#boletin', 'Boletín'], ['#asistencia', 'Asistencia'], ['#comunicados', 'Comunicados']]);
if ($aviso) alerta('ok', $aviso);
if ($error) alerta('err', $error);

if (!$alumno) {
    echo '<section class="panel"><h2>Ficha pendiente</h2><p class="muted">Tu cuenta aún no está vinculada a un aula. Comunícate con secretaría académica.</p></section>';
    dash_end();
    return;
}

$st = db()->prepare('SELECT c.nombre curso, n.periodo, n.tipo, n.nota, n.peso, n.fecha FROM notas n JOIN cursos c ON c.id = n.curso_id WHERE n.alumno_id = ? ORDER BY c.nombre, n.periodo, n.fecha');
$st->execute([$u['id']]);
$notas = $st->fetchAll();
$porCurso = [];
foreach ($notas as $n) $porCurso[$n['curso']][] = $n;
$promedio = 0;
if ($notas) {
    $suma = 0; $pesoTotal = 0;
    foreach ($notas as $n) { $suma += (float)$n['nota'] * (int)$n['peso']; $pesoTotal += (int)$n['peso']; }
    $promedio = $pesoTotal ? round($suma / $pesoTotal, 1) : 0;
}

$st = db()->prepare('SELECT COUNT(*) total, COALESCE(SUM(estado IN (\'Puntual\',\'Tardanza\')),0) ok FROM asistencia WHERE alumno_id = ?');
$st->execute([$u['id']]);
$asist = $st->fetch();
$pctAsist = (int)$asist['total'] ? (int)round((int)$asist['ok'] * 100 / (int)$asist['total']) : 0;

$st = db()->prepare('SELECT t.*, c.nombre curso, e.id entrega_id, e.estado entrega_estado FROM tareas t JOIN cursos c ON c.id = t.curso_id LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = ? WHERE t.aula_id = ? AND t.fecha_limite >= CURDATE() ORDER BY t.fecha_limite LIMIT 8');
$st->execute([$u['id'], (int)$alumno['aula_id']]);
$tareas = $st->fetchAll();
$pendientes = 0;
foreach ($tareas as $t) if (!$t['entrega_id']) $pendientes++;

$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$hoyDia = $dias[(int)date('w')];
$horarioHoy = [];
if (in_array($hoyDia, ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'], true)) {
    $st = db()->prepare('SELECT h.hora, c.nombre curso, t.nombre tutor FROM horarios h JOIN cursos c ON c.id = h.curso_id LEFT JOIN usuarios t ON t.id = h.profesor_id WHERE h.aula_id = ? AND h.dia = ? ORDER BY h.hora');
    $st->execute([(int)$alumno['aula_id'], $hoyDia]);
    $horarioHoy = $st->fetchAll();
}

$st = db()->prepare('SELECT a.fecha, a.estado FROM asistencia a WHERE a.alumno_id = ? ORDER BY a.fecha DESC LIMIT 10');
$st->execute([$u['id']]);
$asistencias = $st->fetchAll();

$st = db()->prepare('SELECT o.*, p.nombre profesor FROM observaciones o LEFT JOIN usuarios p ON p.id = o.profesor_id WHERE o.alumno_id = ? ORDER BY o.fecha DESC LIMIT 8');
$st->execute([$u['id']]);
$observaciones = $st->fetchAll();

$noticias = db()->query('SELECT titulo,resumen,categoria,publicado FROM noticias ORDER BY publicado DESC LIMIT 5')->fetchAll();
?>
<section class="kpis" id="resumen">
  <div class="kpi"><strong><?= e($promedio ?: '—') ?></strong><span>Promedio general</span></div>
  <div class="kpi"><strong><?= $pctAsist ?>%</strong><span>Asistencia registrada</span></div>
  <div class="kpi"><strong><?= $pendientes ?></strong><span>Tareas por entregar</span></div>
  <div class="kpi"><strong><?= e($alumno['aula'] ?? '—') ?></strong><span><?= e($alumno['grado']) ?> · <?= e($alumno['nivel']) ?></span></div>
</section>

<section class="panel" id="horario">
  <h2>Horario de hoy · <?= e($hoyDia) ?></h2>
  <?php if (!$horarioHoy): ?><p class="muted">Hoy no tienes clases programadas. Tu aula es <?= e($alumno['aula'] ?? 'sin asignar') ?><?= $alumno['tutor'] ? ' y tu tutor es ' . e($alumno['tutor']) : '' ?>.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Hora</th><th>Curso</th><th>Docente</th></tr>
    <?php foreach ($horarioHoy as $h): ?><tr><td><?= e(substr($h['hora'], 0, 5)) ?></td><td><?= e($h['curso']) ?></td><td><?= e($h['tutor'] ?? '—') ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="tareas">
  <h2>Próximas tareas y evaluaciones</h2>
  <?php if (!$tareas): ?><p class="muted">No tienes tareas pendientes por ahora.</p><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Curso</th><th>Tarea</th><th>Entrega</th><th>Estado</th><th>Acción</th></tr>
    <?php foreach ($tareas as $t): ?>
    <tr>
      <td><?= e($t['curso']) ?></td>
      <td><strong><?= e($t['titulo']) ?></strong><br><span class="muted"><?= e($t['descripcion'] ?? '') ?><?= $t['recurso'] ? ' · Recurso: ' . e($t['recurso']) : '' ?></span></td>
      <td><?= e($t['fecha_limite']) ?></td>
      <td><span class="badge <?= $t['entrega_id'] ? 'ok' : 'warn' ?>"><?= $t['entrega_id'] ? 'Entregada' : 'Pendiente' ?></span></td>
      <td>
        <?php if (!$t['entrega_id']): ?>
        <form method="post" class="inline-form">
          <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="entregar"><input type="hidden" name="tarea_id" value="<?= (int)$t['id'] ?>">
          <input name="comentario" placeholder="Comentario" maxlength="300" class="mini-input">
          <button class="btn btn-bus btn-sm">Entregar</button>
        </form>
        <?php else: ?><span class="muted"><?= e($t['entrega_estado']) ?></span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="boletin">
  <h2>Boletín de notas</h2>
  <?php if (!$porCurso): ?><p class="muted">Aún no hay notas publicadas.</p><?php else: ?>
  <div class="chart-wrap">
    <canvas id="graficaNotasAlumno" aria-label="Gráfica de promedios por curso" role="img"></canvas>
  </div>
  <div class="chart-legend">
    <?php $colores = ['#0E7C66', '#3D8FB0', '#F2B33D', '#E0715A'];
      $i = 0; foreach ($porCurso as $curso => $lista):
        $s = 0; $p = 0; foreach ($lista as $n) { $s += (float)$n['nota'] * (int)$n['peso']; $p += (int)$n['peso']; }
        $prom = $p ? round($s / $p, 1) : 0; ?>
    <span><i style="background:<?= $colores[$i % count($colores)] ?>"></i><?= e($curso) ?>: <?= $prom ?></span>
    <?php $i++; endforeach; ?>
  </div>
  <script>
    const ctx = document.getElementById('graficaNotasAlumno').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_keys($porCurso)) ?>,
        datasets: [{
          label: 'Promedio (20)',
          data: <?= json_encode(array_map(function($c) { $s=0;$p=0;foreach($c as $n){$s+=(float)$n['nota']*(int)$n['peso'];$p+=(int)$n['peso'];}return $p?$s/$p:0;}, $porCurso)) ?>,
          backgroundColor: <?= json_encode(array_map(function($i) { 
            $colors = ['#3CB371', '#4682B4', '#DAA520', '#CD853F'];
            return $colors[$i % count($colors)];
          }, range(0, count($porCurso) - 1))) ?>,
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
          y: { beginAtZero: true, max: 20, grid: { color: 'rgba(0,0,0,0.08)' }, ticks: { stepSize: 5, color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() } },
          x: { grid: { display: false }, ticks: { color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() } }
        },
        title: { display: true, text: 'Promedio por curso', color: window.getComputedStyle(document.documentElement).getPropertyValue('--muted').trim(), font: { size: 13 } }
      }
    });
  </script>
  <div class="table-wrap"><table>
    <tr><th>Curso</th><th>Periodo</th><th>Tipo</th><th>Nota</th><th>Fecha</th></tr>
    <?php foreach ($porCurso as $curso => $lista):
      $s = 0; $p = 0; foreach ($lista as $n) { $s += (float)$n['nota'] * (int)$n['peso']; $p += (int)$n['peso']; }
      $prom = $p ? round($s / $p, 1) : 0; ?>
      <tr><td colspan="5" style="background:var(--surface-2)"><strong><?= e($curso) ?></strong> · promedio <strong><?= $prom ?></strong></td></tr>
      <?php foreach ($lista as $n): ?>
      <tr><td></td><td><?= e($n['periodo']) ?></td><td><?= e($n['tipo']) ?></td><td><strong><?= e($n['nota']) ?></strong></td><td><?= e($n['fecha']) ?></td></tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="asistencia">
  <h2>Asistencia y conducta</h2>
  <div class="grid-2">
    <div>
      <h3>Últimos registros</h3>
      <?php if (!$asistencias): ?><p class="muted">Sin registros de asistencia.</p><?php else: ?>
      <div class="table-wrap"><table>
        <tr><th>Fecha</th><th>Estado</th></tr>
        <?php foreach ($asistencias as $a): ?>
        <tr><td><?= e($a['fecha']) ?></td><td><span class="badge <?= $a['estado'] === 'Puntual' ? 'ok' : ($a['estado'] === 'Tardanza' ? 'warn' : 'bad') ?>"><?= e($a['estado']) ?></span></td></tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
    <div>
      <h3>Resumen de asistencia</h3>
      <?php
        $countPuntual = count(array_filter($asistencias, fn($a) => $a['estado'] === 'Puntual'));
        $countTardanza = count(array_filter($asistencias, fn($a) => $a['estado'] === 'Tardanza'));
        $countFaltaJ = count(array_filter($asistencias, fn($a) => $a['estado'] === 'Falta justificada'));
        $countFaltaI = count(array_filter($asistencias, fn($a) => $a['estado'] === 'Falta injustificada'));
      ?>
      <?php if ($asistencias): ?>
      <div class="chart-wrap">
        <canvas id="graficaAsistenciaAlumno" aria-label="Gráfica de asistencia" role="img"></canvas>
      </div>
      <div class="chart-legend">
        <span><i style="background:#38bdf8"></i>Puntual: <?= $countPuntual ?></span>
        <span><i style="background:#f6a823"></i>Tardanza: <?= $countTardanza ?></span>
        <span><i style="background:#2d6a4f"></i>F. justificada: <?= $countFaltaJ ?></span>
        <span><i style="background:#e0715a"></i>F. injustificada: <?= $countFaltaI ?></span>
      </div>
      <script>
        const ctx = document.getElementById('graficaAsistenciaAlumno').getContext('2d');
        new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: ['Puntual', 'Tardanza', 'F. justificada', 'F. injustificada'],
            datasets: [{
              data: [<?= $countPuntual ?>, <?= $countTardanza ?>, <?= $countFaltaJ ?>, <?= $countFaltaI ?>],
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
    </div>
    <div>
      <h3>Anotaciones de tus profesores</h3>
      <?php if (!$observaciones): ?><p class="muted">Sin anotaciones.</p><?php else: ?>
      <ul class="feed">
        <?php foreach ($observaciones as $o): ?>
        <li><span class="badge <?= $o['tipo'] === 'Reconocimiento' ? 'ok' : 'warn' ?>"><?= e($o['tipo']) ?></span> <?= e($o['detalle']) ?><br><span class="muted"><?= e($o['fecha']) ?><?= $o['profesor'] ? ' · ' . e($o['profesor']) : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="panel" id="comunicados">
  <h2>Comunicados y avisos</h2>
  <ul class="feed">
    <?php foreach ($noticias as $n): ?>
    <li><span class="tag"><?= e($n['categoria']) ?></span> <strong><?= e($n['titulo']) ?></strong><br><span class="muted"><?= e($n['resumen']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php dash_end(); ?>
