param([ValidateSet('director_admin','vice_principal','teacher','student')][string]$Role='director_admin',[switch]$Stop,[switch]$Accounts)
$ErrorActionPreference='Stop'
$controller=Join-Path $PSScriptRoot 'backend\scripts\manage-dev.ps1'
if($Stop){& $controller -Command stop;exit $LASTEXITCODE}
if($Accounts){& $controller -Command accounts;exit $LASTEXITCODE}
$status=& $controller -Command status
if($LASTEXITCODE -ne 0){throw 'EVAL local setup is unavailable. See docs/local-development.md.'}
if(($status -join "`n") -notmatch 'app running: True'){& $controller -Command start;if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}}
& $controller -Command open -Role $Role
exit $LASTEXITCODE
