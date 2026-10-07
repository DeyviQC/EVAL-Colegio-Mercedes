<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>
            <?= escape($pages[$page]) ?>
            · EVAL
        </title>
        <link rel="stylesheet" href="assets/style.css">
        <link rel="stylesheet" href="assets/institucion.css?v=4">
        <link rel="stylesheet" href="assets/roles.css">
    </head>
    <body>
        <aside class="sidebar">
            <a class="brand" href="./">
                <img class="crest" src="assets/escudo-mercedes.png" alt="Escudo institucional">
                <span>
                    EVAL
                    <small>
                        AULA VIRTUAL
                    </small>
                </span>
            </a>
            <div class="school">
                <span>
                    Institución Educativa Emblemática
                </span>
                <strong>
                    Nuestra Señora de las Mercedes
                </strong>
                <small>
                    <?= escape($user['label']) ?>
                </small>
            </div>
            <nav>
                <?php foreach($pages as $key=>$label): ?>
                <a href="?page=<?= $key ?>" class="<?= $page===$key?'active':'' ?>" <?= $page===$key?'aria-current="page"':'' ?>>
                    <?= escape($label) ?>
                    <?= $key==='avisos'&&$unread?' ('.$unread.')':'' ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <div class="profile">
                <div>
                    <?= escape($user['name']) ?>
                    <small>
                        <?= escape($user['email']) ?>
                    </small>
                </div>
            </div>
            <form action="logout.php" method="post">
                <?php csrf(); ?>
                <button class="logout-button">
                    Cerrar sesión ↗
                </button>
            </form>
        </aside>
        <div class="workspace">
            <header>
                <span>
                    <?= escape($user['label']) ?>
                    /
                    <strong>
                        <?= escape($pages[$page]) ?>
                    </strong>
                </span>
                <a class="mode" href="?page=avisos">
                    <?= $unread ?>
                    avisos nuevos
                </a>
            </header>
            <main>
                <div class="demo">
                    <?= db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'Base local persistente · Cuentas de prueba':'Conectado a MySQL' ?>
                    · Los cambios se comparten entre cuentas al recargar.
                </div>
                <div class="heading">
                    <div class="eyebrow">
                        EVAL · TU COMUNIDAD EDUCATIVA
                    </div>
                    <h1>
                        <?= $page==='inicio'?'Hola, '.escape($user['name']):escape($pages[$page]) ?>
                    </h1>
                </div>
                <?php if($selectedRoom&&!$student): ?>
                <p class="room-context">
                    <a href="?page=<?= escape($page) ?>">
                        Grados
                    </a>
                    /
                    <a href="?page=<?= escape($page) ?>&grade=<?= $selectedGrade ?>">
                        Grado
                        <?= $selectedGrade ?>
                    </a>
                    /
                    <strong>
                        <?= escape($selectedRoom['name']) ?>
                    </strong>
                </p>
                <?php endif; ?>
                <?php if($error): ?>
                <div class="alert" role="alert">
                    <?= escape($error) ?>
                </div>
                <?php endif; if(isset($_GET['saved'])): ?>
                <div class="success" role="status">
                    Los cambios se guardaron correctamente.
                </div>
                <?php endif; ?>
                <?php if($needsSelection): require __DIR__.'/partials/group_navigation.php'; elseif($page==='inicio'): ?>
                <?php if($student): ?>
                <section class="hero">
                    <div>
                        <span class="pill">
                            BIENVENIDA A EVAL
                        </span>
                        <h2>
                            Tus ideas tienen
                            <br>
                            un gran futuro.
                        </h2>
                        <p>
                            Revisa tus actividades y comparte lo que has aprendido.
                        </p>
                        <a class="button" href="?page=actividades">
                            Ver mis actividades →
                        </a>
                    </div>
                    <div class="hero-visual">
                        <img src="assets/estudiantes-hero.png" alt="Estudiantes aprendiendo juntas">
                        <span class="visual-note">
                            ✦ Juntas llegamos más lejos
                        </span>
                    </div>
                </section>
                <?php endif; ?>
                <div class="stats">
                    <div>
                        <span class="stat-icon blue">
                            ▦
                        </span>
                        <p>
                            <strong>
                                <?= count($courses) ?>
                            </strong>
                            Cursos vinculados
                        </p>
                    </div>
                    <div>
                        <span class="stat-icon orange">
                            ✓
                        </span>
                        <p>
                            <strong>
                                <?= count($activities) ?>
                            </strong>
                            Actividades publicadas
                        </p>
                    </div>
                    <div>
                        <span class="stat-icon green">
                            ◎
                        </span>
                        <p>
                            <strong>
                                <?= count(array_filter($submissions,fn($s)=>$s['grade']===null)) ?>
                            </strong>
                            Entregas sin calificar
                        </p>
                    </div>
                </div>
                <div class="courses">
                    <?php foreach($pages as $key=>$label):if(in_array($key,['inicio','perfil','avisos'],true))continue; ?>
                    <a class="activity" href="?page=<?= $key ?>">
                        <h2>
                            <?= escape($label) ?>
                            →
                        </h2>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php elseif($page==='cursos'): ?>
                <?php if($selectedRoom&&!$student)require __DIR__.'/partials/roster.php'; ?>
                <div class="courses">
                    <?php foreach($courses as $course): ?>
                    <article class="activity">
                        <span class="eyebrow">
                            <?= escape($course['group_name']) ?>
                        </span>
                        <h2>
                            <?= escape($course['name']) ?>
                        </h2>
                        <p>
                            Docente:
                            <?= escape($course['teacher']) ?>
                        </p>
                        <a class="button" href="?page=actividades&grade=<?= $selectedGrade ?>&group=<?= $selectedGroup ?>&course=<?= (int)$course['id'] ?>">
                            Ver actividades →
                        </a>
                    </article>
                    <?php endforeach; if(!$courses): ?>
                    <p>
                        No tienes cursos asignados. Consulta con dirección.
                    </p>
                    <?php endif; ?>
                </div>
                <?php elseif($page==='actividades'): ?>
                <?php if($teacher): ?>
                <details class="activity" <?= $error?'open':'' ?>>
                    <summary>
                        Publicar una actividad
                    </summary>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="create_activity">
                        <?php courseSelect($courses); ?>
                        <label>
                            Título
                            <input name="title" required maxlength="200">
                        </label>
                        <label>
                            Fecha de entrega
                            <input type="date" name="due_date" min="<?= date('Y-m-d') ?>" required>
                        </label>
                        <label>
                            Instrucciones
                            <textarea name="description" rows="4" maxlength="5000" required></textarea>
                        </label>
                        <button class="button">
                            Publicar para las estudiantes
                        </button>
                    </form>
                </details>
                <?php endif; ?>
                <form method="get" class="filter">
                    <input type="hidden" name="page" value="actividades">
                    <input type="hidden" name="group" value="<?= $selectedGroup ?>">
                    <input type="hidden" name="grade" value="<?= $selectedGrade ?>">
                    <label for="filter">
                        Curso
                    </label>
                    <select id="filter" name="course">
                        <option value="0">
                            Todos
                        </option>
                        <?php foreach($courses as $course): ?>
                        <option value="<?= (int)$course['id'] ?>" <?= (int)($_GET['course']??0)===(int)$course['id']?'selected':'' ?>>
                            <?= escape($course['name'].' · '.$course['group_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button">
                        Filtrar
                    </button>
                </form>
                <?php $visible=0;foreach($activities as $a):if((int)($_GET['course']??0)>0&&(int)$_GET['course']!==(int)$a['course_id'])continue;$visible++;$own=$mySubmissions[$a['id']]??null; ?>
                <article class="activity">
                    <div class="activity-top">
                        <span class="eyebrow">
                            <?= escape($a['course'].' · '.$a['group_name']) ?>
                        </span>
                        <span class="badge">
                            <?= escape($a['status']) ?>
                            <?= $student&&$own?' · '.($own['grade']!==null?'Calificada':'Entregada'):'' ?>
                        </span>
                    </div>
                    <h2>
                        <?= escape($a['title']) ?>
                    </h2>
                    <p>
                        <?= nl2br(escape($a['description'])) ?>
                    </p>
                    <small>
                        Entrega hasta
                        <?= escape($a['due_date']) ?>
                        · Docente:
                        <?= escape($a['teacher']) ?>
                    </small>
                    <?php if($student): ?>
                    <?php if($own): ?>
                    <p>
                        <strong>
                            Tu entrega:
                        </strong>
                        <?= nl2br(escape($own['answer'])) ?>
                    </p>
                    <small>
                        Enviada:
                        <?= escape($own['submitted_at']) ?>
                        <?= substr($own['submitted_at'],0,10)>$a['due_date']?' · Entrega tardía':'' ?>
                    </small>
                    <?php if($own['grade']!==null): ?>
                    <p>
                        <strong>
                            Nota:
                            <?= escape($own['grade']) ?>
                        </strong>
                        <br>
                        <?= nl2br(escape($own['feedback']??'')) ?>
                    </p>
                    <?php endif; endif; ?>
                    <?php if($a['status']==='activa'&&(!$own||$own['grade']===null)): ?>
                    <details>
                        <summary>
                            <?= $own?'Editar entrega':'Entregar tarea' ?>
                        </summary>
                        <p>
                            Envía tu respuesta escrita. Las entregas después de la fecha se marcarán como tardías.
                        </p>
                        <form method="post">
                            <?php csrf(); ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="activity_id" value="<?= (int)$a['id'] ?>">
                            <label>
                                Tu respuesta
                                <textarea name="answer" required maxlength="10000" rows="5"><?= escape($own['answer']??'') ?></textarea>
                            </label>
                            <button class="button">
                                Enviar al docente →
                            </button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php elseif($teacher): ?>
                    <p>
                        <?= (int)query('SELECT COUNT(*) FROM eval_submissions WHERE activity_id=?',[$a['id']])->fetchColumn() ?>
                        entregas recibidas
                    </p>
                    <a class="button" href="?page=entregas&grade=<?= $selectedGrade ?>&group=<?= $selectedGroup ?>&activity=<?= (int)$a['id'] ?>">
                        Revisar entregas
                    </a>
                    <?php if($a['status']==='activa'): ?>
                    <form method="post" style="margin-top:12px">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="close_activity">
                        <input type="hidden" name="activity_id" value="<?= (int)$a['id'] ?>">
                        <button class="logout-button" style="width:auto">
                            Cerrar nuevas entregas
                        </button>
                    </form>
                    <?php endif; endif; ?>
                </article>
                <?php endforeach; if(!$visible): ?>
                <p>
                    No hay actividades en esta selección.
                </p>
                <?php endif; ?>
                <?php elseif($page==='entregas'||$page==='notas'): ?>
                <?php if($selectedRoom&&!$student)require __DIR__.'/partials/roster.php'; ?>
                <?php if(!$student): ?>
                <form class="filter" method="get">
                    <input type="hidden" name="page" value="entregas">
                    <input type="hidden" name="group" value="<?= $selectedGroup ?>">
                    <input type="hidden" name="grade" value="<?= $selectedGrade ?>">
                    <label for="activity">
                        Actividad
                    </label>
                    <select name="activity" id="activity">
                        <option value="0">
                            Todas
                        </option>
                        <?php foreach($activities as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)($_GET['activity']??0)===(int)$a['id']?'selected':'' ?>>
                            <?= escape($a['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button">
                        Filtrar
                    </button>
                </form>
                <?php endif; ?>
                <?php $visible=0;foreach($submissions as $s):if((int)($_GET['activity']??0)>0&&(int)$_GET['activity']!==(int)$s['activity_id'])continue;$visible++; ?>
                <article class="activity">
                    <div class="activity-top">
                        <span class="eyebrow">
                            <?= escape($s['course'].' · '.$s['group_name']) ?>
                        </span>
                        <span class="badge">
                            <?= $s['grade']!==null?'Nota '.escape($s['grade']):'Pendiente de calificación' ?>
                        </span>
                    </div>
                    <h2>
                        <?= escape($s['title']) ?>
                    </h2>
                    <p>
                        <strong>
                            <?= escape($s['student']) ?>
                        </strong>
                        ·
                        <?= escape($s['submitted_at']) ?>
                        <?= substr($s['submitted_at'],0,10)>$s['due_date']?' · Tardía':'' ?>
                    </p>
                    <p>
                        <?= nl2br(escape($s['answer'])) ?>
                    </p>
                    <?php if($s['feedback']!==null): ?>
                    <p>
                        <strong>
                            Comentarios del docente:
                        </strong>
                        <?= nl2br(escape($s['feedback'])) ?>
                    </p>
                    <?php endif; if($teacher): ?>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="grade">
                        <input type="hidden" name="submission_id" value="<?= (int)$s['id'] ?>">
                        <label>
                            Calificación
                            <select name="grade" required>
                                <option value="">
                                    Seleccionar
                                </option>
                                <?php foreach(['AD'=>'Logro destacado','A'=>'Logro esperado','B'=>'En proceso','C'=>'En inicio'] as $grade=>$label): ?>
                                <option value="<?= $grade ?>" <?= $s['grade']===$grade?'selected':'' ?>>
                                    <?= $grade.' · '.$label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Retroalimentación
                            <textarea name="feedback" required maxlength="5000" rows="3"><?= escape($s['feedback']??'') ?></textarea>
                        </label>
                        <button class="button">
                            Guardar nota y avisar a la estudiante
                        </button>
                    </form>
                    <?php endif; ?>
                </article>
                <?php endforeach; if(!$visible): ?>
                <article class="activity">
                    <h2>
                        Aún no hay entregas
                    </h2>
                    <p>
                        Las respuestas enviadas por las estudiantes aparecerán aquí.
                    </p>
                </article>
                <?php endif; ?>
                <?php elseif($page==='usuarios'): ?>
                <?php if($user['role']==='director'): ?>
                <details class="activity">
                    <summary>
                        Crear una cuenta con acceso al aula
                    </summary>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="create_user">
                        <label>
                            Nombres y apellidos
                            <input name="name" required maxlength="120">
                        </label>
                        <label>
                            Correo
                            <input type="email" name="email" required maxlength="200">
                        </label>
                        <label>
                            Contraseña inicial
                            <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
                        </label>
                        <label>
                            Perfil
                            <select name="role">
                                <?php foreach($labels as $key=>$label): ?>
                                <option value="<?= $key ?>">
                                    <?= escape($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Aula (obligatoria para estudiantes)
                            <select name="group_id">
                                <?php foreach($groups as $g): ?>
                                <option value="<?= (int)$g['id'] ?>" <?= (int)$g['id']===$selectedGroup?'selected':'' ?>>
                                    <?= escape($g['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button">
                            Crear cuenta
                        </button>
                    </form>
                </details>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <caption>
                            Cuentas registradas
                        </caption>
                        <thead>
                            <tr>
                                <th>
                                    Nombre
                                </th>
                                <th>
                                    Correo
                                </th>
                                <th>
                                    Perfil
                                </th>
                                <th>
                                    Aula
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach(query('SELECT u.*,g.name AS group_name FROM eval_users u LEFT JOIN eval_groups g ON g.id=u.group_id WHERE '.($staffView?"u.role<>'estudiante'":'u.group_id=?').' ORDER BY u.name',$staffView?[]:[$selectedGroup])->fetchAll() as $u): ?>
                            <tr>
                                <td>
                                    <?= escape($u['name']) ?>
                                </td>
                                <td>
                                    <?= escape($u['email']) ?>
                                </td>
                                <td>
                                    <?= escape($labels[$u['role']]) ?>
                                </td>
                                <td>
                                    <?= escape($u['group_name']??'—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif($page==='estructura'): ?>
                <?php if($user['role']==='director'): ?>
                <details class="activity">
                    <summary>
                        Crear aula
                    </summary>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="create_group">
                        <label>
                            Grado
                            <select name="grade_level">
                                <?php foreach([1,2,3,4,5] as $grade): ?>
                                <option value="<?= $grade ?>">
                                    <?= $grade ?>
                                    °
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Grado y sección
                            <input name="name" maxlength="60" required placeholder="Ej. 4° A">
                        </label>
                        <button class="button">
                            Guardar aula
                        </button>
                    </form>
                </details>
                <details class="activity">
                    <summary>
                        Asignar curso a docente y aula
                    </summary>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="assign_course">
                        <label>
                            Nombre del curso
                            <input name="name" maxlength="100" required>
                        </label>
                        <label>
                            Docente
                            <select name="teacher_id">
                                <?php foreach(query("SELECT id,name FROM eval_users WHERE role='docente' AND active=1")->fetchAll() as $t): ?>
                                <option value="<?= (int)$t['id'] ?>">
                                    <?= escape($t['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Aula
                            <select name="group_id">
                                <?php foreach($groups as $g): ?>
                                <option value="<?= (int)$g['id'] ?>" <?= (int)$g['id']===$selectedGroup?'selected':'' ?>>
                                    <?= escape($g['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button">
                            Asignar curso
                        </button>
                    </form>
                </details>
                <?php endif; ?>
                <div class="table-wrap">
                    <table>
                        <caption>
                            Aulas disponibles:
                            <?= escape(implode(', ',array_column($groups,'name'))) ?>
                        </caption>
                        <thead>
                            <tr>
                                <th>
                                    Curso
                                </th>
                                <th>
                                    Aula
                                </th>
                                <th>
                                    Docente
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $c): ?>
                            <tr>
                                <td>
                                    <?= escape($c['name']) ?>
                                </td>
                                <td>
                                    <?= escape($c['group_name']) ?>
                                </td>
                                <td>
                                    <?= escape($c['teacher']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif($page==='materiales'||$page==='biblioteca'): ?>
                <?php if($teacher): ?>
                <details class="activity">
                    <summary>
                        Publicar material para este salón
                    </summary>
                    <form method="post" enctype="multipart/form-data" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="material">
                        <?php courseSelect($courses); ?>
                        <label>
                            Título
                            <input name="title" required maxlength="200">
                        </label>
                        <label>
                            Contenido (opcional si adjuntas archivo)
                            <textarea name="body" maxlength="10000" rows="5"></textarea>
                        </label>
                        <label>
                            Archivo PDF, JPG, PNG o TXT · máximo 10 MB
                            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.txt">
                        </label>
                        <p>
                            Se publica directamente para las estudiantes del salón.
                        </p>
                        <button class="button">
                            Publicar material
                        </button>
                    </form>
                </details>
                <?php endif; ?>
                <?php foreach($materials as $m): ?>
                <article class="activity">
                    <span class="eyebrow">
                        <?= escape($m['course']) ?>
                        · Publicado
                    </span>
                    <h2>
                        <?= escape($m['title']) ?>
                    </h2>
                    <p>
                        <?= nl2br(escape($m['body'])) ?>
                    </p>
                    <?php if($m['original_name']): ?>
                    <a class="button" href="download.php?id=<?= (int)$m['id'] ?>">
                        Descargar
                        <?= escape($m['original_name']) ?>
                    </a>
                    <?php endif; if($user['role']==='director'): ?>
                    <form method="post" style="margin-top:15px" onsubmit="return confirm('¿Eliminar este material y su archivo adjunto del aula?')">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="delete_material">
                        <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                        <button class="logout-button">
                            Eliminar material
                        </button>
                    </form>
                    <?php endif; ?>
                </article>
                <?php endforeach; if(!$materials): ?>
                <article class="activity">
                    <p>
                        Todavía no hay materiales publicados para este salón.
                    </p>
                </article>
                <?php endif; ?>
                <?php elseif($page==='avisos'): ?>
                <form method="post">
                    <?php csrf(); ?>
                    <input type="hidden" name="action" value="read_notifications">
                    <button class="button">
                        Marcar avisos como leídos
                    </button>
                </form>
                <?php $notifications=query('SELECT * FROM eval_notifications WHERE user_id=? ORDER BY id DESC',[$user['id']])->fetchAll();foreach($notifications as $n): ?>
                <article class="activity">
                    <span class="badge">
                        <?= $n['is_read']?'Leído':'Nuevo' ?>
                    </span>
                    <p>
                        <?= escape($n['message']) ?>
                    </p>
                    <small>
                        <?= escape($n['created_at']) ?>
                    </small>
                </article>
                <?php endforeach; if(!$notifications): ?>
                <p>
                    No tienes avisos todavía.
                </p>
                <?php endif; ?>
                <?php elseif($page==='reportes'): ?>
                <div class="stats">
                    <div>
                        <p>
                            <strong>
                                <?= (int)query('SELECT COUNT(*) FROM eval_users')->fetchColumn() ?>
                            </strong>
                            Cuentas registradas
                        </p>
                    </div>
                    <div>
                        <p>
                            <strong>
                                <?= count($submissions) ?>
                            </strong>
                            Entregas recibidas
                        </p>
                    </div>
                    <div>
                        <p>
                            <strong>
                                <?= count(array_filter($submissions,fn($s)=>$s['grade']!==null)) ?>
                            </strong>
                            Entregas calificadas
                        </p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <caption>
                            Resumen por curso y aula
                        </caption>
                        <thead>
                            <tr>
                                <th>
                                    Curso
                                </th>
                                <th>
                                    Aula
                                </th>
                                <th>
                                    Actividades
                                </th>
                                <th>
                                    Entregas
                                </th>
                                <th>
                                    Calificadas
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $c):$acts=array_filter($activities,fn($a)=>(int)$a['course_id']===(int)$c['id']);$ids=array_column($acts,'id');$subs=array_filter($submissions,fn($s)=>in_array($s['activity_id'],$ids)); ?>
                            <tr>
                                <td>
                                    <?= escape($c['name']) ?>
                                </td>
                                <td>
                                    <?= escape($c['group_name']) ?>
                                </td>
                                <td>
                                    <?= count($acts) ?>
                                </td>
                                <td>
                                    <?= count($subs) ?>
                                </td>
                                <td>
                                    <?= count(array_filter($subs,fn($s)=>$s['grade']!==null)) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif($page==='perfil'): ?>
                <article class="activity">
                    <h2>
                        <?= escape($user['name']) ?>
                    </h2>
                    <p>
                        <?= escape($user['email'].' · '.$user['label']) ?>
                    </p>
                    <form method="post" class="fields">
                        <?php csrf(); ?>
                        <input type="hidden" name="action" value="password">
                        <label>
                            Contraseña actual
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </label>
                        <label>
                            Nueva contraseña
                            <input type="password" name="password" autocomplete="new-password" required minlength="10" maxlength="200">
                        </label>
                        <button class="button">
                            Cambiar contraseña
                        </button>
                    </form>
                </article>
                <?php endif; ?>
                <footer>
                    EVAL
                    <span>
                        Nuestra Señora de las Mercedes · Ayacucho
                    </span>
                </footer>
            </main>
        </div>
    </body>
</html>

