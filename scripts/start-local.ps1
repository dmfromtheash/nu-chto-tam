param(
    [string]$Php = "C:\php\php.exe",
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PublicPath = Join-Path $ProjectRoot "public"
$Url = "http://${HostName}:${Port}"

Set-Location $ProjectRoot

if (-not (Test-Path -LiteralPath $PublicPath -PathType Container)) {
    Write-Host "[ERROR] public directory was not found: $PublicPath" -ForegroundColor Red
    Write-Host "Run this script from the web2 project, or check the project structure."
    exit 1
}

if (-not (Test-Path -LiteralPath $Php -PathType Leaf)) {
    Write-Host "[ERROR] PHP was not found: $Php" -ForegroundColor Red
    Write-Host "Expected path: C:\php\php.exe"
    exit 1
}

Write-Host "[OK] Project root: $ProjectRoot" -ForegroundColor Green
Write-Host "[OK] PHP: $Php" -ForegroundColor Green
Write-Host "[OK] Document root: $PublicPath" -ForegroundColor Green
Write-Host ""
Write-Host "Starting local PHP server..." -ForegroundColor Cyan
Write-Host "Site is available at $Url" -ForegroundColor Yellow
Write-Host "Stop server: Ctrl+C"
Write-Host ""
Write-Host "Seed is NOT started automatically."
Write-Host ""

& $Php -S "${HostName}:${Port}" -t $PublicPath
