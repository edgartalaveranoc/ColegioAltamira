<?php
// Semilla del Colegio Altamira. Ejecutar tras importar database.sql y luego ELIMINAR este archivo.
require __DIR__ . '/config.php';

// ── Migración mínima para bases creadas con versiones anteriores ──
if (!db()->query("SHOW COLUMNS FROM usuarios LIKE 'username'")->fetch()) {
    db()->exec("ALTER TABLE usuarios ADD COLUMN username VARCHAR(60) NOT NULL UNIQUE AFTER nombre");
}
db()->exec("CREATE TABLE IF NOT EXISTS aulas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  nivel ENUM('Primaria','Secundaria') NOT NULL,
  grado VARCHAR(40) NOT NULL,
  capacidad INT NOT NULL DEFAULT 30,
  piso TINYINT NULL,
  tutor_id INT NULL,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB");
db()->exec("CREATE TABLE IF NOT EXISTS alumnos (
  usuario_id INT PRIMARY KEY,
  grado VARCHAR(40) NOT NULL,
  nivel ENUM('Primaria','Secundaria') NOT NULL,
  aula_id INT NULL,
  padre_id INT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE SET NULL,
  FOREIGN KEY (padre_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB");

$ddl = [
"CREATE TABLE IF NOT EXISTS cursos (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(60) NOT NULL UNIQUE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS asignaciones (id INT AUTO_INCREMENT PRIMARY KEY, profesor_id INT NOT NULL, curso_id INT NOT NULL, aula_id INT NOT NULL, UNIQUE KEY prof_curso_aula (profesor_id, curso_id, aula_id), FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE, FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS horarios (id INT AUTO_INCREMENT PRIMARY KEY, aula_id INT NOT NULL, curso_id INT NOT NULL, profesor_id INT NULL, dia ENUM('Lunes','Martes','Miércoles','Jueves','Viernes') NOT NULL, hora TIME NOT NULL, FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE, FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE, FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS notas (id INT AUTO_INCREMENT PRIMARY KEY, alumno_id INT NOT NULL, curso_id INT NOT NULL, profesor_id INT NULL, periodo VARCHAR(20) NOT NULL, tipo ENUM('Continua','Parcial','Final') NOT NULL DEFAULT 'Continua', nota DECIMAL(4,2) NOT NULL, peso INT NOT NULL DEFAULT 1, fecha DATE NOT NULL, FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE, FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS asistencia (id INT AUTO_INCREMENT PRIMARY KEY, alumno_id INT NOT NULL, fecha DATE NOT NULL, estado ENUM('Puntual','Tardanza','Falta justificada','Falta injustificada') NOT NULL, registrado_por INT NULL, UNIQUE KEY alumno_fecha (alumno_id, fecha), FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS observaciones (id INT AUTO_INCREMENT PRIMARY KEY, alumno_id INT NOT NULL, profesor_id INT NULL, tipo ENUM('Conducta','Reconocimiento') NOT NULL, detalle VARCHAR(300) NOT NULL, fecha DATE NOT NULL, FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS tareas (id INT AUTO_INCREMENT PRIMARY KEY, curso_id INT NOT NULL, aula_id INT NOT NULL, profesor_id INT NULL, titulo VARCHAR(160) NOT NULL, descripcion VARCHAR(400) NULL, recurso VARCHAR(255) NULL, fecha_limite DATE NOT NULL, creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE, FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE, FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS entregas (id INT AUTO_INCREMENT PRIMARY KEY, tarea_id INT NOT NULL, alumno_id INT NOT NULL, comentario VARCHAR(300) NULL, estado ENUM('Enviada','Calificada') NOT NULL DEFAULT 'Enviada', entregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY tarea_alumno (tarea_id, alumno_id), FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE, FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS pagos (id INT AUTO_INCREMENT PRIMARY KEY, alumno_id INT NOT NULL, concepto VARCHAR(60) NOT NULL, periodo VARCHAR(40) NOT NULL, monto DECIMAL(8,2) NOT NULL, estado ENUM('Pagado','Pendiente','Vencido') NOT NULL DEFAULT 'Pendiente', vence DATE NOT NULL, pagado_en DATE NULL, FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS citas (id INT AUTO_INCREMENT PRIMARY KEY, padre_id INT NOT NULL, profesor_id INT NULL, alumno_id INT NULL, fecha DATETIME NOT NULL, motivo VARCHAR(300) NOT NULL, estado ENUM('Solicitada','Confirmada','Atendida') NOT NULL DEFAULT 'Solicitada', creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (padre_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL, FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS incidencias (id INT AUTO_INCREMENT PRIMARY KEY, espacio VARCHAR(80) NOT NULL, descripcion VARCHAR(300) NOT NULL, estado ENUM('Abierta','En revisión','Resuelta') NOT NULL DEFAULT 'Abierta', reportado_por INT NULL, fecha DATE NOT NULL, FOREIGN KEY (reportado_por) REFERENCES usuarios(id) ON DELETE SET NULL) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS asistencia_personal (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, fecha DATE NOT NULL, estado ENUM('Presente','Tardanza','Ausente','Licencia') NOT NULL DEFAULT 'Presente', UNIQUE KEY personal_fecha (usuario_id, fecha), FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB",
"CREATE TABLE IF NOT EXISTS lecturas (noticia_id INT NOT NULL, usuario_id INT NOT NULL, leido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (noticia_id, usuario_id), FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE, FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB",
];
foreach ($ddl as $sql) db()->exec($sql);

function uid(string $username): int {
    $st = db()->prepare('SELECT id FROM usuarios WHERE username = ?');
    $st->execute([$username]);
    return (int)$st->fetchColumn();
}

$hecho = [];

// ── Usuarios ────────────────────────────────────────────────────────
if ((int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
    // [nombre, username, contraseña, rol]
    $usuarios = [
        // Administración
        ['Edgar Talaverano', 'etalaverano', 'admin123', 'admin'],

        // Profesores (usuario: inicial del nombre + apellido; clave: DNI)
        ['Claudio Isaias Huancahuire Bravo',   'chuancahuire',   '12345678', 'profesor'],
        ['María Quispe Loayza',    'mquispe',    '40876543', 'profesor'],
        ['Jorge Huamán Ttito',     'jhuaman',    '39654321', 'profesor'],
        ['Rosa Ttito Nina',        'rttito',     '42345678', 'profesor'],
        ['Luis Chumpitaz Vera',    'lchumpitaz', '45678901', 'profesor'],
        ['Carmen Vilca Soto',      'cvilca',     '43456789', 'profesor'],
        ['Pedro Salazar Ramos',    'psalazar',   '40123456', 'profesor'],
        ['Ana Espinoza Yupanqui',  'aespinoza',  '44567890', 'profesor'],
        ['Roberto Nina Ccahuide',  'rnina',      '41987654', 'profesor'],
        ['Julia Condori Melgar',   'jcondori',   '46789012', 'profesor'],

        // Alumnos (usuario: DNI; clave: mismo DNI)
        ['Alonso Ríos',        '72548319', '72548319', 'alumno'],
        ['Camila Flores',      '73829465', '73829465', 'alumno'],
        ['Diego Paredes',      '71658294', '71658294', 'alumno'],
        ['Fernanda Rojas',     '74920385', '74920385', 'alumno'],
        ['Gabriel Ticona',     '70518492', '70518492', 'alumno'],
        ['Valeria Sinche',     '75639281', '75639281', 'alumno'],
        ['Mateo Aguilar',      '72846195', '72846195', 'alumno'],
        ['Luciana Chávez',     '73019284', '73019284', 'alumno'],
        ['Sebastián Yactayo',  '76128394', '76128394', 'alumno'],
        ['Renata Melgar',      '71239485', '71239485', 'alumno'],
        ['Nicolás Zapata',     '74329185', '74329185', 'alumno'],
        ['Ariadna Vilcapoma',  '75849201', '75849201', 'alumno'],
        ['Joaquín Loayza',     '72194837', '72194837', 'alumno'],
        ['Micaela Ocampo',     '73568192', '73568192', 'alumno'],
        ['Andrés Ttito',       '76483920', '76483920', 'alumno'],

        // Padres de familia (usuario: DNI; clave: mismo DNI)
        ['Marco Ríos',        '40238195', '40238195', 'familia'],
        ['Rosa Flores',       '41857293', '41857293', 'familia'],
        ['Hugo Paredes',      '39847562', '39847562', 'familia'],
        ['Sonia Rojas',       '42938475', '42938475', 'familia'],
        ['Félix Ticona',      '38574621', '38574621', 'familia'],
        ['Diana Sinche',      '43746291', '43746291', 'familia'],
        ['Raúl Aguilar',      '40928375', '40928375', 'familia'],
        ['Patricia Chávez',   '42837465', '42837465', 'familia'],
        ['Ernesto Yactayo',   '39284756', '39284756', 'familia'],
        ['Giovanna Melgar',   '43829174', '43829174', 'familia'],

        // Personal académico (usuario: inicial + apellido; clave: DNI)
        ['Teresa Ramos',  'tramos',   '48293746', 'personal'],
        ['Óscar Vega',    'ovega',    '47382915', 'personal'],
        ['Nélida Choque', 'nchoque',  '46192837', 'personal'],
        ['Félix Mendoza', 'fmendoza', '45283746', 'personal'],
        ['Gloria Puma',   'gpuma',    '49382746', 'personal'],
        ['Simón Tapia',   'stapia',   '47182936', 'personal'],
    ];

    $st = db()->prepare('INSERT INTO usuarios (nombre, username, email, password_hash, rol) VALUES (?,?,?,?,?)');
    foreach ($usuarios as [$n, $u, $p, $r]) {
        $st->execute([$n, $u, $u . '@altamira.edu', password_hash($p, PASSWORD_DEFAULT), $r]);
    }
    $hecho[] = count($usuarios) . ' usuarios';
}

// ── Aulas (tutor = profesor) ────────────────────────────────────────
if ((int)db()->query('SELECT COUNT(*) FROM aulas')->fetchColumn() === 0) {
    // [nombre, nivel, grado, capacidad, piso, tutor]
    $aulas = [
        ['Aula 101', 'Primaria',   '1.º de Primaria',   30, 1, 'chuancahuire'],
        ['Aula 102', 'Primaria',   '2.º de Primaria',   30, 1, 'mquispe'],
        ['Aula 103', 'Primaria',   '3.º de Primaria',   30, 1, 'jhuaman'],
        ['Aula 201', 'Primaria',   '4.º de Primaria',   28, 2, 'rttito'],
        ['Aula 202', 'Primaria',   '5.º de Primaria',   28, 2, 'lchumpitaz'],
        ['Aula 203', 'Primaria',   '6.º de Primaria',   28, 2, 'cvilca'],
        ['Aula 301', 'Secundaria', '1.º de Secundaria', 30, 3, 'psalazar'],
        ['Aula 302', 'Secundaria', '2.º de Secundaria', 30, 3, 'aespinoza'],
        ['Aula 303', 'Secundaria', '3.º de Secundaria', 28, 3, 'rnina'],
        ['Aula 401', 'Secundaria', '4.º de Secundaria', 28, 4, 'jcondori'],
        ['Aula 402', 'Secundaria', '5.º de Secundaria', 28, 4, 'jcondori'],
    ];
    $st = db()->prepare('INSERT INTO aulas (nombre, nivel, grado, capacidad, piso, tutor_id) VALUES (?,?,?,?,?,?)');
    foreach ($aulas as [$n, $niv, $g, $cap, $piso, $t]) {
        $st->execute([$n, $niv, $g, $cap, $piso, uid($t) ?: null]);
    }
    $hecho[] = count($aulas) . ' aulas';
}

// ── Matrícula (alumno → aula y apoderado) ───────────────────────────
if ((int)db()->query('SELECT COUNT(*) FROM alumnos')->fetchColumn() === 0) {
    $aulaIds = [];
    foreach (db()->query('SELECT id, nombre FROM aulas')->fetchAll() as $a) $aulaIds[$a['nombre']] = (int)$a['id'];

    // [usuario alumno, grado, nivel, aula, usuario apoderado]
    $matricula = [
        ['72548319', '1.º de Primaria',   'Primaria',   'Aula 101', '40238195'],
        ['73829465', '1.º de Primaria',   'Primaria',   'Aula 101', '41857293'],
        ['71658294', '2.º de Primaria',   'Primaria',   'Aula 102', '39847562'],
        ['74920385', '3.º de Primaria',   'Primaria',   'Aula 103', '42938475'],
        ['70518492', '3.º de Primaria',   'Primaria',   'Aula 103', '38574621'],
        ['75639281', '4.º de Primaria',   'Primaria',   'Aula 201', '43746291'],
        ['72846195', '5.º de Primaria',   'Primaria',   'Aula 202', '40928375'],
        ['73019284', '5.º de Primaria',   'Primaria',   'Aula 202', '42837465'],
        ['76128394', '6.º de Primaria',   'Primaria',   'Aula 203', '39284756'],
        ['71239485', '1.º de Secundaria', 'Secundaria', 'Aula 301', '43829174'],
        ['74329185', '1.º de Secundaria', 'Secundaria', 'Aula 301', null],
        ['75849201', '2.º de Secundaria', 'Secundaria', 'Aula 302', null],
        ['72194837', '3.º de Secundaria', 'Secundaria', 'Aula 303', null],
        ['73568192', '4.º de Secundaria', 'Secundaria', 'Aula 401', null],
        ['76483920', '5.º de Secundaria', 'Secundaria', 'Aula 402', null],
    ];
    $st = db()->prepare('INSERT INTO alumnos (usuario_id, grado, nivel, aula_id, padre_id) VALUES (?,?,?,?,?)');
    foreach ($matricula as [$usr, $g, $niv, $aula, $padre]) {
        $st->execute([uid($usr), $g, $niv, $aulaIds[$aula] ?? null, $padre ? uid($padre) : null]);
    }
    $hecho[] = count($matricula) . ' matrículas';
}

// ── Carga académica y datos de ejemplo ──────────────────────────────
if ((int)db()->query('SELECT COUNT(*) FROM cursos')->fetchColumn() === 0) {
    $nombres = ['Matemática', 'Comunicación', 'Inglés', 'Ciencia y Tecnología', 'Ciencias Sociales', 'Arte', 'Educación Física', 'Robótica'];
    $st = db()->prepare('INSERT INTO cursos (nombre) VALUES (?)');
    foreach ($nombres as $n) $st->execute([$n]);
    $hecho[] = count($nombres) . ' cursos';
}

$cursoId = [];
foreach (db()->query('SELECT id, nombre FROM cursos')->fetchAll() as $c) $cursoId[$c['nombre']] = (int)$c['id'];
$aulaFila = db()->query('SELECT id, nombre, tutor_id FROM aulas ORDER BY id')->fetchAll();

if ((int)db()->query('SELECT COUNT(*) FROM asignaciones')->fetchColumn() === 0) {
    $st = db()->prepare('INSERT IGNORE INTO asignaciones (profesor_id, curso_id, aula_id) VALUES (?,?,?)');
    foreach ($aulaFila as $a) {
        if (!$a['tutor_id']) continue;
        foreach (['Matemática', 'Comunicación', 'Ciencia y Tecnología'] as $cn) {
            if (isset($cursoId[$cn])) $st->execute([$a['tutor_id'], $cursoId[$cn], $a['id']]);
        }
    }
    $hecho[] = 'asignaciones docentes';
}

if ((int)db()->query('SELECT COUNT(*) FROM horarios')->fetchColumn() === 0) {
    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
    $horas = ['08:00', '09:30', '11:00', '12:30'];
    $rot = ['Matemática', 'Comunicación', 'Ciencia y Tecnología', 'Inglés'];
    $st = db()->prepare('INSERT INTO horarios (aula_id, curso_id, profesor_id, dia, hora) VALUES (?,?,?,?,?)');
    foreach ($aulaFila as $a) {
        if (!$a['tutor_id']) continue;
        foreach ($dias as $d => $dia) {
            foreach ($horas as $h => $hora) {
                $cn = $rot[($d + $h) % 4];
                if (isset($cursoId[$cn])) $st->execute([$a['id'], $cursoId[$cn], $a['tutor_id'], $dia, $hora]);
            }
        }
    }
    $hecho[] = 'horarios';
}

$alumnosFila = db()->query('SELECT al.usuario_id, al.aula_id, a.tutor_id FROM alumnos al JOIN aulas a ON a.id = al.aula_id ORDER BY al.usuario_id')->fetchAll();

if ((int)db()->query('SELECT COUNT(*) FROM notas')->fetchColumn() === 0) {
    $st = db()->prepare('INSERT INTO notas (alumno_id, curso_id, profesor_id, periodo, tipo, nota, peso, fecha) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($alumnosFila as $i => $al) {
        foreach (['Matemática', 'Comunicación', 'Ciencia y Tecnología'] as $j => $cn) {
            foreach (['Bimestre I', 'Bimestre II'] as $k => $per) {
                foreach (['Continua', 'Parcial'] as $t => $tipo) {
                    $nota = 12 + (($i * 7 + $j * 5 + $k * 3 + $t * 4) % 8);
                    $st->execute([$al['usuario_id'], $cursoId[$cn], $al['tutor_id'], $per, $tipo, $nota, 1, date('Y-m-d', strtotime('-' . (30 - $k * 10) . ' days'))]);
                }
            }
        }
    }
    $hecho[] = 'notas';
}

if ((int)db()->query('SELECT COUNT(*) FROM asistencia')->fetchColumn() === 0) {
    $fechas = []; $d = new DateTime('today');
    while (count($fechas) < 10) { if ((int)$d->format('N') <= 5) $fechas[] = $d->format('Y-m-d'); $d->modify('-1 day'); }
    $patron = ['Puntual', 'Puntual', 'Puntual', 'Tardanza', 'Puntual', 'Puntual', 'Falta justificada', 'Puntual', 'Puntual', 'Falta injustificada'];
    $st = db()->prepare('INSERT IGNORE INTO asistencia (alumno_id, fecha, estado, registrado_por) VALUES (?,?,?,?)');
    foreach ($alumnosFila as $i => $al) {
        foreach ($fechas as $j => $f) $st->execute([$al['usuario_id'], $f, $patron[($i + $j) % 10], $al['tutor_id']]);
    }
    $hecho[] = 'asistencias';
}

if ((int)db()->query('SELECT COUNT(*) FROM observaciones')->fetchColumn() === 0) {
    $detalles = [
        ['Reconocimiento', 'Excelente participación en la feria de ciencias.'],
        ['Conducta', 'Llegó tarde en tres ocasiones esta semana.'],
        ['Reconocimiento', 'Apoyo destacado a sus compañeros en el proyecto de robótica.'],
        ['Conducta', 'Conversaciones durante la clase de Matemática.'],
        ['Reconocimiento', 'Mejor promedio de su aula en Comunicación.'],
        ['Conducta', 'No presentó la tarea de Ciencias Sociales.'],
    ];
    $st = db()->prepare('INSERT INTO observaciones (alumno_id, profesor_id, tipo, detalle, fecha) VALUES (?,?,?,?,?)');
    foreach ($alumnosFila as $i => $al) {
        if (!isset($detalles[$i])) break;
        [$tipo, $det] = $detalles[$i];
        $st->execute([$al['usuario_id'], $al['tutor_id'], $tipo, $det, date('Y-m-d', strtotime('-' . (3 + $i) . ' days'))]);
    }
    $hecho[] = 'observaciones';
}

if ((int)db()->query('SELECT COUNT(*) FROM tareas')->fetchColumn() === 0) {
    $st = db()->prepare('INSERT INTO tareas (curso_id, aula_id, profesor_id, titulo, descripcion, recurso, fecha_limite) VALUES (?,?,?,?,?,?,?)');
    foreach ($aulaFila as $a) {
        if (!$a['tutor_id']) continue;
        $st->execute([$cursoId['Matemática'], $a['id'], $a['tutor_id'], 'Práctica dirigida de fracciones', 'Resolver los ejercicios 1 al 12 de la guía.', 'guia-fracciones.pdf', date('Y-m-d', strtotime('+4 days'))]);
        $st->execute([$cursoId['Comunicación'], $a['id'], $a['tutor_id'], 'Reseña de lectura mensual', 'Redactar una reseña de 300 palabras del libro asignado.', 'lectura-mensual.pdf', date('Y-m-d', strtotime('+8 days'))]);
    }
    $hecho[] = 'tareas';
}

if ((int)db()->query('SELECT COUNT(*) FROM pagos')->fetchColumn() === 0) {
    $st = db()->prepare('INSERT INTO pagos (alumno_id, concepto, periodo, monto, estado, vence, pagado_en) VALUES (?,?,?,?,?,?,?)');
    $cuotas = [['Marzo', '-40 days'], ['Abril', '-10 days'], ['Mayo', '+20 days']];
    $estados = ['Pagado', 'Pendiente', 'Vencido'];
    foreach ($alumnosFila as $i => $al) {
        foreach ($cuotas as $j => [$per, $offset]) {
            $estado = $estados[($i + $j) % 3];
            $vence = date('Y-m-d', strtotime($offset));
            $st->execute([$al['usuario_id'], 'Pensión mensual', $per, 450, $estado, $vence, $estado === 'Pagado' ? $vence : null]);
        }
    }
    $hecho[] = 'pagos';
}

if ((int)db()->query('SELECT COUNT(*) FROM citas')->fetchColumn() === 0) {
    $filas = db()->query("SELECT u.id padre, al.usuario_id hijo, a.tutor_id FROM usuarios u JOIN alumnos al ON al.padre_id = u.id JOIN aulas a ON a.id = al.aula_id WHERE u.rol = 'familia' ORDER BY u.id LIMIT 3")->fetchAll();
    $st = db()->prepare('INSERT INTO citas (padre_id, profesor_id, alumno_id, fecha, motivo, estado) VALUES (?,?,?,?,?,?)');
    foreach ($filas as $i => $c) {
        $st->execute([$c['padre'], $c['tutor_id'], $c['hijo'], date('Y-m-d H:i:s', strtotime('+' . (2 + $i) . ' days 16:00')), 'Entrevista de seguimiento académico', 'Solicitada']);
    }
    $hecho[] = 'citas';
}

if ((int)db()->query('SELECT COUNT(*) FROM incidencias')->fetchColumn() === 0) {
    $admin = uid('etalaverano');
    $st = db()->prepare('INSERT INTO incidencias (espacio, descripcion, estado, reportado_por, fecha) VALUES (?,?,?,?,?)');
    $st->execute(['Laboratorio 2', 'Una luna del microscopio está suelta.', 'Abierta', $admin, date('Y-m-d')]);
    $st->execute(['Losa deportiva', 'Falta pintura en la línea lateral de la cancha.', 'En revisión', $admin, date('Y-m-d', strtotime('-2 days'))]);
    $st->execute(['Aula 203', 'Proyector sin señal HDMI.', 'Abierta', $admin, date('Y-m-d', strtotime('-1 day'))]);
    $hecho[] = 'incidencias';
}

if ((int)db()->query('SELECT COUNT(*) FROM asistencia_personal')->fetchColumn() === 0) {
    $staff = db()->query("SELECT id FROM usuarios WHERE rol IN ('profesor','personal') ORDER BY id")->fetchAll();
    $fechas = []; $d = new DateTime('today');
    while (count($fechas) < 5) { if ((int)$d->format('N') <= 5) $fechas[] = $d->format('Y-m-d'); $d->modify('-1 day'); }
    $st = db()->prepare('INSERT IGNORE INTO asistencia_personal (usuario_id, fecha, estado) VALUES (?,?,?)');
    foreach ($staff as $i => $s) {
        foreach ($fechas as $j => $f) $st->execute([$s['id'], $f, (($i + $j) % 9 === 0) ? 'Tardanza' : 'Presente']);
    }
    $hecho[] = 'asistencia del personal';
}

echo $hecho
    ? 'Creado: ' . implode(', ', $hecho) . '. Elimina setup.php.'
    : 'La base ya estaba sembrada. Elimina setup.php.';
