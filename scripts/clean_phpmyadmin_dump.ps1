# Clean phpMyAdmin SQL dump: remove export error comments.
#
# Examples:
#   .\scripts\clean_phpmyadmin_dump.ps1 -InputFile dump.sql
#   .\scripts\clean_phpmyadmin_dump.ps1 -InputFile dump.sql -OnlyWithTables
#   .\scripts\clean_phpmyadmin_dump.ps1 -InputFile dump.sql -OutputFile dump_clean.sql

param(
    [Parameter(Mandatory = $true)]
    [string] $InputFile,

    [string] $OutputFile = "",

    [switch] $OnlyWithTables,

    [string] $SplitDir = ""
)

$ErrorActionPreference = "Stop"

function Test-ErrorCommentLine {
    param([string] $Line)

    $trimmed = $Line.Trim()
    if (-not $trimmed.StartsWith("--")) {
        return $false
    }

    if ($trimmed -match '#\d{4}\s*-') {
        return $true
    }

    if ($trimmed -match "doesn.?t exist in engine") {
        return $true
    }

    return $false
}

function Decode-HtmlEntities {
    param([string] $Text)
    return [System.Net.WebUtility]::HtmlDecode($Text)
}

function Test-BlockHasTables {
    param([string] $Block)
    return [regex]::IsMatch($Block, '(?im)^\s*CREATE\s+TABLE\s+')
}

function Split-DatabaseBlocks {
    param([string] $Content)

    $pattern = '(?im)^CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`'
    $matches = [regex]::Matches($Content, $pattern)

    if ($matches.Count -eq 0) {
        return [pscustomobject]@{
            Preamble = ""
            Blocks = @([pscustomobject]@{ Name = ""; Block = $Content })
        }
    }

    $preamble = $Content.Substring(0, $matches[0].Index)
    $blocks = New-Object System.Collections.Generic.List[object]

    for ($i = 0; $i -lt $matches.Count; $i++) {
        $dbName = $matches[$i].Groups[1].Value
        $start = $matches[$i].Index
        $end = if ($i + 1 -lt $matches.Count) { $matches[$i + 1].Index } else { $Content.Length }
        $block = $Content.Substring($start, $end - $start)

        [void]$blocks.Add([pscustomobject]@{ Name = $dbName; Block = $block })
    }

    return [pscustomobject]@{
        Preamble = $preamble
        Blocks = $blocks
    }
}

function Clean-Lines {
    param([string] $Text)

    $removed = 0
    $builder = New-Object System.Text.StringBuilder

    foreach ($line in ($Text -split "`r?`n", -1)) {
        if (Test-ErrorCommentLine -Line $line) {
            $removed++
            continue
        }

        [void]$builder.AppendLine($line)
    }

    return [pscustomobject]@{
        Text = $builder.ToString()
        Removed = $removed
    }
}

if (-not (Test-Path -LiteralPath $InputFile)) {
    Write-Error "File not found: $InputFile"
}

$raw = Get-Content -LiteralPath $InputFile -Raw -Encoding UTF8
$raw = Decode-HtmlEntities -Text $raw

$split = Split-DatabaseBlocks -Content $raw
$preambleResult = Clean-Lines -Text $split.Preamble
$totalRemoved = $preambleResult.Removed
$cleanedBlocks = New-Object System.Collections.Generic.List[object]
$skippedDatabases = New-Object System.Collections.Generic.List[string]

foreach ($item in $split.Blocks) {
    $result = Clean-Lines -Text $item.Block
    $totalRemoved += $result.Removed

    if ($OnlyWithTables -and $item.Name -and -not (Test-BlockHasTables -Block $result.Text)) {
        [void]$skippedDatabases.Add($item.Name)
        continue
    }

    [void]$cleanedBlocks.Add([pscustomobject]@{
        Name = $item.Name
        Block = $result.Text
    })
}

if ($cleanedBlocks.Count -eq 0) {
    Write-Error "No importable data left after filtering."
}

$resolvedInput = (Resolve-Path -LiteralPath $InputFile).Path

if ($SplitDir) {
    New-Item -ItemType Directory -Force -Path $SplitDir | Out-Null

    foreach ($item in $cleanedBlocks) {
        $suffix = if ($item.Name) { $item.Name } else { "preamble" }
        $outFile = Join-Path $SplitDir "$suffix.sql"
        Set-Content -LiteralPath $outFile -Value $item.Block -Encoding UTF8
        Write-Host "  - $outFile"
    }

    Write-Host "Created $($cleanedBlocks.Count) files in $SplitDir"
} else {
    if (-not $OutputFile) {
        $base = [System.IO.Path]::GetFileNameWithoutExtension($resolvedInput)
        $dir = [System.IO.Path]::GetDirectoryName($resolvedInput)
        $OutputFile = Join-Path $dir "${base}_clean.sql"
    }

    $merged = $preambleResult.Text + (($cleanedBlocks | ForEach-Object { $_.Block }) -join "")
    Set-Content -LiteralPath $OutputFile -Value $merged -Encoding UTF8

    Write-Host "Done: $OutputFile"
    Write-Host "Databases in file: $($cleanedBlocks.Count)"
    Write-Host "Removed phpMyAdmin error comment lines: $totalRemoved"
}

if ($skippedDatabases.Count -gt 0) {
    Write-Host "Skipped empty databases (-OnlyWithTables): $($skippedDatabases.Count)"
    $preview = ($skippedDatabases | Select-Object -First 10) -join ", "
    if ($skippedDatabases.Count -gt 10) {
        $preview += ", ..."
    }
    Write-Host "  $preview"
}

$databasesWithTables = @($cleanedBlocks | Where-Object { $_.Name -and (Test-BlockHasTables -Block $_.Block) } | ForEach-Object { $_.Name })
if ($databasesWithTables.Count -gt 0) {
    Write-Host "Databases with tables:"
    foreach ($name in $databasesWithTables) {
        Write-Host "  - $name"
    }
}

Write-Host ""
Write-Host "MySQL Workbench import:"
Write-Host "  1. Connect to server (127.0.0.1)."
Write-Host "  2. Server -> Data Import -> Import from Self-Contained File."
Write-Host "  3. Select cleaned .sql file -> Start Import."
Write-Host "  Or: File -> Open SQL Script -> Execute (lightning icon)."
