# EVAL-NSM

Aula educativa en PHP: cuentas por rol, cursos por grado y salón, actividades, entregas, calificaciones y materiales publicados directamente.

## Estructura

```text
app/                  Lógica PHP, autenticación, base de datos y migraciones
config/               Configuración de MySQL (example.php; local.php privado)
database/             Instrucciones SQL para crear la base
public/               Única carpeta pública: páginas de entrada e imágenes/CSS
resources/views/      Plantillas y componentes de las pantallas
scripts/              Inicio del servidor, datos de prueba y generación del ZIP
storage/              Base SQLite, archivos adjuntos y registros del servidor
tests/                Pruebas del flujo entre cuentas
docs/                Instrucciones para otra laptop
references/           Prototipo y copias anteriores conservadas
dist/                ZIP de distribución
```

`references` y `dist` no son necesarios para ejecutar la aplicación y no se incluyen en el nuevo ZIP. La raíz conserva solo este README, .gitignore y el archivo para abrir el aula.

## Abrir en Windows

Necesitas PHP 8.2+ con PDO, pdo_sqlite, sqlite3, mbstring y fileinfo. El ZIP no incluye PHP; puedes usar XAMPP, PHP en el PATH o una subcarpeta php/ con el ejecutable.

Haz doble clic en **Abrir EVAL-NSM.cmd**, o ejecuta `./scripts/start.ps1`. El iniciador abre un servidor en segundo plano y usa public/ como raíz del sitio. Para ejecutarlo en primer plano desde la raíz:

```powershell
php -d upload_max_filesize=10M -d post_max_size=12M -S 127.0.0.1:8080 -t public scripts/router.php
```

Abre http://127.0.0.1:8080/login.php. Si usas Apache, configura el DocumentRoot apuntando a public/. No sirvas la raíz completa del proyecto.

## Cuentas de prueba

Contraseña inicial: `Mercedes2026!`. Todas pueden cambiarla en Mi cuenta.

| Perfil | Correo |
| --- | --- |
| Estudiante (3° A) | estudiante@eval.test |
| Matemática | docente@eval.test |
| Comunicación | comunicacion@eval.test |
| Ciencia y Tecnología | ciencia@eval.test |
| Ciencias Sociales | sociales@eval.test |
| Inglés | ingles@eval.test |
| Arte y Cultura | arte@eval.test |
| Dirección | director@eval.test |
| Subdirección | subdirector@eval.test |

La base de prueba contiene 5 grados con secciones A–E: 25 salones, 250 alumnas y 6 cursos por salón (150 asignaciones). Cada docente enseña su curso en todos los salones. Las cuentas nuevas de alumnas siguen el formato alumna.1a.01@eval.test; las cuentas anteriores se conservan.

## Flujos

- Dirección crea cuentas, aulas y asignaciones docente-curso-salón. Las cuentas creadas sí pueden iniciar sesión.
- Docente publica tareas de sus cursos; las alumnas de ese salón reciben un aviso y envían respuestas escritas.
- Docente califica AD/A/B/C con comentarios. Las alumnas consultan sus notas; dirección y subdirección consultan el seguimiento.
- Una respuesta se puede editar mientras no esté calificada y la tarea siga abierta. Se permiten entregas tardías, identificadas por su fecha.
- Docente publica textos o archivos PDF, JPG, PNG y TXT directamente, sin aprobación. Dirección puede eliminar el material y su adjunto. Subdirección no puede eliminarlo.
- Descargas y acciones validan permisos en el servidor. Formularios con CSRF; contraseñas almacenadas como hash; datos compartidos entre sesiones.

Sin config/local.php se utiliza storage/eval.sqlite. Cerrar sesión no borra la información. Los cambios se ven al recargar; no hay sincronización offline/remota ni actualización push.

## MySQL

Crea una base eval_nsm con utf8mb4. Copia config/example.php a config/local.php y coloca las credenciales del usuario de esa base. Necesitas pdo_mysql. Al abrir el login se crean las tablas eval_* si no existen; no se eliminan otras tablas.

Cambiar a MySQL no importa automáticamente los datos SQLite: conserva la base y prepara la migración si necesitas esos registros. config/local.php y los datos privados no se guardan en Git.

## Scripts

- `php scripts/seed_school.php`: completa el escenario de prueba; evita duplicar cuentas y cursos y no elimina alumnas existentes.
- `./tests/flow.ps1`: prueba publicación, entrega, nota, aislamiento de aulas y publicación/eliminación de materiales. Requiere el servidor y el escenario de prueba. Crea registros identificados como Prueba integrada.
- `php scripts/package.php`: generadist/EVAL-NSM-laptop.zip con copia consistente de la base local. Excluye configuraciones privadas, logs, referencias y entregas anteriores.

Pendiente: adjuntos a entregas de estudiantes, edición de matrícula/asignaciones, auditoría de notas, recuperación de contraseña, limitación de intentos de login, integración SIAGIE y despliegue de producción con HTTPS.
