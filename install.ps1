[CmdletBinding()]
param([switch]$Demo,[switch]$BuildAssets,[switch]$Serve)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
function Invoke-Checked {
    param([string]$Program,[string[]]$Arguments)
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Perintah gagal: $Program $($Arguments -join ' ')" }
}
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP 8.3+ diperlukan. Tambahkan php.exe ke PATH.' }
if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) {
    Invoke-Checked 'composer' @('install','--no-interaction','--prefer-dist')
}
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
if (-not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
$environmentText = Get-Content -LiteralPath '.env' -Raw
if ($environmentText -notmatch '(?m)^APP_KEY=base64:.+') { Invoke-Checked 'php' @('artisan','key:generate','--force') }
Invoke-Checked 'php' @('artisan','config:clear')
Invoke-Checked 'php' @('artisan','migrate','--force')
Invoke-Checked 'php' @('artisan','db:seed','--force')
if ($Demo) { Invoke-Checked 'php' @('artisan','db:seed','--class=DemoSeeder','--force') }
Invoke-Checked 'php' @('artisan','storage:link')
if ($BuildAssets -or -not (Test-Path -LiteralPath 'public/build/manifest.json')) {
    Invoke-Checked 'npm.cmd' @('ci','--ignore-scripts','--no-audit','--no-fund')
    Invoke-Checked 'npm.cmd' @('run','build')
}
Invoke-Checked 'php' @('artisan','treefund:doctor')
Write-Host ''
Write-Host 'TreeFund siap. Isi kunci Midtrans Sandbox pada .env untuk mencoba Snap.' -ForegroundColor Green
Write-Host 'Jalankan: php artisan serve'
if ($Serve) { Invoke-Checked 'php' @('artisan','serve','--host=127.0.0.1','--port=8000') }
