# PowerShell Profile Setup Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PowerShell Profile Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if profile exists
Write-Host "[1] Checking PowerShell Profile" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$profilePath = $PROFILE
Write-Host "  Profile Path: $profilePath" -ForegroundColor White

if (Test-Path $profilePath) {
    Write-Host "  [OK] Profile exists" -ForegroundColor Green
} else {
    Write-Host "  [INFO] Profile does not exist. Creating..." -ForegroundColor Yellow
    
    # Get the directory path
    $profileDir = Split-Path $profilePath -Parent
    
    # Create directory if it doesn't exist
    if (!(Test-Path $profileDir)) {
        Write-Host "  Creating directory: $profileDir" -ForegroundColor White
        New-Item -Path $profileDir -ItemType Directory -Force | Out-Null
    }
    
    # Create profile file
    Write-Host "  Creating profile file: $profilePath" -ForegroundColor White
    New-Item -Path $profilePath -ItemType File -Force | Out-Null
    
    Write-Host "  [OK] Profile created" -ForegroundColor Green
}

Write-Host ""

# Check execution policy
Write-Host "[2] Checking Execution Policy" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$executionPolicy = Get-ExecutionPolicy
Write-Host "  Current Execution Policy: $executionPolicy" -ForegroundColor White

if ($executionPolicy -eq "Restricted") {
    Write-Host "  [WARN] Execution policy is Restricted" -ForegroundColor Yellow
    Write-Host "  Recommendation: Change to RemoteSigned" -ForegroundColor Yellow
    Write-Host ""
    $changePolicy = Read-Host "Change execution policy to RemoteSigned? (Y/N)"
    
    if ($changePolicy -eq "Y" -or $changePolicy -eq "y") {
        Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
        Write-Host "  [OK] Execution policy changed to RemoteSigned" -ForegroundColor Green
    }
} else {
    Write-Host "  [OK] Execution policy is OK" -ForegroundColor Green
}

Write-Host ""

# Add UTF-8 encoding to profile
Write-Host "[3] Adding UTF-8 Encoding to Profile" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$utf8Setup = @"

# UTF-8 Encoding Setup
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
chcp 65001 | Out-Null

"@

# Check if UTF-8 setup already exists
$profileContent = Get-Content $profilePath -ErrorAction SilentlyContinue
$hasUtf8Setup = $profileContent | Where-Object { $_ -match "UTF-8 Encoding Setup" -or $_ -match "OutputEncoding.*UTF8" }

if ($hasUtf8Setup) {
    Write-Host "  [INFO] UTF-8 setup already exists in profile" -ForegroundColor Yellow
} else {
    Write-Host "  Adding UTF-8 setup to profile..." -ForegroundColor White
    Add-Content -Path $profilePath -Value $utf8Setup -Encoding UTF8
    Write-Host "  [OK] UTF-8 setup added to profile" -ForegroundColor Green
}

Write-Host ""

# Display profile content
Write-Host "[4] Profile Content" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Write-Host ""
if (Test-Path $profilePath) {
    Write-Host "Current profile content:" -ForegroundColor White
    Get-Content $profilePath | ForEach-Object {
        Write-Host "  $_" -ForegroundColor Gray
    }
} else {
    Write-Host "  Profile file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. Restart PowerShell to apply changes" -ForegroundColor White
Write-Host "  2. Run: .\scripts\check_powershell_encoding.ps1" -ForegroundColor White
Write-Host "  3. Verify that Code Page is 65001 (UTF-8)" -ForegroundColor White
Write-Host ""
