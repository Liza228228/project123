# Генерация SQL для ER-диаграммы MySQL Workbench (все таблицы).
#
# Примеры:
#   .\scripts\generate_workbench_eer.ps1
#   .\scripts\generate_workbench_eer.ps1 -Database diplom
#   .\scripts\generate_workbench_eer.ps1 -InputFile C:\Downloads\127_0_0_1.sql -Database diplom

param(
    [string] $InputFile = "",
    [string] $Database = "",
    [string] $OutputFile = ""
)

$ErrorActionPreference = "Stop"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$pyScript = Join-Path $scriptDir "generate_workbench_eer.py"

$argsList = @()
if ($InputFile) { $argsList += $InputFile }
if ($Database) { $argsList += @("--database", $Database) }
if ($OutputFile) { $argsList += @("-o", $OutputFile) }

& py $pyScript @argsList
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
