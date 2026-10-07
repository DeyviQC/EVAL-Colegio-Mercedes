param([switch]$OpenBrowser)
$ErrorActionPreference = 'Stop'
$evalRoot = Split-Path $PSScriptRoot -Parent
$evalBackend = Join-Path $evalRoot 'backend'
$evalUrl = 'http://127.0.0.1:8081/'
$evalPhp = @((Join-Path $evalRoot 'php\php.exe'), 'C:\xampp\php\php.exe', 'C:\php\php.exe', (Join-Path $env:USERPROFILE '.config\herd\bin\php84\php.exe')) |
    Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $evalPhp) { $evalPhp = (Get-Command php.exe -ErrorAction Stop).Source }
if (!(Test-Path -LiteralPath (Join-Path $evalBackend 'vendor\autoload.php'))) { throw 'Instala las dependencias con composer install dentro de backend.' }
if (!(Test-Path -LiteralPath (Join-Path $evalBackend '.env'))) { throw 'Configura backend/.env con MySQL y ejecuta php artisan key:generate y las migraciones.' }
if (!(Test-Path -LiteralPath (Join-Path $evalBackend 'public\client\index.html'))) { throw 'Compila React con npm install y npm run build dentro de frontend.' }
# The optional isolated development database is unrelated to the installed MySQL service.
$evalData = Join-Path $evalRoot 'storage\mysql-development\data'
if (Test-Path -LiteralPath $evalData) {
    $evalTcp = [Net.Sockets.TcpClient]::new()
    try { $evalTcp.Connect('127.0.0.1', 3307); $evalMysqlRunning = $true } catch { $evalMysqlRunning = $false } finally { $evalTcp.Dispose() }
    if (-not $evalMysqlRunning) {
        $evalMysql = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe'
        if (!(Test-Path -LiteralPath $evalMysql)) { throw 'No se encontró el ejecutable de MySQL para la base de desarrollo.' }
        $evalMysqlLog = Join-Path $evalRoot 'storage\mysql-development\server.log'
        Start-Process -FilePath $evalMysql -ArgumentList @('--no-defaults', '--skip-log-bin', '--bind-address=127.0.0.1', '--port=3307', '--mysqlx=OFF', ('--datadir="' + $evalData + '"'), ('--log-error="' + $evalMysqlLog + '"')) -WindowStyle Hidden
        Start-Sleep -Seconds 3
    }
}
$evalRunning = $false
try { $evalResponse = Invoke-WebRequest $evalUrl -UseBasicParsing -TimeoutSec 3; $evalRunning = $evalResponse.StatusCode -eq 200 -and $evalResponse.Content.Contains('EVAL') } catch {}
if (-not $evalRunning) {
    $evalLogs = Join-Path $evalRoot 'storage\logs'
    New-Item -ItemType Directory -Path $evalLogs -Force | Out-Null
    $evalPublic = Join-Path $evalBackend 'public'
    $evalRouter = Join-Path $evalBackend 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    $evalUploadTemp = Join-Path $evalBackend 'storage\app\tmp'
    New-Item -ItemType Directory -Path $evalUploadTemp -Force | Out-Null
    Start-Process -FilePath $evalPhp -ArgumentList @('-d','upload_max_filesize=10M','-d','post_max_size=12M','-d',('upload_tmp_dir="'+$evalUploadTemp+'"'),'-S','127.0.0.1:8081',('"'+$evalRouter+'"')) -WorkingDirectory $evalPublic -WindowStyle Hidden -RedirectStandardOutput (Join-Path $evalLogs 'laravel-output.log') -RedirectStandardError (Join-Path $evalLogs 'laravel-error.log')
    Start-Sleep -Seconds 2
    $evalResponse = Invoke-WebRequest $evalUrl -UseBasicParsing -TimeoutSec 10
    if (!$evalResponse.Content.Contains('EVAL')) { throw 'El puerto 8081 pertenece a otra aplicación.' }
}
Write-Output "EVAL (Laravel/MySQL/React): $evalUrl"
$evalSession = Invoke-WebRequest ($evalUrl + 'api/session') -UseBasicParsing -TimeoutSec 10
if ($evalSession.StatusCode -ne 200) { throw 'No se pudo verificar la conexión de la nueva versión.' }
if ($OpenBrowser) { Start-Process $evalUrl }
