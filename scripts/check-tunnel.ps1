param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8000,
    [string]$ApiHost = "api.trycloudflare.com",
    [string]$ApiUrl = "https://api.trycloudflare.com/tunnel"
)

$ErrorActionPreference = "Continue"

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$LocalUrl = "http://${HostName}:${Port}"
$CloudflaredConfig = Join-Path $env:USERPROFILE ".cloudflared\config.yaml"

Set-Location $ProjectRoot

$Summary = [ordered]@{
    LocalSite = "unknown"
    Cloudflared = "unknown"
    Dns = "unknown"
    Tcp443 = "unknown"
    CloudflareApi = "unknown"
    CurlIpv4 = "unknown"
    ConfigYaml = "unknown"
}

function Write-Step {
    param([string]$Text)
    Write-Host ""
    Write-Host "== $Text ==" -ForegroundColor Cyan
}

function Write-Ok {
    param([string]$Text)
    Write-Host "[OK] $Text" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Text)
    Write-Host "[WARN] $Text" -ForegroundColor Yellow
}

function Write-Fail {
    param([string]$Text)
    Write-Host "[FAIL] $Text" -ForegroundColor Red
}

Write-Host "Cloudflare Tunnel diagnostics for local demo"
Write-Host "Project: $ProjectRoot"
Write-Host "Local site: $LocalUrl"
Write-Host "Cloudflare API: $ApiUrl"

Write-Step "Local site"
try {
    $LocalResponse = Invoke-WebRequest -Uri $LocalUrl -UseBasicParsing -TimeoutSec 5
    $Summary.LocalSite = "works ($($LocalResponse.StatusCode))"
    Write-Ok "Local site responds: $LocalUrl ($($LocalResponse.StatusCode))"
}
catch {
    $Summary.LocalSite = "not working"
    Write-Fail "Local site does not respond: $LocalUrl"
    Write-Host "Start it in a separate PowerShell window:"
    Write-Host "  powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1"
}

Write-Step "cloudflared --version"
$Cloudflared = Get-Command "cloudflared" -ErrorAction SilentlyContinue
if ($null -eq $Cloudflared) {
    $Summary.Cloudflared = "not found"
    Write-Fail "cloudflared was not found in PATH."
    Write-Host "Install with winget:"
    Write-Host "  winget install --id Cloudflare.cloudflared -e"
}
else {
    $Summary.Cloudflared = "found"
    Write-Ok "cloudflared found: $($Cloudflared.Source)"
    & cloudflared --version
}

Write-Step "Resolve-DnsName $ApiHost"
try {
    $Dns = Resolve-DnsName $ApiHost -ErrorAction Stop
    $Summary.Dns = "works"
    Write-Ok "DNS works."
    $Dns | Format-Table -AutoSize
}
catch {
    $Summary.Dns = "failed"
    Write-Fail "DNS could not resolve $ApiHost"
    Write-Host $_.Exception.Message
}

Write-Step "Test-NetConnection $ApiHost -Port 443"
try {
    $Tcp = Test-NetConnection $ApiHost -Port 443 -WarningAction SilentlyContinue
    if ($Tcp.TcpTestSucceeded) {
        $Summary.Tcp443 = "works"
        Write-Ok "TCP 443 is reachable."
    }
    else {
        $Summary.Tcp443 = "failed"
        Write-Fail "TCP 443 is not reachable."
    }
    $Tcp | Format-List ComputerName,RemoteAddress,RemotePort,TcpTestSucceeded,InterfaceAlias,SourceAddress
}
catch {
    $Summary.Tcp443 = "failed"
    Write-Fail "Test-NetConnection failed."
    Write-Host $_.Exception.Message
}

Write-Step "Invoke-WebRequest POST $ApiUrl"
try {
    $ApiResponse = Invoke-WebRequest -Uri $ApiUrl -Method POST -UseBasicParsing -TimeoutSec 20
    $Summary.CloudflareApi = "works ($($ApiResponse.StatusCode))"
    Write-Ok "Cloudflare API returned HTTP $($ApiResponse.StatusCode)."
}
catch {
    $Message = $_.Exception.Message
    $StatusCode = $null
    if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
        $StatusCode = [int]$_.Exception.Response.StatusCode
    }

    if ($StatusCode -ne $null) {
        $Summary.CloudflareApi = "reachable (HTTP $StatusCode)"
        Write-Ok "HTTPS to Cloudflare API works; server returned HTTP $StatusCode."
    }
    elseif ($Message -match "timed out|timeout|deadline") {
        $Summary.CloudflareApi = "timeout"
        Write-Fail "HTTPS to Cloudflare API timed out."
    }
    else {
        $Summary.CloudflareApi = "failed"
        Write-Fail "Invoke-WebRequest could not reach Cloudflare API."
    }
    Write-Host $Message
}

Write-Step "curl.exe -4 -v -X POST $ApiUrl"
$Curl = Get-Command "curl.exe" -ErrorAction SilentlyContinue
if ($null -eq $Curl) {
    $Summary.CurlIpv4 = "curl.exe not found"
    Write-Warn "curl.exe was not found."
}
else {
    & curl.exe -4 -v -X POST --connect-timeout 10 --max-time 25 $ApiUrl 2>&1
    $CurlExit = $LASTEXITCODE
    if ($CurlExit -eq 0) {
        $Summary.CurlIpv4 = "works"
        Write-Ok "curl.exe completed successfully."
    }
    elseif ($CurlExit -eq 28) {
        $Summary.CurlIpv4 = "timeout"
        Write-Fail "curl.exe got timeout (exit code 28)."
    }
    else {
        $Summary.CurlIpv4 = "failed (exit code $CurlExit)"
        Write-Warn "curl.exe exited with code $CurlExit."
    }
}

Write-Step "Check $CloudflaredConfig"
if (Test-Path -LiteralPath $CloudflaredConfig -PathType Leaf) {
    $Summary.ConfigYaml = "exists"
    Write-Warn "Found config.yaml: $CloudflaredConfig"
    Write-Host "Quick tunnel usually does not need it. If tunnel behaves strangely, rename this file temporarily and retry."
}
else {
    $Summary.ConfigYaml = "not found"
    Write-Ok "No conflicting config.yaml was found."
}

Write-Step "Summary"
Write-Host "Local site: $($Summary.LocalSite)"
Write-Host "cloudflared: $($Summary.Cloudflared)"
Write-Host "DNS ${ApiHost}: $($Summary.Dns)"
Write-Host "TCP 443 to ${ApiHost}: $($Summary.Tcp443)"
Write-Host "HTTPS POST via Invoke-WebRequest: $($Summary.CloudflareApi)"
Write-Host "HTTPS POST via curl IPv4: $($Summary.CurlIpv4)"
Write-Host "config.yaml: $($Summary.ConfigYaml)"

if ($Summary.CloudflareApi -eq "timeout" -or $Summary.CurlIpv4 -eq "timeout") {
    Write-Host ""
    Write-Host "Result: local PHP site can be OK, while Quick Tunnel cannot reach Cloudflare API." -ForegroundColor Yellow
    Write-Host "What to try:"
    Write-Host "  1) toggle VPN on/off;"
    Write-Host "  2) try mobile internet;"
    Write-Host "  3) check firewall/antivirus rules for cloudflared.exe;"
    Write-Host "  4) try again later;"
    Write-Host "  5) future option: named tunnel with a Cloudflare account."
}
