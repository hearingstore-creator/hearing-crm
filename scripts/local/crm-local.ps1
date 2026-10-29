<#
  Local CRM housekeeping for this PC (one MySQL: C:\xampp_new).

    -Action backup    dump crm_local to E:\Backups\CRM\local\daily, keep 14 days + 8 weekly copies
    -Action verify    restore the newest daily backup into a scratch database, compare row counts, drop it
    -Action shutdown  stop MySQL cleanly (never kill mysqld: that is what corrupted C:\xampp_old)

  Database name, user and password are read from the project's .env; the password is never printed.
  Every run appends one line to E:\Backups\CRM\backup.log.
#>
param(
    [ValidateSet('backup', 'verify', 'shutdown')]
    [string]$Action = 'backup'
)

$ErrorActionPreference = 'Stop'

$Project   = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$MysqlBin  = 'C:\xampp_new\mysql\bin'
$Root      = 'E:\Backups\CRM'
$Daily     = Join-Path $Root 'local\daily'
$Weekly    = Join-Path $Root 'local\weekly'
$Log       = Join-Path $Root 'backup.log'
$KeepDaily = 14
$KeepWeekly = 8

function Write-Log([string]$message) {
    $line = '{0}  {1,-8}  {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Action, $message
    Add-Content -Path $Log -Value $line -Encoding UTF8
    Write-Host $line
}

function Read-DbSettings {
    $db = @{ DB_HOST = '127.0.0.1'; DB_PORT = '3306'; DB_DATABASE = 'crm_local'; DB_USERNAME = 'root'; DB_PASSWORD = '' }
    foreach ($line in Get-Content (Join-Path $Project '.env')) {
        if ($line -match '^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=(.*)$') {
            $db[$Matches[1]] = $Matches[2].Trim().Trim('"').Trim("'")
        }
    }
    $env:MYSQL_PWD = $db.DB_PASSWORD   # read by mysql, mysqldump and mysqladmin; keeps it off the command line
    return $db
}

function Invoke-Mysql([string]$exe, [string[]]$arguments) {
    $db = $script:db
    $all = @("--host=$($db.DB_HOST)", "--port=$($db.DB_PORT)", "--user=$($db.DB_USERNAME)") + $arguments
    # Judge by exit code only: in Windows PowerShell a native tool's stderr line would otherwise stop the script
    $ErrorActionPreference = 'Continue'
    $output = & (Join-Path $MysqlBin $exe) @all 2>&1 | ForEach-Object { "$_" }
    if ($LASTEXITCODE -ne 0) { throw "$exe failed: $($output | Out-String)" }
    return $output
}

function Compress-Gzip([string]$source, [string]$target) {
    $in = [IO.File]::OpenRead($source)
    $out = [IO.File]::Create($target)
    $gz = New-Object IO.Compression.GZipStream($out, [IO.Compression.CompressionLevel]::Optimal)
    try { $in.CopyTo($gz) } finally { $gz.Dispose(); $out.Dispose(); $in.Dispose() }
}

function Expand-Gzip([string]$source, [string]$target) {
    $in = [IO.File]::OpenRead($source)
    $gz = New-Object IO.Compression.GZipStream($in, [IO.Compression.CompressionMode]::Decompress)
    $out = [IO.File]::Create($target)
    try { $gz.CopyTo($out) } finally { $out.Dispose(); $gz.Dispose(); $in.Dispose() }
}

function Get-RowCounts([string]$database) {
    $rows = Invoke-Mysql 'mysql.exe' @('-N', '-B', '-e', "SELECT table_name FROM information_schema.tables WHERE table_schema='$database' AND table_type='BASE TABLE' ORDER BY table_name")
    $counts = @{}
    foreach ($t in $rows) {
        $counts[$t] = [int](Invoke-Mysql 'mysql.exe' @('-N', '-B', '-e', "SELECT COUNT(*) FROM ``$database``.``$t``"))
    }
    return $counts
}

