<#
Backup do banco mecanize_db via mysqldump.
Salva em database/backups/mecanize_YYYY-MM-DD_HHmm.sql e apaga backups com
mais de 30 dias.

Para agendar (roda todo dia às 23:30), execute UMA VEZ como administrador:

    schtasks /create /tn "Mecanize - Backup DB" /tr "powershell.exe -ExecutionPolicy Bypass -File C:\xampp\htdocs\mecanize\database\backup.ps1" /sc daily /st 23:30

Para testar manualmente, só rode este script direto:

    powershell -File database\backup.ps1
#>

$mysqldump = "C:\xampp\mysql\bin\mysqldump.exe"
$banco     = "mecanize_db"
$usuario   = "root"
$pastaBackup = Join-Path $PSScriptRoot "backups"
$diasParaManter = 30

if (!(Test-Path $pastaBackup)) {
    New-Item -ItemType Directory -Path $pastaBackup | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd_HHmm"
$arquivo   = Join-Path $pastaBackup "mecanize_$timestamp.sql"

& $mysqldump --user=$usuario --result-file=$arquivo $banco

if ($LASTEXITCODE -ne 0) {
    Write-Error "Falha ao gerar backup (mysqldump saiu com codigo $LASTEXITCODE)."
    exit 1
}

Write-Output "Backup gerado: $arquivo"

Get-ChildItem -Path $pastaBackup -Filter "mecanize_*.sql" |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$diasParaManter) } |
    Remove-Item -Force
