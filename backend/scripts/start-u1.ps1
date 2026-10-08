param([switch]$ConfigureRuntime, [ValidateSet('U1','U2','U3','U4','U5','U6','U7', 'U8','U9','U10','Auth')][string]$Unit = 'U1')
$ErrorActionPreference = 'Stop'
$evalRoot = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u1'))
$evalStatePath = Join-Path $evalRoot 'database-state.json'
$evalState = Get-Content -LiteralPath $evalStatePath -Raw | ConvertFrom-Json
$evalExecutable = [IO.Path]::GetFullPath($evalState.executable)
$evalData = [IO.Path]::GetFullPath((Join-Path $evalRoot 'mysql\data'))
if (!$evalExecutable.StartsWith($evalRoot+'\') -or !$evalData.StartsWith($evalRoot+'\') -or $evalState.port -ne 3307) {
    throw 'Only the existing isolated U1 runtime is permitted.'
}
$evalProcess = Get-Process -Id $evalState.pid -ErrorAction SilentlyContinue
if (!$evalProcess) {
    $evalListener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
    if ($evalListener) {
        $evalChild = Get-CimInstance Win32_Process -Filter ('ProcessId='+$evalListener.OwningProcess)
        if ($evalChild.ParentProcessId -ne $evalState.pid -or $evalChild.ExecutablePath -ne $evalExecutable -or !$evalChild.CommandLine.Contains('--datadir='+$evalData)) {
            throw 'Listener is not the child of the owned isolated launcher.'
        }
        $evalProcess = Get-Process -Id $evalChild.ProcessId
        $evalState.pid = $evalProcess.Id
        $evalState.started = $evalProcess.StartTime.ToUniversalTime().Ticks
    }
}
if ($evalProcess) {
    if ($evalProcess.Path -ne $evalExecutable -or $evalProcess.StartTime.ToUniversalTime().Ticks -ne $evalState.started) {
        throw 'Recorded PID is not the owned isolated server.'
    }
    if (!$ConfigureRuntime) { Write-Output 'Owned isolated MySQL already running.'; exit 0 }
    if ($evalState.PSObject.Properties.Name -contains 'launcher_pid') {
        $evalLauncher = Get-Process -Id $evalState.launcher_pid -ErrorAction SilentlyContinue
        if ($evalLauncher) {
            if ($evalLauncher.Path -ne $evalExecutable -or $evalLauncher.StartTime.ToUniversalTime().Ticks -ne $evalState.launcher_started) { throw 'Launcher identity mismatch.' }
            Stop-Process -Id $evalLauncher.Id
        }
    }
    # Restart only the verified disposable server to apply its local bootstrap grants.
    Stop-Process -Id $evalProcess.Id
    $evalProcess.WaitForExit(10000) | Out-Null
    Start-Sleep -Seconds 1
}
if (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) {
    throw 'Port 3307 occupied; never use another server.'
}
$evalArgs = @('--no-defaults', ('--datadir="'+$evalData+'"'), '--bind-address=127.0.0.1', '--port=3307',
    '--mysqlx=OFF', '--skip-log-bin', '--require-secure-transport=ON',
    ('--ssl-ca="'+(Join-Path $evalRoot 'tls\ca.pem')+'"'),
    ('--ssl-cert="'+(Join-Path $evalRoot 'tls\server-cert.pem')+'"'),
    ('--ssl-key="'+(Join-Path $evalRoot 'tls\server-key.pem')+'"'))
if ($ConfigureRuntime) {
    $grants = $Unit.ToLowerInvariant()+'-runtime-grants.sql'
    $evalArgs += '--init-file="'+(Join-Path $PSScriptRoot $grants)+'"'
}
$evalProcess = Start-Process -FilePath $evalExecutable -ArgumentList $evalArgs -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 3
$evalProcess.Refresh()
if ($evalProcess.HasExited) { throw 'Isolated MySQL failed to start; inspect its local error log.' }
$evalState | Add-Member -NotePropertyName launcher_pid -NotePropertyValue $evalProcess.Id -Force
$evalState | Add-Member -NotePropertyName launcher_started -NotePropertyValue $evalProcess.StartTime.ToUniversalTime().Ticks -Force
$evalListener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction Stop
$evalServer = Get-CimInstance Win32_Process -Filter ('ProcessId='+$evalListener.OwningProcess)
if ($evalServer.ExecutablePath -ne $evalExecutable -or !$evalServer.CommandLine.Contains('--datadir='+$evalData) -or ($evalServer.ProcessId -ne $evalProcess.Id -and $evalServer.ParentProcessId -ne $evalProcess.Id)) {
    throw 'Startup listener is not the verified owned server.'
}
$evalProcess = Get-Process -Id $evalServer.ProcessId
$evalState.pid = $evalProcess.Id
$evalState.started = $evalProcess.StartTime.ToUniversalTime().Ticks
[IO.File]::WriteAllText($evalStatePath, ($evalState | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
Write-Output 'Owned isolated MySQL started at 127.0.0.1:3307; run db-smoke to verify TLS.'