function Backup {
    New-Item -ItemType Directory -Force -Path $Daily, $Weekly | Out-Null
    $name = $db.DB_DATABASE
    $stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
    $sql = Join-Path $env:TEMP "$name-$stamp.sql"
    $gzFile = Join-Path $Daily "$name-$stamp.sql.gz"

    # --single-transaction: a consistent copy while the CRM keeps running
    Invoke-Mysql 'mysqldump.exe' @('--single-transaction', '--routines', '--triggers', '--events', '--default-character-set=utf8mb4', "--result-file=$sql", $name) | Out-Null

    $tail = Get-Content $sql -Tail 1
    if ((Get-Item $sql).Length -lt 10KB -or $tail -notmatch 'Dump completed') {
        Remove-Item $sql -Force
        throw "dump of $name looks incomplete; no backup saved"
    }
    Compress-Gzip $sql $gzFile
    Remove-Item $sql -Force

    # One copy per ISO week goes to weekly\
    $weekNo = [Globalization.CultureInfo]::InvariantCulture.Calendar.GetWeekOfYear((Get-Date), [Globalization.CalendarWeekRule]::FirstFourDayWeek, [DayOfWeek]::Monday)
    $week = (Get-Date -Format 'yyyy') + '-W' + $weekNo.ToString('00')
    $weeklyFile = Join-Path $Weekly "$name-$week.sql.gz"
    if (-not (Test-Path $weeklyFile)) { Copy-Item $gzFile $weeklyFile }

    Get-ChildItem $Daily -Filter "$name-*.sql.gz" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDaily) } | Remove-Item -Force
    Get-ChildItem $Weekly -Filter "$name-*.sql.gz" | Sort-Object Name -Descending | Select-Object -Skip $KeepWeekly | Remove-Item -Force

    Write-Log ('ok  {0}  {1:N0} KB' -f (Split-Path $gzFile -Leaf), ((Get-Item $gzFile).Length / 1KB))
}

function Verify {
    $latest = Get-ChildItem $Daily -Filter "$($db.DB_DATABASE)-*.sql.gz" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $latest) { throw 'no daily backup to verify' }
    $scratch = 'crm_restore_test'
    $sql = Join-Path $env:TEMP "$scratch.sql"
    Expand-Gzip $latest.FullName $sql
    try {
        Invoke-Mysql 'mysql.exe' @('-e', "DROP DATABASE IF EXISTS ``$scratch``; CREATE DATABASE ``$scratch`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") | Out-Null
        Invoke-Mysql 'mysql.exe' @('--default-character-set=utf8mb4', $scratch, '-e', "source $($sql -replace '\\', '/')") | Out-Null
        $restored = Get-RowCounts $scratch
        $live = Get-RowCounts $db.DB_DATABASE
        $diff = @($live.Keys | Where-Object { $restored[$_] -ne $live[$_] })
        $rows = ($restored.Values | Measure-Object -Sum).Sum
        if ($restored.Count -ne $live.Count) {
            Write-Log ("FAILED  {0}: {1} tables restored, {2} in {3}" -f $latest.Name, $restored.Count, $live.Count, $db.DB_DATABASE)
            exit 1
        }
        $note = if ($diff.Count) { "; differs from current data in: $($diff -join ', ') (normal if you worked after the backup)" } else { '; matches current data' }
        Write-Log ("ok  {0} restores: {1} tables, {2:N0} rows{3}" -f $latest.Name, $restored.Count, $rows, $note)
    } finally {
        Invoke-Mysql 'mysql.exe' @('-e', "DROP DATABASE IF EXISTS ``$scratch``") | Out-Null
        Remove-Item $sql -Force -ErrorAction SilentlyContinue
    }
}

function Shutdown {
    if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) { Write-Log 'MySQL was not running'; return }
    Invoke-Mysql 'mysqladmin.exe' @('shutdown') | Out-Null
    for ($i = 0; $i -lt 30 -and (Get-Process mysqld -ErrorAction SilentlyContinue); $i++) { Start-Sleep -Seconds 1 }
    if (Get-Process mysqld -ErrorAction SilentlyContinue) { Write-Log 'MySQL is still stopping; leave it a moment before shutting down the PC' }
    else { Write-Log 'MySQL stopped cleanly' }
}

New-Item -ItemType Directory -Force -Path $Root | Out-Null
try {
    $script:db = Read-DbSettings
    switch ($Action) {
        'backup'   { Backup }
        'verify'   { Verify }
        'shutdown' { Shutdown }
    }
} catch {
    Write-Log "FAILED  $($_.Exception.Message)"
    exit 1
} finally {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}
