CREATE DATABASE IF NOT EXISTS colegio_altamira CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE colegio_altamira;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','profesor','alumno','familia','personal') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE aulas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(40) NOT NULL UNIQUE,
  nivel ENUM('Primaria','Secundaria') NOT NULL,
  grado VARCHAR(40) NOT NULL,
  capacidad INT NOT NULL DEFAULT 30,
  piso TINYINT NULL,
  tutor_id INT NULL,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE alumnos (
  usuario_id INT PRIMARY KEY,
  grado VARCHAR(40) NOT NULL,
  nivel ENUM('Primaria','Secundaria') NOT NULL,
  aula_id INT NULL,
  padre_id INT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE SET NULL,
  FOREIGN KEY (padre_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE noticias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(160) NOT NULL,
  resumen VARCHAR(300) NOT NULL,
  categoria ENUM('Aviso','Noticia','Evento') NOT NULL DEFAULT 'Noticia',
  autor_id INT NULL,
  publicado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE mensajes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) NULL,
  motivo ENUM('Admisión','Consulta general','Visita guiada') NOT NULL,
  mensaje TEXT NOT NULL,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE asignaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  profesor_id INT NOT NULL,
  curso_id INT NOT NULL,
  aula_id INT NOT NULL,
  UNIQUE KEY prof_curso_aula (profesor_id, curso_id, aula_id),
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE horarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aula_id INT NOT NULL,
  curso_id INT NOT NULL,
  profesor_id INT NULL,
  dia ENUM('Lunes','Martes','Miércoles','Jueves','Viernes') NOT NULL,
  hora TIME NOT NULL,
  FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  curso_id INT NOT NULL,
  profesor_id INT NULL,
  periodo VARCHAR(20) NOT NULL,
  tipo ENUM('Continua','Parcial','Final') NOT NULL DEFAULT 'Continua',
  nota DECIMAL(4,2) NOT NULL,
  peso INT NOT NULL DEFAULT 1,
  fecha DATE NOT NULL,
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asistencia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('Puntual','Tardanza','Falta justificada','Falta injustificada') NOT NULL,
  registrado_por INT NULL,
  UNIQUE KEY alumno_fecha (alumno_id, fecha),
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE observaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  profesor_id INT NULL,
  tipo ENUM('Conducta','Reconocimiento') NOT NULL,
  detalle VARCHAR(300) NOT NULL,
  fecha DATE NOT NULL,
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tareas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL,
  aula_id INT NOT NULL,
  profesor_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  descripcion VARCHAR(400) NULL,
  recurso VARCHAR(255) NULL,
  fecha_limite DATE NOT NULL,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
  FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tarea_id INT NOT NULL,
  alumno_id INT NOT NULL,
  comentario VARCHAR(300) NULL,
  estado ENUM('Enviada','Calificada') NOT NULL DEFAULT 'Enviada',
  entregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY tarea_alumno (tarea_id, alumno_id),
  FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alumno_id INT NOT NULL,
  concepto VARCHAR(60) NOT NULL,
  periodo VARCHAR(40) NOT NULL,
  monto DECIMAL(8,2) NOT NULL,
  estado ENUM('Pagado','Pendiente','Vencido') NOT NULL DEFAULT 'Pendiente',
  vence DATE NOT NULL,
  pagado_en DATE NULL,
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  padre_id INT NOT NULL,
  profesor_id INT NULL,
  alumno_id INT NULL,
  fecha DATETIME NOT NULL,
  motivo VARCHAR(300) NOT NULL,
  estado ENUM('Solicitada','Confirmada','Atendida') NOT NULL DEFAULT 'Solicitada',
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (padre_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE incidencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  espacio VARCHAR(80) NOT NULL,
  descripcion VARCHAR(300) NOT NULL,
  estado ENUM('Abierta','En revisión','Resuelta') NOT NULL DEFAULT 'Abierta',
  reportado_por INT NULL,
  fecha DATE NOT NULL,
  FOREIGN KEY (reportado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE asistencia_personal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('Presente','Tardanza','Ausente','Licencia') NOT NULL DEFAULT 'Presente',
  UNIQUE KEY personal_fecha (usuario_id, fecha),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lecturas (
  noticia_id INT NOT NULL,
  usuario_id INT NOT NULL,
  leido_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (noticia_id, usuario_id),
  FOREIGN KEY (noticia_id) REFERENCES noticias(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO noticias (titulo,resumen,categoria) VALUES
('Abrimos la matrícula 2027','Las vacantes de 1.º de primaria y 1.º de secundaria ya están disponibles. Agenda tu visita guiada.','Aviso'),
('Feria de Ciencias: 42 proyectos en el patio central','Estudiantes de 4.º a 5.º de secundaria presentaron prototipos de energía solar y huertos hidropónicos.','Noticia'),
('Taller de robótica para primaria','Desde noviembre, los sábados de 9:00 a 11:00 a. m. en el laboratorio de innovación.','Evento');
