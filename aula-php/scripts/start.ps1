param([switch]$OpenBrowser)
$ErrorActionPreference = 'Stop'
$aulaRoot = Split-Path $PSScriptRoot -Parent
$aulaPublic = Join-Path $aulaRoot 'public'
$aulaLogs = Join-Path $aulaRoot 'storage\logs'
New-Item -ItemType Directory -Path $aulaLogs -Force | Out-Null
$aulaUrl = 'http://127.0.0.1:8080/login.php'
$aulaRunning = $false
try {
    $aulaResponse = Invoke-WebRequest $aulaUrl -UseBasicParsing -TimeoutSec 3
    $aulaRunning = $aulaResponse.StatusCode -eq 200 -and $aulaResponse.Content.Contains('EVAL-NSM')
} catch {}
if (-not $aulaRunning) {
    $aulaCandidates = @(
        (Join-Path $aulaRoot 'php\php.exe'),
        'C:\xampp\php\php.exe',
        'C:\php\php.exe',
        (Join-Path $env:USERPROFILE '.config\herd\bin\php84\php.exe')
    )
    $aulaPhp = $aulaCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
    if (-not $aulaPhp) {
        $aulaCommand = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($aulaCommand) { $aulaPhp = $aulaCommand.Source }
        else { throw 'Instala PHP 8.2 o superior (por ejemplo con XAMPP), o coloca PHP en la carpeta php de este proyecto.' }
    }
    $aulaRouter = Join-Path $PSScriptRoot 'router.php'
    Start-Process -FilePath $aulaPhp -ArgumentList @(
        '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M',
        '-S', '127.0.0.1:8080', '-t', ('"' + $aulaPublic + '"'), ('"' + $aulaRouter + '"')
    ) -WorkingDirectory $aulaRoot -WindowStyle Hidden `
      -RedirectStandardOutput (Join-Path $aulaLogs 'server-output.log') `
      -RedirectStandardError (Join-Path $aulaLogs 'server-error.log')
    Start-Sleep -Seconds 2
    $aulaResponse = Invoke-WebRequest $aulaUrl -UseBasicParsing -TimeoutSec 10
    if (-not $aulaResponse.Content.Contains('EVAL-NSM')) { throw 'El puerto 8080 no está sirviendo EVAL-NSM.' }
}
Write-Output "EVAL-NSM disponible en $aulaUrl"
if ($OpenBrowser) { Start-Process $aulaUrl }
