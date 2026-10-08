$ErrorActionPreference='Stop'
$base='http://127.0.0.1:8080'
function Assert($value,$message) { if(!$value){throw $message}; Write-Host "OK: $message" }
function Login($email) {
 $page=Invoke-WebRequest -TimeoutSec 10 "$base/login.php" -SessionVariable loginSession
 $token=[regex]::Match($page.Content,'name="csrf" value="([^"]+)"').Groups[1].Value
 $result=Invoke-WebRequest -TimeoutSec 10 "$base/login.php" -Method Post -WebSession $loginSession -Body @{csrf=$token;email=$email;password='Mercedes2026!'}
 Assert ($result.Content.Contains('Cerrar sesión')) "Login $email"
 return @{Session=$loginSession;Token=$token}
}
function Post($account,$page,$body){$body.csrf=$account.Token;return Invoke-WebRequest -TimeoutSec 10 "$base/?page=$page&group=1" -Method Post -WebSession $account.Session -Body $body}
$t=Login 'docente@eval.test';$s=Login 'estudiante@eval.test';$d=Login 'director@eval.test';$sd=Login 'subdirector@eval.test'
$title='Prueba integrada '+[guid]::NewGuid().ToString('N').Substring(0,8)
$r=Post $t 'actividades' @{action='create_activity';course_id='1';title=$title;description='Resuelve y explica';due_date=(Get-Date).AddDays(2).ToString('yyyy-MM-dd')}
Assert ($r.Content.Contains($title)) 'Docente publica actividad'
$studentPage=Invoke-WebRequest -TimeoutSec 10 "$base/?page=actividades" -WebSession $s.Session
Assert ($studentPage.Content.Contains($title)) 'Otra sesión estudiante ve actividad'
$pattern='(?s)<h2>'+[regex]::Escape($title)+'.*?name="activity_id" value="(\d+)"'
$id=[regex]::Match($studentPage.Content,$pattern).Groups[1].Value
Assert ($id -ne '') 'Actividad identificada'
$r=Post $s 'actividades' @{action='submit';activity_id=$id;answer='Respuesta integrada <script>test</script>'}
Assert ($r.Content.Contains('Respuesta integrada &lt;script&gt;')) 'Entrega y escape HTML'
$teacherPage=Invoke-WebRequest -TimeoutSec 10 "$base/?page=entregas&group=1&activity=$id" -WebSession $t.Session
Assert ($teacherPage.Content.Contains('Respuesta integrada')) 'Docente ve entrega de otra sesión'
$submissionId=[regex]::Match($teacherPage.Content,'name="submission_id" value="(\d+)"').Groups[1].Value
$r=Post $s 'actividades' @{action='grade';submission_id=$submissionId;grade='AD';feedback='No permitido'}
Assert ($r.Content.Contains('no tiene permiso')) 'Estudiante no puede calificar'
$r=Post $t 'entregas' @{action='grade';submission_id=$submissionId;grade='A';feedback='Buen trabajo integrado'}
Assert ($r.Content.Contains('Buen trabajo integrado')) 'Docente califica'
$notes=Invoke-WebRequest -TimeoutSec 10 "$base/?page=notas" -WebSession $s.Session
Assert ($notes.Content.Contains('Buen trabajo integrado') -and $notes.Content.Contains('Nota A')) 'Estudiante ve nota y retroalimentación'
$r=Post $s 'actividades' @{action='submit';activity_id=$id;answer='Cambiar después de nota'}
Assert ($r.Content.Contains('ya fue calificada')) 'Entrega calificada protegida'
$r=Post $t 'actividades' @{action='close_activity';activity_id=$id}
$report=Invoke-WebRequest -TimeoutSec 10 "$base/?page=reportes&group=1" -WebSession $d.Session
Assert ($report.Content.Contains('Entregas calificadas')) 'Dirección ve reporte'
$notify=Invoke-WebRequest -TimeoutSec 10 "$base/?page=avisos" -WebSession $s.Session
Assert ($notify.Content.Contains('Calificación A en: '+$title)) 'Notificación de nota'
$r=Post $sd 'usuarios' @{action='create_user';name='No permitido';email='blocked@eval.test';password='Password123!';role='docente'}
Assert ($r.Content.Contains('no tiene permiso')) 'Subdirección no administra credenciales'
$r=Post $t 'materiales' @{action='material';course_id='1';title=$title+' recurso';body='Lectura integrada'}
$review=Invoke-WebRequest -TimeoutSec 10 "$base/?page=materiales&group=1" -WebSession $d.Session
$materialId=[regex]::Match($review.Content,'name="material_id" value="(\d+)"').Groups[1].Value
$library=Invoke-WebRequest -TimeoutSec 10 "$base/?page=biblioteca" -WebSession $s.Session
Assert ($library.Content.Contains($title+' recurso')) 'Material visible sin aprobación'
$r=Post $sd 'materiales' @{action='delete_material';material_id=$materialId}
Assert ($r.Content.Contains('no tiene permiso')) 'Subdirección no elimina material'
$r=Post $d 'materiales' @{action='delete_material';material_id=$materialId}
$library=Invoke-WebRequest -TimeoutSec 10 "$base/?page=biblioteca" -WebSession $s.Session
Assert (!$library.Content.Contains($title+' recurso')) 'Dirección elimina material publicado'
$new=Invoke-WebRequest -TimeoutSec 10 "$base/login.php" -SessionVariable newSession
$newToken=[regex]::Match($new.Content,'name="csrf" value="([^"]+)"').Groups[1].Value
$account=Invoke-WebRequest -TimeoutSec 10 "$base/login.php" -WebSession $newSession -Method Post -Body @{csrf=$newToken;email='alumna.3b.10@eval.test';password='Mercedes2026!'}
Assert ($account.Content.Contains('Cerrar sesión')) 'Estudiante de otro salón puede iniciar sesión'
$isolated=Invoke-WebRequest -TimeoutSec 10 "$base/?page=actividades" -WebSession $newSession
Assert (!$isolated.Content.Contains($title)) 'Aula distinta no ve actividad'
$denied=Invoke-WebRequest -TimeoutSec 10 "$base/?page=actividades" -WebSession $newSession -Method Post -Body @{csrf=$newToken;action='submit';activity_id=$id;answer='Invasión'}
Assert ($denied.Content.Contains('no tiene permiso')) 'No se puede entregar en aula ajena'
$bad=Invoke-WebRequest -TimeoutSec 10 "$base/?page=actividades" -WebSession $s.Session -Method Post -Body @{csrf='incorrecto';action='submit';activity_id=$id;answer='Prueba'}
Assert ($bad.Content.Contains('Formulario vencido')) 'CSRF rechazado'
Write-Output 'Flujo completo verificado. Los registros de prueba quedan identificados como Prueba integrada.'





