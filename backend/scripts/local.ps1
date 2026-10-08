param(
    [ValidateSet('php', 'composer', 'test', 'db-smoke', 'migrate')][string]$Command = 'php',
    [ValidateSet('runtime', 'migration')][string]$Role = 'runtime',
    [ValidateSet('U1', 'U2', 'U3', 'U4', 'Auth')][string]$Unit = 'U1',
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments
)
$ErrorActionPreference = 'Stop'
$root = Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u1'
if (!(Test-Path -LiteralPath $root)) { throw 'Isolated runtime missing' }
$php = Join-Path $root 'php\php.exe'
$names = @('COMPOSER_HOME','COMPOSER_CACHE_DIR','COMPOSER_VENDOR_DIR','COMPOSER_AUTH','COMPOSER','GIT_CONFIG_GLOBAL','GIT_CONFIG_NOSYSTEM','GIT_TERMINAL_PROMPT','EVAL_VENDOR_DIR','EVAL_DB_HOST','EVAL_DB_PORT','EVAL_DB_NAME','EVAL_DB_USER','EVAL_DB_PASSWORD','EVAL_DB_CA','EVAL_DB_ROLE','EVAL_U1_MIGRATION_PASSWORD','EVAL_UNIT')
$saved = @{}
foreach ($name in $names) { $saved[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
    $env:COMPOSER_HOME = Join-Path $root 'composer-home'
    $env:COMPOSER_CACHE_DIR = Join-Path $root 'composer-cache'
    $env:COMPOSER_VENDOR_DIR = Join-Path $root 'vendor'
    $env:COMPOSER_AUTH = '{}'
    $env:COMPOSER = Join-Path (Split-Path $PSScriptRoot) 'composer.json'
    $env:GIT_CONFIG_GLOBAL = 'NUL'
    $env:GIT_CONFIG_NOSYSTEM = '1'
    $env:GIT_TERMINAL_PROMPT = '0'
    $env:EVAL_VENDOR_DIR = $env:COMPOSER_VENDOR_DIR
    if ($Command -eq 'test' -and $Arguments -and @($Arguments | Where-Object { $_ -notmatch '^(--testdox|--filter=.*)$' }).Count) {
        throw 'Unit test wrapper accepts only --testdox and --filter=...; suite override denied.'
    }
    if ($Command -in @('test','db-smoke','migrate')) {
        $state = Get-Content -LiteralPath (Join-Path $root 'database-state.json') -Raw | ConvertFrom-Json
        $process = Get-Process -Id $state.pid -ErrorAction Stop
        if ($process.Path -ne $state.executable -or $process.StartTime.ToUniversalTime().Ticks -ne $state.started) { throw 'Owned MySQL process identity mismatch' }
        $secure = ConvertTo-SecureString $state.$Role
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
        try { $env:EVAL_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
        if ($Command -eq 'test') {
            $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR((ConvertTo-SecureString $state.migration))
            try { $env:EVAL_U1_MIGRATION_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
            finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
        }
        $env:EVAL_DB_HOST = '127.0.0.1'
        $env:EVAL_DB_PORT = [string]$state.port
        $env:EVAL_DB_NAME = 'eval_'+$Unit.ToLowerInvariant()+'_test'
        $env:EVAL_UNIT = $Unit
        $env:EVAL_DB_USER = "eval_u1_$Role"
        $env:EVAL_DB_CA = Join-Path $root 'tls\ca.pem'
        $env:EVAL_DB_ROLE = $Role
    }
    $target = switch ($Command) {
        'composer' { Join-Path $root 'composer.phar' }
        'test' { Join-Path $root 'vendor\phpunit\phpunit\phpunit' }
        'db-smoke' { Join-Path $PSScriptRoot 'db-smoke.php' }
        'migrate' { Join-Path $PSScriptRoot 'migrate.php' }
    }
    if ($Command -eq 'php') { & $php -c (Join-Path $root 'php.ini') @Arguments }
    elseif ($Command -eq 'test') {
        $configuration = if ($Unit -eq 'U1') { 'phpunit.xml' } else { 'phpunit.'+$Unit.ToLowerInvariant()+'.xml' }
        & $php -c (Join-Path $root 'php.ini') $target --configuration (Join-Path (Split-Path $PSScriptRoot) $configuration) @Arguments
    }
    elseif ($Command -eq 'composer') {
        Push-Location (Split-Path $PSScriptRoot)
        try { & $php -c (Join-Path $root 'php.ini') $target @Arguments }
        finally { Pop-Location }
    }
    else { & $php -c (Join-Path $root 'php.ini') $target @Arguments }
    $code = $LASTEXITCODE
} finally {
    foreach ($name in $names) { [Environment]::SetEnvironmentVariable($name, $saved[$name], 'Process') }
}
exit $code
