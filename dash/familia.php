<?php
require_once __DIR__ . '/comun.php';

$st = db()->prepare('SELECT al.usuario_id, al.grado, al.nivel, al.aula_id, u.nombre, au.nombre aula, t.nombre tutor FROM alumnos al JOIN usuarios u ON u.id = al.usuario_id LEFT JOIN aulas au ON au.id = al.aula_id LEFT JOIN usuarios t ON t.id = au.tutor_id WHERE al.padre_id = ? ORDER BY u.nombre');
$st->execute([$u['id']]);
$hijos = $st->fetchAll();

$hijoId = (int)($_GET['hijo'] ?? 0);
$idsHijos = array_map('intval', array_column($hijos, 'usuario_id'));
if (!in_array($hijoId, $idsHijos, true)) $hijoId = $idsHijos[0] ?? 0;
$hijo = null;
foreach ($hijos as $h) if ((int)$h['usuario_id'] === $hijoId) $hijo = $h;

$aviso = ''; $error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $hijo) {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'pagar') {
            $pagoId = (int)($_POST['pago_id'] ?? 0);
            $st = db()->prepare("UPDATE pagos SET estado = 'Pagado', pagado_en = CURDATE() WHERE id = ? AND alumno_id = ? AND estado <> 'Pagado'");
            $st->execute([$pagoId, $hijoId]);
            $aviso = $st->rowCount() ? 'Pago registrado.' : 'La cuota ya estaba pagada o no existe.';
        } elseif ($accion === 'cita') {
            $profe = (int)($_POST['profesor_id'] ?? 0) ?: null;
            $fecha = $_POST['fecha'] ?? '';
            $motivo = trim($_POST['motivo'] ?? '');
            if ($fecha !== '' && $motivo !== '') {
                db()->prepare('INSERT INTO citas (padre_id, profesor_id, alumno_id, fecha, motivo) VALUES (?,?,?,?,?)')
                    ->execute([$u['id'], $profe, $hijoId, str_replace('T', ' ', $fecha) . ':00', mb_substr($motivo, 0, 300)]);
                $aviso = 'Solicitud de reunión enviada.';
            } else $error = 'Indica la fecha y el motivo de la reunión.';
        } elseif ($accion === 'lectura') {
            db()->prepare('INSERT IGNORE INTO lecturas (noticia_id, usuario_id) VALUES (?,?)')->execute([(int)($_POST['noticia_id'] ?? 0), $u['id']]);
            $aviso = 'Lectura confirmada.';
        }
    } catch (Throwable $ex) {
        $error = 'No se pudo completar la operación.';
    }
}

dash_start($u, 'Portal de familias', 'Año escolar 2026 · Seguimiento académico, pagos y comunicación con el colegio', [['#resumen', 'Resumen'], ['#academico', 'Académico'], ['#asistencia', 'Asistencia'], ['#pagos', 'Pagos'], ['#citas', 'Citas'], ['#comunicados', 'Comunicados']]);
if ($aviso) alerta('ok', $aviso);
if ($error) alerta('err', $error);

if (!$hijo) {
    echo '<section class="panel"><h2>Sin estudiantes vinculados</h2><p class="muted">Tu cuenta aún no tiene hijos registrados. Comunícate con secretaría académica.</p></section>';
    dash_end();
    return;
}

$st = db()->prepare('SELECT c.nombre curso, n.periodo, n.tipo, n.nota, n.peso, n.fecha FROM notas n JOIN cursos c ON c.id = n.curso_id WHERE n.alumno_id = ? ORDER BY c.nombre, n.periodo, n.fecha');
$st->execute([$hijoId]);
$notas = $st->fetchAll();
$porCurso = [];
foreach ($notas as $n) $porCurso[$n['curso']][] = $n;
$suma = 0; $pesoTotal = 0;
foreach ($notas as $n) { $suma += (float)$n['nota'] * (int)$n['peso']; $pesoTotal += (int)$n['peso']; }
$promedio = $pesoTotal ? round($suma / $pesoTotal, 1) : 0;

$st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM(estado IN ('Puntual','Tardanza')),0) ok FROM asistencia WHERE alumno_id = ?");
$st->execute([$hijoId]);
$asist = $st->fetch();
$pctAsist = (int)$asist['total'] ? (int)round((int)$asist['ok'] * 100 / (int)$asist['total']) : 0;

