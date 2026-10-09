param(
    [ValidateSet('prepare','start','status','stop','open','accounts','snapshot','seed-school-sample','verify','verify-enrollments','verify-assignments','verify-users','verify-activities','verify-assessments','verify-reports','verify-notifications','verify-history','verify-activity-recovery','verify-delivery-context','verify-library','verify-write-recovery','verify-recorded-time','verify-material-maintenance','verify-observations','verify-file-replacement','verify-tablet-interface','verify-school-sample','calendar-maintenance','verify-calendar','resource-week-maintenance','verify-weeks','verify-week-reads','configure-calendar-demo')][string]$Command='status',
    [switch]$Maintenance,
    [switch]$BrowserOnly,
    [switch]$Sample,
    [ValidateSet('director_admin','vice_principal','teacher','student')][string]$Role='director_admin'
)
$ErrorActionPreference='Stop'
if($BrowserOnly -and $Command -notin @('verify-reports','verify-notifications','verify-library','verify-material-maintenance','verify-observations','verify-file-replacement','verify-tablet-interface','verify-school-sample')){throw 'BrowserOnly is supported only for reports, notifications, library, material maintenance, observations and file replacement.'}
$repo=[IO.Path]::GetFullPath((Split-Path (Split-Path $PSScriptRoot)))
$ownedRoot=[IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u1'))
$devRoot=Join-Path $repo '.local\eval-dev'
$stage='private_paths'
$sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
function Protect-Path([string]$Path,[bool]$Directory=$false) {
    $item=Get-Item -LiteralPath $Path
    if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Private path cannot be a link.'}
    $acl=Get-Acl -LiteralPath $Path
    if($acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $sid.Value){throw 'Private path must belong to the current user.'}
    $acl.SetAccessRuleProtection($true,$false)
    foreach($oldRule in @($acl.GetAccessRules($true,$false,[Security.Principal.SecurityIdentifier]))){$acl.RemoveAccessRuleSpecific($oldRule)}
    foreach($id in @($sid.Value,'S-1-5-18','S-1-5-32-544')){
        $principal=New-Object Security.Principal.SecurityIdentifier($id)
        $inherit=if($Directory){[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'}else{[Security.AccessControl.InheritanceFlags]::None}
        $rule=New-Object Security.AccessControl.FileSystemAccessRule($principal,[Security.AccessControl.FileSystemRights]::FullControl,$inherit,[Security.AccessControl.PropagationFlags]::None,[Security.AccessControl.AccessControlType]::Allow)
        $acl.AddAccessRule($rule)
    }
    if($Directory){[IO.Directory]::SetAccessControl($Path,$acl)}else{[IO.File]::SetAccessControl($Path,$acl)}
}
function Assert-Private([string]$Path){
    $item=Get-Item -LiteralPath $Path
    if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Private path cannot be a link.'}
    $acl=Get-Acl -LiteralPath $Path
    if(!$acl.AreAccessRulesProtected -or $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $sid.Value){throw 'Private ACL required.'}
    foreach($rule in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){
        if($rule.AccessControlType -eq 'Allow' -and $rule.IdentityReference.Value -notin @($sid.Value,'S-1-5-18','S-1-5-32-544')){throw 'Unexpected private access.'}
    }
}
function Secret {
    $bytes=New-Object byte[] 32;$rng=[Security.Cryptography.RandomNumberGenerator]::Create()
    try{$rng.GetBytes($bytes)}finally{$rng.Dispose()}
    return ([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant()
}
function Encrypt([string]$Value){return (ConvertTo-SecureString $Value -AsPlainText -Force | ConvertFrom-SecureString)}
function Decrypt([string]$Value){
    $secure=ConvertTo-SecureString $Value;$pointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try{return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)}finally{[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer);$secure.Dispose()}
}
function Save-State($Value){
    [IO.File]::WriteAllText($statePath,($Value|ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false));Protect-Path $statePath
}
function Owned-Process($Record){
    if(!$Record){return $null}
    $process=Get-Process -Id $Record.pid -ErrorAction SilentlyContinue
    if(!$process){return $null}
    # Recover the initial Node recording race only with the exact original start,
    # known executable, current owner and exact repository helper marker below.
    if([string]::IsNullOrEmpty($Record.executable) -and $process.StartTime.ToUniversalTime().Ticks.ToString() -eq [string]$Record.started){
        $nodeMarkers=@((Join-Path $repo 'backend\dev-runtime\https-proxy.mjs'),(Join-Path $repo 'backend\dev-runtime\open-browser.mjs'))
        if($Record.marker -notin $nodeMarkers -or $process.Path -ne (Get-Command node.exe).Source){throw 'Incomplete process record cannot be recovered.'}
        $Record.executable=$process.Path
    }
    if($process.Path -ne $Record.executable -or $process.StartTime.ToUniversalTime().Ticks.ToString() -ne [string]$Record.started){throw ('Recorded process identity changed: actual='+[IO.Path]::GetFileName($process.Path)+'; recorded='+[IO.Path]::GetFileName($Record.executable)+'; start_match='+($process.StartTime.ToUniversalTime().Ticks.ToString() -eq [string]$Record.started)+'.')}
    $info=Get-CimInstance Win32_Process -Filter ('ProcessId='+$process.Id)
    $owner=Invoke-CimMethod -InputObject $info -MethodName GetOwnerSid
    if($owner.ReturnValue -ne 0 -or $owner.Sid -ne $sid.Value -or !$info.CommandLine.Replace('"','').Contains($Record.marker)){throw 'Recorded process ownership changed.'}
    return $process
}
function Record-Process($Process,[string]$Marker){return @{pid=$Process.Id;started=$Process.StartTime.ToUniversalTime().Ticks.ToString();executable=[IO.Path]::GetFullPath($Process.StartInfo.FileName);marker=$Marker}}
function Start-Child([string]$Exe,[string]$Arguments,$Environment,[string]$Log,$InputText=$null,[bool]$Wait=$false){
    $info=New-Object Diagnostics.ProcessStartInfo;$info.FileName=$Exe;$info.Arguments=$Arguments;$info.WorkingDirectory=$repo
    $info.UseShellExecute=$false;$info.CreateNoWindow=$true;$info.EnvironmentVariables.Clear()
    foreach($name in @('SystemRoot','TEMP','TMP','LOCALAPPDATA','PATH')){$info.EnvironmentVariables[$name]=[Environment]::GetEnvironmentVariable($name)}
    foreach($name in $Environment.Keys){$info.EnvironmentVariables[$name]=[string]$Environment[$name]}
    $info.RedirectStandardOutput=$Wait;$info.RedirectStandardError=$Wait
    $info.RedirectStandardInput=$null -ne $InputText
    $process=New-Object Diagnostics.Process;$process.StartInfo=$info
    [void]$process.Start();$info.EnvironmentVariables.Clear()
    if($null -ne $InputText){$process.StandardInput.Write($InputText);$process.StandardInput.Close()}
    if($Wait){
        $output=$process.StandardOutput.ReadToEnd();$errors=$process.StandardError.ReadToEnd();$process.WaitForExit()
        if($process.ExitCode -ne 0){throw ('Owned child failed at '+$stage+'; '+($errors -replace '[\r\n]+',' ').Substring(0,[Math]::Min(220,($errors -replace '[\r\n]+',' ').Length)))}
        return $output
    }
    return $process
}
function Db-Environment([string]$Role){
    return @{EVAL_VENDOR_DIR=(Join-Path $ownedRoot 'vendor');EVAL_UNIT='Dev';EVAL_DB_HOST='127.0.0.1';EVAL_DB_PORT='3307';EVAL_DB_NAME='eval_dev';EVAL_DB_USER=('eval_dev_'+$Role);EVAL_DB_PASSWORD=(Decrypt $state.$Role);EVAL_DB_CA=(Join-Path $ownedRoot 'tls\ca.pem');EVAL_DB_ROLE=$Role}
}
function Verify-MySQL {
    $existingPath=Join-Path $ownedRoot 'database-state.json';Assert-Private $existingPath
    $existing=[IO.File]::ReadAllText($existingPath)|ConvertFrom-Json
    $expectedExe=[IO.Path]::GetFullPath($existing.executable)
    if(!$expectedExe.StartsWith((Join-Path $ownedRoot 'mysql')+'\',[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetFileName($expectedExe) -ne 'mysqld.exe' -or $existing.port -ne 3307){throw 'Unexpected MySQL binding.'}
    $pathCheck=$expectedExe
    while($pathCheck.Length -ge $ownedRoot.Length){
        if((Get-Item -LiteralPath $pathCheck).Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Owned MySQL path cannot be a link.'}
        if($pathCheck -eq $ownedRoot){break};$pathCheck=Split-Path $pathCheck
    }
    $record=@{pid=$existing.pid;started=[string]$existing.started;executable=$existing.executable;marker=('--datadir='+(Join-Path $ownedRoot 'mysql\data'))}
    $mysql=Owned-Process $record
    if(!$mysql -and $Command -eq 'prepare' -and $Maintenance -and $state -and !$state.prepared -and (Test-Path -LiteralPath (Join-Path $devRoot 'maintenance.sql'))){
        $recoveryListener=@(Get-NetTCPConnection -LocalPort 3307 -State Listen)
        if($recoveryListener.Count -ne 1 -or $recoveryListener[0].LocalAddress -ne '127.0.0.1'){throw 'Ambiguous maintenance recovery listener.'}
        $recovery=Get-CimInstance Win32_Process -Filter ('ProcessId='+$recoveryListener[0].OwningProcess)
        $recoveryOwner=Invoke-CimMethod -InputObject $recovery -MethodName GetOwnerSid
        if($recovery.ExecutablePath -ne $expectedExe -or $recoveryOwner.Sid -ne $sid.Value -or !$recovery.CommandLine.Replace('"','').Contains('--datadir='+(Join-Path $ownedRoot 'mysql\data')) -or !$recovery.CommandLine.Contains((Join-Path $devRoot 'maintenance.sql'))){throw 'Maintenance recovery identity mismatch.'}
        $mysql=Get-Process -Id $recovery.ProcessId
        $existing.pid=$mysql.Id;$existing.started=$mysql.StartTime.ToUniversalTime().Ticks
        $parent=Get-Process -Id $recovery.ParentProcessId -ErrorAction SilentlyContinue
        if($parent -and $parent.Path -eq $expectedExe){
            $existing|Add-Member launcher_pid $parent.Id -Force
            $existing|Add-Member launcher_started $parent.StartTime.ToUniversalTime().Ticks -Force
        }
        [IO.File]::WriteAllText($existingPath,($existing|ConvertTo-Json),[Text.UTF8Encoding]::new($false));Protect-Path $existingPath
    }
    if(!$mysql -and $Command -eq 'start'){
        if(Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue){throw 'Foreign MySQL listener is retained.'}
        & (Join-Path $PSScriptRoot 'start-u1.ps1')
        if($LASTEXITCODE -ne 0){throw 'Owned MySQL startup failed.'}
        return Verify-MySQL
    }
    if(!$mysql){throw 'Owned MySQL is not running.'}
    $listener=@(Get-NetTCPConnection -LocalPort 3307 -State Listen)
    if($listener.Count -ne 1 -or $listener[0].LocalAddress -ne '127.0.0.1' -or $listener[0].OwningProcess -ne $mysql.Id){throw 'Unexpected MySQL listener.'}
    return @{state=$existing;process=$mysql;path=$existingPath}
}
try {
    if(!(Test-Path -LiteralPath (Join-Path $repo '.local'))){[void](New-Item -ItemType Directory -Path (Join-Path $repo '.local'))}
    Protect-Path (Join-Path $repo '.local') $true
    if(!(Test-Path -LiteralPath $devRoot)){[void](New-Item -ItemType Directory -Path $devRoot)}
    Protect-Path $devRoot $true
    $lock=[IO.File]::Open((Join-Path $devRoot 'operation.lock'),[IO.FileMode]::OpenOrCreate,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None)
    $statePath=Join-Path $devRoot 'state.json'
    $php=Join-Path $ownedRoot 'php\php.exe';$ini=Join-Path $ownedRoot 'php.ini';$node=(Get-Command node.exe).Source
    if(Test-Path -LiteralPath $statePath){Assert-Private $statePath;$state=[IO.File]::ReadAllText($statePath)|ConvertFrom-Json}
    else{$state=$null}
    if($Command -eq 'prepare'){
        $stage='maintenance_authority';if(!$Maintenance -and !$state){throw 'First preparation requires explicit -Maintenance.'}
        $owned=Verify-MySQL
        if(!$state){
            $state=[pscustomobject]@{database='eval_dev';origin='https://127.0.0.1:8443';upstream=18080;migration=(Encrypt (Secret));runtime=(Encrypt (Secret));key=(Encrypt ([Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes((Secret).Substring(0,32)))));passwords=@{};prepared=$false;workers=@()}
            foreach($role in @('director_admin','vice_principal','teacher','student')){$state.passwords[$role]=Encrypt ((Secret).Substring(0,24))}
            Save-State $state
        }
        if(!$state.prepared){
            $stage='maintenance_authority';if(!$Maintenance){throw 'Recovery preparation requires explicit -Maintenance.'}
            $stage='active_test_check'
            $active=@(Get-CimInstance Win32_Process | Where-Object {$_.Name -eq 'php.exe' -and $_.ExecutablePath -eq $php -and $_.CommandLine -like '*phpunit*'})
            if($active.Count){throw 'Active test or preview must finish before MySQL maintenance.'}
            $stage='maintenance_sql'
            $migration=Decrypt $state.migration;$runtime=Decrypt $state.runtime
            $sql="CREATE DATABASE IF NOT EXISTS eval_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;`nCREATE USER IF NOT EXISTS 'eval_dev_migration'@'127.0.0.1' IDENTIFIED BY '$migration' REQUIRE SSL;`nCREATE USER IF NOT EXISTS 'eval_dev_runtime'@'127.0.0.1' IDENTIFIED BY '$runtime' REQUIRE SSL;`nGRANT ALL PRIVILEGES ON eval_dev.* TO 'eval_dev_migration'@'127.0.0.1' WITH GRANT OPTION;`nGRANT SELECT ON eval_dev.* TO 'eval_dev_runtime'@'127.0.0.1';`n"
            $init=Join-Path $devRoot 'maintenance.sql';[IO.File]::WriteAllText($init,$sql,[Text.UTF8Encoding]::new($false));Protect-Path $init
            $migration=$null;$runtime=$null;$sql=$null
            $stage='mysql_restart'
            $owned=Verify-MySQL
            $launcher=$null
            if($owned.state.launcher_pid -and $owned.state.launcher_pid -ne $owned.process.Id){
                $launcherRecord=@{pid=$owned.state.launcher_pid;started=[string]$owned.state.launcher_started;executable=$owned.state.executable;marker=('--datadir='+(Join-Path $ownedRoot 'mysql\data'))}
                $launcher=Owned-Process $launcherRecord
            }
            if($launcher){Stop-Process -Id $launcher.Id}
            Stop-Process -Id $owned.process.Id;$owned.process.WaitForExit(10000)|Out-Null
            $arguments='--no-defaults --datadir="'+(Join-Path $ownedRoot 'mysql\data')+'" --bind-address=127.0.0.1 --port=3307 --mysqlx=OFF --skip-log-bin --require-secure-transport=ON --ssl-ca="'+(Join-Path $ownedRoot 'tls\ca.pem')+'" --ssl-cert="'+(Join-Path $ownedRoot 'tls\server-cert.pem')+'" --ssl-key="'+(Join-Path $ownedRoot 'tls\server-key.pem')+'" --init-file="'+$init+'"'
            $launched=Start-Process -FilePath $owned.state.executable -ArgumentList $arguments -WindowStyle Hidden -PassThru
            $deadline=[DateTime]::UtcNow.AddSeconds(15);$listener=$null
            do{Start-Sleep -Milliseconds 200;$listener=Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue}while(!$listener -and [DateTime]::UtcNow -lt $deadline)
            if(!$listener -or $listener.LocalAddress -ne '127.0.0.1'){throw 'Owned MySQL restart did not become ready.'}
            $server=Get-Process -Id $listener.OwningProcess
            $owned.state.pid=$server.Id;$owned.state.started=$server.StartTime.ToUniversalTime().Ticks
            $owned.state|Add-Member -NotePropertyName launcher_pid -NotePropertyValue $launched.Id -Force
            $owned.state|Add-Member -NotePropertyName launcher_started -NotePropertyValue $launched.StartTime.ToUniversalTime().Ticks -Force
            [IO.File]::WriteAllText($owned.path,($owned.state|ConvertTo-Json),[Text.UTF8Encoding]::new($false));Protect-Path $owned.path
            $null=Verify-MySQL
        }
        $stage='provision'
        $passwords=@{};foreach($role in @('director_admin','vice_principal','teacher','student')){$passwords[$role]=Decrypt $state.passwords.$role}
        $result=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\provision.php')+'"') (Db-Environment 'migration') '' ($passwords|ConvertTo-Json -Compress) $true
        if(Test-Path -LiteralPath (Join-Path $devRoot 'maintenance.sql')){Remove-Item -LiteralPath (Join-Path $devRoot 'maintenance.sql')}
        $state.prepared=$true;Save-State $state;Write-Output $result.Trim()
    } elseif(!$state -or !$state.prepared){throw 'Run prepare -Maintenance first.'}
    elseif($Command -eq 'resource-week-maintenance'){
        $stage='resource_week_authority';if(!$Maintenance){throw 'Resource week maintenance requires explicit -Maintenance.'}
        $null=Verify-MySQL
        $stage='resource_week_maintenance'
        Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\resource-week-maintenance.php')+'"') (Db-Environment 'migration') '' $null $true).Trim()
    }
    elseif($Command -eq 'calendar-maintenance'){
        $stage='calendar_authority';if(!$Maintenance){throw 'Calendar maintenance requires explicit -Maintenance.'}
        $null=Verify-MySQL
        $stage='calendar_maintenance'
        Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\calendar-maintenance.php')+'"') (Db-Environment 'migration') '' $null $true).Trim()
    } elseif($Command -in @('verify-calendar','verify-weeks','verify-week-reads','configure-calendar-demo')){
        $stage='calendar_runtime_check';$null=Verify-MySQL
        if(@($state.workers | Where-Object {Owned-Process $_}).Count -ne 2){throw 'Start EVAL before calendar verification.'}
        $passwords=@{};foreach($role in @('director_admin','teacher','student')){$passwords[$role]=Decrypt $state.passwords.$role}
        $verifyEnvironment=@{EVAL_DEV_ROOT=$devRoot;PLAYWRIGHT_BROWSERS_PATH=(Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u12-browser')}
        $stage='calendar_browser_verification'
        Write-Output (Start-Child $node ('"'+(Join-Path $repo $(if($Command -eq 'configure-calendar-demo'){'backend\dev-runtime\configure-calendar-demo.mjs'}elseif($Command -eq 'verify-week-reads'){'backend\dev-runtime\verify-week-reads.mjs'}elseif($Command -eq 'verify-weeks'){'backend\dev-runtime\verify-weeks.mjs'}else{'backend\dev-runtime\verify-calendar.mjs'}))+'"') $verifyEnvironment '' ($passwords|ConvertTo-Json -Compress) $true).Trim()
    }
    elseif($Command -eq 'snapshot'){
        $stage='schema_verification';$null=Verify-MySQL
        Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\inspect.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
    } elseif($Command -eq 'seed-school-sample'){
        $stage='school_sample';$null=Verify-MySQL
        Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\seed-school-sample.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
    } elseif($Command -eq 'status'){
        $running=@($state.workers | Where-Object {Owned-Process $_})
        Save-State $state
        Write-Output ('EVAL database: eval_dev; URL: '+$state.origin+'; app running: '+($running.Count -eq 2))
        Write-Output ('Owned browser sessions: '+@($state.browsers | Where-Object {$_ -and (Owned-Process $_)}).Count)
    } elseif($Command -eq 'stop'){
        foreach($browserRecord in @($state.browsers)){
            $browserProcess=Owned-Process $browserRecord
            if($browserProcess){[IO.File]::WriteAllText((Join-Path $devRoot ('browser-stop-'+$browserRecord.role)), 'stop');if(!$browserProcess.WaitForExit(10000)){throw 'Owned browser did not close; process retained.'}}
        }
        foreach($record in @($state.workers)){$process=Owned-Process $record;if($process){Stop-Process -Id $process.Id}}
        if($state.PSObject.Properties.Name -contains 'browser'){$process=Owned-Process $state.browser;if($process){Stop-Process -Id $process.Id};$state.browser=$null}
        $state.workers=@();$state|Add-Member -NotePropertyName browsers -NotePropertyValue @() -Force;Save-State $state;Write-Output 'EVAL stopped. MySQL, academic data and files retained.'
    } elseif($Command -eq 'start'){
        $stage='start_checks';$null=Verify-MySQL
        $null=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\inspect.php')+'"') (Db-Environment 'runtime') '' $null $true
        foreach($record in @($state.workers)){if(Owned-Process $record){throw 'EVAL already has owned workers; use status or stop.'}}
        if(!(Test-Path -LiteralPath (Join-Path $repo 'frontend\dist\index.html'))){throw 'Build frontend first.'}
        foreach($port in @(8443,18080)){if(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue){throw 'Application port is occupied; foreign process is retained.'}}
        $stage='certificate';if(!(Test-Path -LiteralPath (Join-Path $devRoot 'cert.pem'))){$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\certificate.php')+'" "'+$devRoot+'"') @{} '' $null $true}
        $secret=Secret;$environment=Db-Environment 'runtime';$environment.EVAL_PROXY_KEY=$secret;$environment.EVAL_RUNTIME_KEY=Decrypt $state.key;$environment.EVAL_RUNTIME_ORIGIN=$state.origin;$environment.EVAL_UI_ENABLED='1';$environment.EVAL_MATERIAL_ROOT=Join-Path $devRoot 'materials'
        $router=Join-Path $repo 'backend\dev-runtime\router.php';$proxy=Join-Path $repo 'backend\dev-runtime\https-proxy.mjs'
        $stage='php_start';$worker=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 -d upload_max_filesize=25M -d post_max_size=27M -S 127.0.0.1:18080 "'+$router+'"') $environment ''
        $state.workers=@((Record-Process $worker $router));Save-State $state
        $stage='proxy_start';$worker=Start-Child $node ('"'+$proxy+'" 8443 18080 "'+(Join-Path $devRoot 'cert.pem')+'" "'+(Join-Path $devRoot 'key.pem')+'"') @{EVAL_PROXY_KEY=$secret;EVAL_UI_ENABLED='1'} ''
        $state.workers+=(Record-Process $worker $proxy);Save-State $state
        $deadline=[DateTime]::UtcNow.AddSeconds(8)
        do{Start-Sleep -Milliseconds 200;$ready=Get-NetTCPConnection -LocalPort 8443 -State Listen -ErrorAction SilentlyContinue}while(!$ready -and [DateTime]::UtcNow -lt $deadline)
        if(!$ready){throw 'HTTPS listener unavailable.'}
        Write-Output ('EVAL started: '+$state.origin)
    } elseif($Command -eq 'open'){
        $stage='browser_open';$helper=Join-Path $repo 'backend\dev-runtime\open-browser.mjs'
        if(@($state.workers | Where-Object {Owned-Process $_}).Count -ne 2){throw 'Start EVAL before opening.'}
        foreach($browserRecord in @($state.browsers)){
            if($browserRecord -and $browserRecord.role -eq $Role -and (Owned-Process $browserRecord)){throw 'An EVAL browser for this role is already open.'}
        }
        $browserEnvironment=@{EVAL_DEV_ROOT=$devRoot;EVAL_OPEN_SAMPLE=$(if($Sample){'1'}else{'0'});PLAYWRIGHT_BROWSERS_PATH=(Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u12-browser')}
        $login= switch($Role){'director_admin'{'director'} 'vice_principal'{'subdirector'} 'teacher'{'docente'} 'student'{'estudiante'}}
        $account=@{role=$Role;login=$login;password=(Decrypt $state.passwords.$Role)}|ConvertTo-Json -Compress
        $readyPath=Join-Path $devRoot ('browser-ready-'+$Role)
        if(Test-Path -LiteralPath $readyPath){Remove-Item -LiteralPath $readyPath}
        $worker=Start-Child $node ('"'+$helper+'"') $browserEnvironment '' $account
        $record=Record-Process $worker $helper;$record.role=$Role
        $browsers=@($state.browsers | Where-Object {$_ -and (Owned-Process $_)})+@($record)
        $state|Add-Member -NotePropertyName browsers -NotePropertyValue $browsers -Force;Save-State $state
        $deadline=[DateTime]::UtcNow.AddSeconds(20)
        do{Start-Sleep -Milliseconds 200;$worker.Refresh();if($worker.HasExited){throw 'Owned browser failed to open; no trust settings were changed.'}}while(!(Test-Path -LiteralPath $readyPath) -and [DateTime]::UtcNow -lt $deadline)
        if(!(Test-Path -LiteralPath $readyPath)){throw 'Browser authentication was not confirmed.'}
        Write-Output ('EVAL opened and authenticated: '+$Role)
    } elseif($Command -in @('verify','verify-enrollments','verify-assignments','verify-users','verify-activities','verify-assessments','verify-reports','verify-notifications','verify-history','verify-activity-recovery','verify-delivery-context','verify-library','verify-write-recovery','verify-recorded-time','verify-material-maintenance','verify-observations','verify-file-replacement','verify-tablet-interface','verify-school-sample')){
        if($Command -eq 'verify-activities'){
            $stage='activity_transaction_fixture';$null=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\activity-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{$stage='activity_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-activities-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()}
            finally{$stage='activity_transaction_cleanup';$null=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\activity-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-assessments'){
            $stage='assessment_fixture';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\assessment-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{
            $stage='assessment_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-assessments-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
            }finally{$stage='assessment_fixture_cleanup';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\assessment-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-notifications' -and !$BrowserOnly){
            $stage='notification_fixture';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\notification-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{$stage='notification_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-notifications-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()}
            finally{$stage='notification_fixture_cleanup';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\notification-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-reports' -and !$BrowserOnly){
            $stage='report_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-reports-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
        }
        if($Command -eq 'verify-library' -and !$BrowserOnly){
            $stage='library_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-library-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
        }
        if($Command -eq 'verify-material-maintenance' -and !$BrowserOnly){
            $stage='material_rollback_fixture';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\material-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{$stage='material_maintenance_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-material-maintenance-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()}
            finally{$stage='material_rollback_cleanup';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\material-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-observations' -and !$BrowserOnly){
            $stage='observation_rollback_fixture';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\observation-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{$stage='observation_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-observations-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()}
            finally{$stage='observation_rollback_cleanup';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\observation-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-file-replacement' -and !$BrowserOnly){
            $stage='file_rollback_fixture';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\file-rollback-fixture.php')+'" prepare') (Db-Environment 'migration') '' $null $true
            try{$stage='file_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-file-replacement-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()}
            finally{$stage='file_rollback_cleanup';$null=Start-Child $php ('-c "'+$ini+'" "'+(Join-Path $repo 'backend\dev-runtime\file-rollback-fixture.php')+'" remove') (Db-Environment 'migration') '' $null $true}
        }
        if($Command -eq 'verify-users'){
            $stage='account_server_verification';Write-Output (Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\verify-users-server.php')+'"') (Db-Environment 'runtime') '' $null $true).Trim()
        }
        if($Command -eq 'verify-enrollments'){
            $stage='enrollment_fixture';$null=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\enrollment-fixture.php')+'"') (Db-Environment 'migration') '' $null $true
        }
        if($Command -eq 'verify-assignments'){
            $stage='assignment_fixture';$null=Start-Child $php ('-c "'+$ini+'" -d display_errors=0 "'+(Join-Path $repo 'backend\dev-runtime\assignment-fixture.php')+'"') (Db-Environment 'migration') '' $null $true
        }
        $stage='browser_verification';$helper=Join-Path $repo $(if($Command -eq 'verify-enrollments'){'backend\dev-runtime\verify-enrollments.mjs'}elseif($Command -eq 'verify-assignments'){'backend\dev-runtime\verify-assignments.mjs'}elseif($Command -eq 'verify-users'){'backend\dev-runtime\verify-users.mjs'}elseif($Command -eq 'verify-activities'){'backend\dev-runtime\verify-activities.mjs'}elseif($Command -eq 'verify-assessments'){'backend\dev-runtime\verify-assessments.mjs'}elseif($Command -eq 'verify-reports'){'backend\dev-runtime\verify-reports.mjs'}elseif($Command -eq 'verify-notifications'){'backend\dev-runtime\verify-notifications.mjs'}elseif($Command -eq 'verify-history'){'backend\dev-runtime\verify-history.mjs'}elseif($Command -eq 'verify-activity-recovery'){'backend\dev-runtime\verify-activity-recovery.mjs'}elseif($Command -eq 'verify-delivery-context'){'backend\dev-runtime\verify-delivery-context.mjs'}elseif($Command -eq 'verify-library'){'backend\dev-runtime\verify-library.mjs'}elseif($Command -eq 'verify-write-recovery'){'backend\dev-runtime\verify-write-recovery.mjs'}elseif($Command -eq 'verify-recorded-time'){'backend\dev-runtime\verify-recorded-time.mjs'}elseif($Command -eq 'verify-material-maintenance'){'backend\dev-runtime\verify-material-maintenance.mjs'}elseif($Command -eq 'verify-observations'){'backend\dev-runtime\verify-observations.mjs'}elseif($Command -eq 'verify-file-replacement'){'backend\dev-runtime\verify-file-replacement.mjs'}elseif($Command -eq 'verify-tablet-interface'){'backend\dev-runtime\verify-tablet-interface.mjs'}elseif($Command -eq 'verify-school-sample'){'backend\dev-runtime\verify-school-sample.mjs'}else{'backend\dev-runtime\verify-dev.mjs'})
        $passwords=@{};foreach($role in @('director_admin','vice_principal','teacher','student')){$passwords[$role]=Decrypt $state.passwords.$role}
        $verifyEnvironment=@{EVAL_DEV_ROOT=$devRoot;PLAYWRIGHT_BROWSERS_PATH=(Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u12-browser')}
        Write-Output (Start-Child $node ('"'+$helper+'"') $verifyEnvironment '' ($passwords|ConvertTo-Json -Compress) $true).Trim()
    } elseif($Command -eq 'accounts'){
        # This read-only dialog must not retain the maintenance lock while the human reads it.
        $lock.Dispose();$lock=$null
        Add-Type -AssemblyName System.Windows.Forms
        $text="EVAL - cuentas locales de desarrollo`r`n`r`n"
        foreach($pair in @(@('director_admin','director'),@('vice_principal','subdirector'),@('teacher','docente'),@('student','estudiante'))){$text+=$pair[1]+' : '+(Decrypt $state.passwords.($pair[0]))+"`r`n`r`n"}
        $form=New-Object Windows.Forms.Form;$form.Text='EVAL - acceso local';$form.Width=650;$form.Height=340
        $box=New-Object Windows.Forms.TextBox;$box.Multiline=$true;$box.ReadOnly=$true;$box.Dock='Fill';$box.Text=$text;$form.Controls.Add($box)
        [void]$form.ShowDialog();$box.Text='';$form.Dispose()
    }
} catch {
    if($Command -eq 'start' -and $stage -in @('php_start','proxy_start')){
        foreach($record in @($state.workers)){
            try{$process=Owned-Process $record;if($process){Stop-Process -Id $process.Id}}catch{[Console]::Error.WriteLine('Partial startup process retained because ownership could not be verified.')}
        }
        $state.workers=@();Save-State $state
    }
    [Console]::Error.WriteLine('EVAL local operation failed at '+$stage+' (line '+$_.InvocationInfo.ScriptLineNumber+'). '+$_.Exception.Message);exit 2
} finally {if($lock){$lock.Dispose()}}
exit 0
