param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$LocalUrl = "http://${HostName}:${Port}"

Set-Location $ProjectRoot

$Cloudflared = Get-Command "cloudflared" -ErrorAction SilentlyContinue
if ($null -eq $Cloudflared) {
    Write-Host "[ERROR] cloudflared was not found in PATH." -ForegroundColor Red
    Write-Host "Install with winget:"
    Write-Host "  winget install --id Cloudflare.cloudflared -e"
    Write-Host "Then open a new PowerShell window and run this script again."
    exit 1
}

Write-Host "[OK] cloudflared found: $($Cloudflared.Source)" -ForegroundColor Green

try {
    $Response = Invoke-WebRequest -Uri $LocalUrl -UseBasicParsing -TimeoutSec 5
    Write-Host "[OK] Local site responds: $LocalUrl ($($Response.StatusCode))" -ForegroundColor Green
}
catch {
    Write-Host "[ERROR] Local site does not respond: $LocalUrl" -ForegroundColor Red
    Write-Host "Start PHP server in a separate PowerShell window first:"
    Write-Host "  powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1"
    Write-Host "or:"
    Write-Host "  C:\php\php.exe -S 127.0.0.1:8000 -t public"
    exit 1
}

Write-Host ""
Write-Host "Starting Cloudflare Quick Tunnel..." -ForegroundColor Cyan
Write-Host "Command:"
Write-Host "  cloudflared tunnel --edge-ip-version 4 --url $LocalUrl --no-autoupdate"
Write-Host ""
Write-Host "If api.trycloudflare.com times out, this is a network/Cloudflare API access issue, not a PHP site issue."
Write-Host "Stop tunnel: Ctrl+C"
Write-Host ""

& cloudflared tunnel --edge-ip-version 4 --url $LocalUrl --no-autoupdate
