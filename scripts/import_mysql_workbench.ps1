# Очистка SQL-дампа phpMyAdmin и (опционально) импорт через mysql.exe
#
# Примеры:
#   .\scripts\import_mysql_workbench.ps1 -InputFile "C:\path\dump.sql"
#   .\scripts\import_mysql_workbench.ps1 -InputFile dump.sql -OnlyWithTables
#   .\scripts\import_mysql_workbench.ps1 -InputFile dump.sql -RunImport -MySqlPath "C:\xampp\mysql\bin\mysql.exe"

param(
    [Parameter(Mandatory = $true)]
    [string] $InputFile,

    [string] $OutputFile = "",

    [switch] $OnlyWithTables,

    [switch] $RunImport,

    [string] $MySqlPath = "mysql",

    [string] $HostName = "127.0.0.1",

    [int] $Port = 3306,

    [string] $User = "root",

    [string] $Password = ""
)

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$cleanScript = Join-Path $scriptDir "clean_phpmyadmin_dump.ps1"

if (-not (Test-Path -LiteralPath $InputFile)) {
    Write-Error "Файл не найден: $InputFile"
}

if (-not (Test-Path -LiteralPath $cleanScript)) {
    Write-Error "Не найден скрипт очистки: $cleanScript"
}

$cleanArgs = @{
    InputFile = $InputFile
}

if ($OutputFile) {
    $cleanArgs.OutputFile = $OutputFile
}

if ($OnlyWithTables) {
    $cleanArgs.OnlyWithTables = $true
}

Write-Host "Очистка дампа..." -ForegroundColor Cyan
& $cleanScript @cleanArgs

if (-not $OutputFile) {
    $resolvedInput = (Resolve-Path -LiteralPath $InputFile).Path
    $base = [System.IO.Path]::GetFileNameWithoutExtension($resolvedInput)
    $dir = [System.IO.Path]::GetDirectoryName($resolvedInput)
    $OutputFile = Join-Path $dir "${base}_clean.sql"
}

if (-not (Test-Path -LiteralPath $OutputFile)) {
    Write-Error "Очищенный файл не создан: $OutputFile"
}

$resolvedOutput = (Resolve-Path -LiteralPath $OutputFile).Path

Write-Host ""
Write-Host "Очищенный файл: $resolvedOutput" -ForegroundColor Green

if ($RunImport) {
    $mysqlCmd = Get-Command $MySqlPath -ErrorAction SilentlyContinue
    if (-not $mysqlCmd) {
        Write-Error "mysql.exe не найден. Укажите -MySqlPath, например: C:\xampp\mysql\bin\mysql.exe"
    }

    Write-Host "Импорт через mysql.exe..." -ForegroundColor Cyan

    $mysqlArgs = @(
        "-h", $HostName,
        "-P", $Port,
        "-u", $User,
        "--default-character-set=utf8mb4"
    )

    if ($Password) {
        $env:MYSQL_PWD = $Password
    }

    try {
        Get-Content -LiteralPath $resolvedOutput -Raw -Encoding UTF8 | & $mysqlCmd.Source @mysqlArgs
    } finally {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if ($LASTEXITCODE -ne 0) {
        Write-Error "Импорт завершился с ошибкой (код $LASTEXITCODE)"
    }

    Write-Host "Импорт завершён." -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Импорт в MySQL Workbench:" -ForegroundColor Yellow
    Write-Host "  1. Подключитесь к серверу ($HostName`:$Port)."
    Write-Host "  2. Server -> Data Import -> Import from Self-Contained File."
    Write-Host "  3. Выберите файл: $resolvedOutput"
    Write-Host "  4. Default Target Schema можно оставить пустым (CREATE DATABASE уже в дампе)."
    Write-Host "  5. Start Import."
    Write-Host ""
    Write-Host "Альтернатива: File -> Open SQL Script -> Execute (иконка молнии)."
}