$st = db()->prepare('SELECT a.fecha, a.estado FROM asistencia a WHERE a.alumno_id = ? ORDER BY a.fecha DESC LIMIT 10');
$st->execute([$hijoId]);
$asistencias = $st->fetchAll();

$st = db()->prepare('SELECT o.*, p.nombre profesor FROM observaciones o LEFT JOIN usuarios p ON p.id = o.profesor_id WHERE o.alumno_id = ? ORDER BY o.fecha DESC LIMIT 8');
$st->execute([$hijoId]);
$observaciones = $st->fetchAll();

$st = db()->prepare('SELECT * FROM pagos WHERE alumno_id = ? ORDER BY vence');
$st->execute([$hijoId]);
$pagos = $st->fetchAll();
$deuda = 0;
foreach ($pagos as $p) if ($p['estado'] !== 'Pagado') $deuda += (float)$p['monto'];

$st = db()->prepare('SELECT c.*, p.nombre profesor, a.nombre alumno FROM citas c LEFT JOIN usuarios p ON p.id = c.profesor_id LEFT JOIN usuarios a ON a.id = c.alumno_id WHERE c.padre_id = ? ORDER BY c.fecha DESC LIMIT 8');
$st->execute([$u['id']]);
$citas = $st->fetchAll();
$profesores = db()->query("SELECT id,nombre FROM usuarios WHERE rol = 'profesor' AND activo = 1 ORDER BY nombre")->fetchAll();

$noticias = db()->query('SELECT n.*, (SELECT COUNT(*) FROM lecturas l WHERE l.noticia_id = n.id AND l.usuario_id = ' . (int)$u['id'] . ') leida FROM noticias n ORDER BY n.publicado DESC LIMIT 6')->fetchAll();
?>
<?php if ($hijos): ?>
<section class="panel">
  <h2>Tus hijos</h2>
  <nav class="tabs">
    <?php foreach ($hijos as $h): ?>
    <a class="<?= (int)$h['usuario_id'] === $hijoId ? 'act' : '' ?>" href="panel.php?hijo=<?= (int)$h['usuario_id'] ?>#resumen"><?= e($h['nombre']) ?> · <?= e($h['grado']) ?></a>
    <?php endforeach; ?>
  </nav>
</section>
<?php endif; ?>

<section class="kpis" id="resumen">
  <div class="kpi"><strong><?= e($promedio ?: '—') ?></strong><span>Promedio de <?= e($hijo['nombre']) ?></span></div>
  <div class="kpi"><strong><?= $pctAsist ?>%</strong><span>Asistencia</span></div>
  <div class="kpi"><strong>S/ <?= number_format($deuda, 2) ?></strong><span>Pendiente de pago</span></div>
  <div class="kpi"><strong><?= count($observaciones) ?></strong><span>Anotaciones recientes</span></div>
</section>

<section class="panel" id="academico">
  <h2>Boletín de <?= e($hijo['nombre']) ?> · <?= e($hijo['grado']) ?> (<?= e($hijo['nivel']) ?>)</h2>
  <?php if (!$porCurso): ?><p class="muted">Aún no hay notas publicadas.</p><?php else: ?>
  <?php foreach ($porCurso as $curso => $lista):
    $s = 0; $p = 0; foreach ($lista as $n) { $s += (float)$n['nota'] * (int)$n['peso']; $p += (int)$n['peso']; }
    $prom = $p ? round($s / $p, 1) : 0; ?>
  <div class="bar-row"><span><?= e($curso) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= max(4, (int)round($prom * 5)) ?>%"></div></div><strong><?= $prom ?></strong></div>
  <?php endforeach; ?>
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
      <?php if (!$asistencias): ?><p class="muted">Sin registros.</p><?php else: ?>
      <div class="table-wrap"><table>
        <tr><th>Fecha</th><th>Estado</th></tr>
        <?php foreach ($asistencias as $a): ?>
        <tr><td><?= e($a['fecha']) ?></td><td><span class="badge <?= $a['estado'] === 'Puntual' ? 'ok' : ($a['estado'] === 'Tardanza' ? 'warn' : 'bad') ?>"><?= e($a['estado']) ?></span></td></tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
    <div>
      <h3>Anotaciones de los docentes</h3>
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

