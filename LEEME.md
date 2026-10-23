# Colegio Altamira: instalación en XAMPP

## 1. Copiar el proyecto
Copia toda la carpeta del proyecto a `C:\xampp\htdocs\altamira` (o la subcarpeta que prefieras dentro de `htdocs`).

## 2. Arrancar Apache y MySQL
Abre el **Panel de control de XAMPP** y presiona **Start** en los módulos **Apache** y **MySQL**. Ambos deben quedar en verde.

## 3. Importar la base de datos
Con MySQL corriendo, abre `http://localhost/phpmyadmin`:
1. Pestaña **Importar** → **Seleccionar archivo** → elige `database.sql` → **Continuar**.
2. Se creará la base `colegio_altamira` con las tablas de usuarios, aulas, alumnado, cursos, horarios, notas, asistencia, tareas, pagos, citas, incidencias, comunicados y lecturas.

(Alternativa por consola: `C:\xampp\mysql\bin\mysql -u root < database.sql`.)

## 4. Crear los usuarios
Abre `http://localhost/altamira/setup.php` **una sola vez**: crea el admin y los 41 usuarios de las 4 secciones, las 11 aulas con sus tutores, la matrícula de los 15 alumnos y datos de ejemplo (cursos, horarios, notas, asistencia, tareas, pagos, citas e incidencias). Puedes volver a abrirlo si te falta alguna sección: no duplica datos. Luego **borra el archivo `setup.php`** por seguridad.

## 5. Verificar la conexión
`config.php` ya viene apuntando a XAMPP por defecto. Solo ajústalo si cambiaste algo:
- `DB_HOST = 'localhost'`
- `DB_USER = 'root'`
- `DB_PASS = ''` (vacío en XAMPP por defecto)
- `DB_NAME = 'colegio_altamira'`

Si todo funciona, `http://localhost/altamira/index.html` carga las noticias desde MySQL.

## 6. Ingresar al portal
En la portada pulsa **Ingresar**: se abre una ventana de acceso sobre la página, con el fondo difuminado. También puedes entrar directo a `http://localhost/altamira/login.php` con tu **usuario** (no correo). Cada rol tiene su propio dashboard:

- **Personal académico** (admin y personal): métricas globales, asistencia del día, pensiones, rendimiento por grado, gestión de usuarios y permisos, asignación de docentes, admisiones y matrículas, comunicados oficiales, asistencia del personal e incidencias.
- **Profesores**: horario semanal, alumnado filtrable por aula o búsqueda, libreta de notas (registrar, editar y eliminar), promedios por curso, asistencia diaria, observaciones de conducta y publicación de tareas con sus entregas.
- **Alumnos**: horario del día, tareas con entrega en línea, boletín con promedios por curso, asistencia, anotaciones de sus docentes y comunicados.
- **Padres de familia**: selector de hijos, boletín y promedios, asistencia y conducta, estado de cuenta con pago de cuotas, solicitud de citas con docentes y comunicados con confirmación de lectura.

### Credenciales
   - Admin: `etalaverano` / `admin123`
   - Profesores (10): inicial del nombre + apellido (ej. `ccahuide`), clave = su DNI.
   - Alumnos (15): usuario = su DNI, clave = mismo DNI.
   - Padres (10): usuario = su DNI, clave = mismo DNI.
   - Personal académico (6): inicial del nombre + apellido (ej. `tramos`), clave = su DNI.

## Fotografías de la portada
Las imágenes del campus (fachada, aulas, laboratorio, biblioteca, deporte y estudiantes) están en `assets/img/` y provienen de Unsplash, con licencia libre de uso. Se muestran únicamente en `index.html`; el login y el dashboard no cargan imágenes.

## Diseño del dashboard

El dashboard ha sido rediseñado con una estética profesional y moderna:

- **Tipografía**: Sora para títulos (pesos 600/700/800), Inter para texto corporativo.
- **Paleta**: Verde acento (#0E7C66), océano (#3D8FB0), dorado (#F2B33D) y coral (#E0715A) con modo oscuro desde `localStorage`.
- **Cards KPI**: métricas con gradiente superior y efecto hover.
- **Paneles**: bordes suaves, sombras sutiles, gradiente decorativo en cada título.
- **Tablas**: hover en filas, encabezados con tipografía Sora en mayúsculas.
- **Gráficas (Chart.js)**:
  - Profesor: barras de promedio por curso + doughnut de asistencia del día.
  - Alumno: barras de promedio por curso + doughnut de asistencia.
  - Familia: doughnut de pagos (pagado / por vencer / vencido).

### Modo oscuro

Cambia el tema con el botón 🌙 en la esquina del dashboard. La preferencia se guarda en `localStorage` y respeta el esquema del sistema si no hay preferencia guardada.
