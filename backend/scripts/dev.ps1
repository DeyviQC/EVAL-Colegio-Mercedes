param(
    [ValidateSet('capability')][string]$Command = 'capability',
    [ValidateSet('migration', 'runtime')][string]$Role = 'migration'
)
$ErrorActionPreference = 'Stop'

function Assert-OwnedPath([string]$Path, [string]$Root) {
    $full = [IO.Path]::GetFullPath($Path)
    if (!$full.StartsWith($Root + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Owned path binding failed.' }
    $check = $full
    while ($check.Length -ge $Root.Length) {
        $item = Get-Item -LiteralPath $check -ErrorAction Stop
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Owned path cannot be a reparse point.' }
        if ($check -eq $Root) { break }
        $check = Split-Path $check
    }
    return $full
}

$stage = 'state_path'
$aclFlags = $null
try {
    $root = [IO.Path]::GetFullPath((Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u1'))
    $rootItem = Get-Item -LiteralPath $root
    if ($rootItem.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Owned root cannot be a reparse point.' }
    $statePath = Assert-OwnedPath (Join-Path $root 'database-state.json') $root
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    $stage = 'state_acl'
    $acl = Get-Acl -LiteralPath $statePath
    $aclFlags = [ordered]@{ acl_protected = [bool]$acl.AreAccessRulesProtected;
        owner_current_user = $acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -eq $sid;
        allowed_principals_only = $true }
    foreach ($rule in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
        if ($rule.AccessControlType -eq 'Allow' -and $rule.IdentityReference.Value -notin @($sid, 'S-1-5-18', 'S-1-5-32-544')) {
            $aclFlags.allowed_principals_only = $false
        }
    }
    if (!$aclFlags.acl_protected -or !$aclFlags.owner_current_user -or !$aclFlags.allowed_principals_only) {
        throw 'Owned credential state requires a protected current-user ACL.'
    }
    # Read/decrypt only inside this bounded wrapper; never emit private state.
    $stage = 'process_identity'
    $state = [IO.File]::ReadAllText($statePath) | ConvertFrom-Json
    if ($state.port -ne 3307) { throw 'Only the owned loopback port is permitted.' }
    $executable = Assert-OwnedPath $state.executable $root
    $data = Assert-OwnedPath (Join-Path $root 'mysql\data') $root
    $process = Get-Process -Id $state.pid -ErrorAction Stop
    if ($process.Path -ne $executable -or $process.StartTime.ToUniversalTime().Ticks -ne $state.started) {
        throw 'Owned MySQL process identity mismatch.'
    }
    $server = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $process.Id)
    $owner = Invoke-CimMethod -InputObject $server -MethodName GetOwnerSid
    if ($owner.ReturnValue -ne 0 -or $owner.Sid -ne $sid -or $server.ExecutablePath -ne $executable -or
        !$server.CommandLine.Contains('--datadir=' + $data)) { throw 'Owned MySQL command/owner binding failed.' }
    $stage = 'listener_binding'
    $listeners = @(Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction Stop)
    if ($listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1' -or $listeners[0].OwningProcess -ne $process.Id) {
        throw 'Owned MySQL listener binding failed.'
    }
    $stage = 'child_configuration'
    $php = Assert-OwnedPath (Join-Path $root 'php\php.exe') $root
    $ini = Assert-OwnedPath (Join-Path $root 'php.ini') $root
    $ca = Assert-OwnedPath (Join-Path $root 'tls\ca.pem') $root
    $target = Join-Path (Split-Path $PSScriptRoot) 'dev-runtime\capability.php'
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $php
    $start.Arguments = '-c "' + $ini + '" -d display_errors=0 -d log_errors=0 "' + $target + '"'
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.EnvironmentVariables.Clear()
    foreach ($name in @('SystemRoot', 'TEMP', 'TMP')) { $start.EnvironmentVariables[$name] = [Environment]::GetEnvironmentVariable($name) }
    $start.EnvironmentVariables['EVAL_DB_HOST'] = '127.0.0.1'
    $start.EnvironmentVariables['EVAL_DB_PORT'] = '3307'
    $start.EnvironmentVariables['EVAL_DB_USER'] = 'eval_u1_' + $Role
    $start.EnvironmentVariables['EVAL_DB_CA'] = $ca
    $start.EnvironmentVariables['EVAL_DEV_CAPABILITY_ROLE'] = $Role
    $stage = 'credential_resolution'
    $secure = ConvertTo-SecureString $state.$Role
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $start.EnvironmentVariables['EVAL_DB_PASSWORD'] = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    $child = New-Object Diagnostics.Process
    $child.StartInfo = $start
    try {
        $stage = 'account_capabilities'
        [void]$child.Start()
        $start.EnvironmentVariables.Remove('EVAL_DB_PASSWORD')
        $output = $child.StandardOutput.ReadToEnd()
        $null = $child.StandardError.ReadToEnd()
        $child.WaitForExit()
        if ($child.ExitCode -ne 0) { throw 'Owned capability check failed; no database mutation performed.' }
        $result = $output | ConvertFrom-Json
        $flags = @('account_binding', 'tls', 'create_database', 'create_user', 'grant_dev_permissions')
        if (@($result.PSObject.Properties).Count -ne $flags.Count) { throw 'Unexpected capability response.' }
        $safe = [ordered]@{ role = $Role }
        foreach ($flag in $flags) {
            if ($result.$flag -isnot [bool]) { throw 'Invalid capability flag.' }
            $safe[$flag] = $result.$flag
        }
        $safe | ConvertTo-Json -Compress
    } finally {
        $start.EnvironmentVariables.Remove('EVAL_DB_PASSWORD')
        $secure.Dispose()
        $child.Dispose()
    }
} catch {
    # All messages are server-owned constants; exceptions/state/paths are not logged.
    [Console]::Error.WriteLine('Owned capability safety check failed at ' + $stage + '; no database mutation performed.')
    if ($stage -eq 'state_acl' -and $null -ne $aclFlags) {
        [Console]::Error.WriteLine(($aclFlags | ConvertTo-Json -Compress))
    }
    exit 2
}