<section class="panel" id="pagos">
  <h2>Estado de cuenta</h2>
  <?php if (!$pagos): ?><p class="muted">Sin movimientos registrados.</p><?php else: ?>
  <?php
    $totalPagado = 0; $totalVence = 0; $totalVencido = 0;
    foreach ($pagos as $p) {
      if ($p['estado'] === 'Pagado') $totalPagado += (float)$p['monto'];
      elseif ($p['estado'] === 'Vencido') $totalVencido += (float)$p['monto'];
      else $totalVence += (float)$p['monto'];
    }
  ?>
  <div class="grid-2" style="margin-bottom:1.5rem">
    <div class="chart-wrap">
      <canvas id="graficaPagos" aria-label="Gráfica de pagos" role="img"></canvas>
    </div>
    <div>
      <h3>Resumen de pagos</h3>
      <div class="chart-legend">
        <span><i style="background:#2d6a4f"></i>Pagado: S/ <?= number_format($totalPagado, 2) ?></span>
        <span><i style="background:#f6a823"></i>Por vencer: S/ <?= number_format($totalVence, 2) ?></span>
        <span><i style="background:#e0715a"></i>Vencido: S/ <?= number_format($totalVencido, 2) ?></span>
      </div>
    </div>
  </div>
  <script>
    const ctx = document.getElementById('graficaPagos').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pagado', 'Por vencer', 'Vencido'],
        datasets: [{
          data: [<?= $totalPagado ?>, <?= $totalVence ?>, <?= $totalVencido ?>],
          backgroundColor: ['#2E7D32', '#F9A825', '#C62828'],
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
                return context.label + ': S/ ' + context.parsed.toLocaleString('es-PE', {minimumFractionDigits: 2});
              }
            }
          }
        },
        cutout: '60%'
      }
    });
  </script>
  <div class="table-wrap"><table>
    <tr><th>Concepto</th><th>Periodo</th><th>Monto</th><th>Vence</th><th>Estado</th><th></th></tr>
    <?php foreach ($pagos as $p): ?>
    <tr>
      <td><?= e($p['concepto']) ?></td><td><?= e($p['periodo']) ?></td><td>S/ <?= number_format((float)$p['monto'], 2) ?></td><td><?= e($p['vence']) ?></td>
      <td><span class="badge <?= $p['estado'] === 'Pagado' ? 'ok' : ($p['estado'] === 'Vencido' ? 'bad' : 'warn') ?>"><?= e($p['estado']) ?></span></td>
      <td><?php if ($p['estado'] !== 'Pagado'): ?>
        <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="pagar"><input type="hidden" name="pago_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-bus btn-sm">Pagar en línea</button></form>
      <?php else: ?><span class="muted"><?= e($p['pagado_en']) ?></span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="citas">
  <h2>Citaciones y reuniones</h2>
  <form method="post" class="form-inline">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="cita">
    <label>Profesor<select name="profesor_id">
      <option value="0">Coordinación académica</option>
      <?php foreach ($profesores as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $hijo['tutor'] === $p['nombre'] ? ' selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Fecha y hora<input type="datetime-local" name="fecha" required></label>
    <label>Motivo<input name="motivo" required maxlength="300"></label>
    <button class="btn btn-bus btn-sm">Solicitar reunión</button>
  </form>
  <?php if ($citas): ?>
  <div class="table-wrap" style="margin-top:1rem"><table>
    <tr><th>Fecha</th><th>Hijo</th><th>Profesor</th><th>Motivo</th><th>Estado</th></tr>
    <?php foreach ($citas as $c): ?>
    <tr><td><?= e($c['fecha']) ?></td><td><?= e($c['alumno'] ?? '—') ?></td><td><?= e($c['profesor'] ?? 'Coordinación') ?></td><td><?= e($c['motivo']) ?></td><td><span class="badge <?= $c['estado'] === 'Atendida' ? 'ok' : 'warn' ?>"><?= e($c['estado']) ?></span></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel" id="comunicados">
  <h2>Comunicados del colegio</h2>
  <ul class="feed">
    <?php foreach ($noticias as $n): ?>
    <li>
      <span class="tag"><?= e($n['categoria']) ?></span> <strong><?= e($n['titulo']) ?></strong>
      <?php if ((int)$n['leida']): ?> <span class="badge ok">Leído</span><?php endif; ?>
      <br><span class="muted"><?= e($n['resumen']) ?></span>
      <?php if (!(int)$n['leida']): ?>
      <form method="post" class="inline-form" style="margin-top:.4rem">
        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="accion" value="lectura"><input type="hidden" name="noticia_id" value="<?= (int)$n['id'] ?>">
        <button class="btn btn-line btn-sm">Confirmar lectura</button>
      </form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php dash_end(); ?>
