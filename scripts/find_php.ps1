# PHP Location Finder Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PHP Location Finder" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Search common installation directories
Write-Host "[1] Searching Common Installation Directories" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$searchPaths = @(
    "C:\Program Files",
    "C:\Program Files (x86)",
    "C:\xampp",
    "C:\wamp",
    "C:\wamp64",
    "C:\laragon",
    "C:\php",
    "C:\tools\php",
    "$env:USERPROFILE\php",
    "$env:LOCALAPPDATA\php"
)

$foundPhp = @()

foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        Write-Host "  Searching: $path" -ForegroundColor White
        $phpDirs = Get-ChildItem -Path $path -Filter "php*" -Directory -ErrorAction SilentlyContinue
        $phpExes = Get-ChildItem -Path $path -Filter "php.exe" -Recurse -ErrorAction SilentlyContinue | Select-Object -First 5
        
        if ($phpDirs) {
            foreach ($dir in $phpDirs) {
                Write-Host "    [FOUND] Directory: $($dir.FullName)" -ForegroundColor Green
                $foundPhp += $dir.FullName
            }
        }
        
        if ($phpExes) {
            foreach ($exe in $phpExes) {
                Write-Host "    [FOUND] php.exe: $($exe.FullName)" -ForegroundColor Green
                $foundPhp += $exe.DirectoryName
            }
        }
    }
}

Write-Host ""

# 2. Check running httpd process for PHP configuration
Write-Host "[2] Checking httpd Process Configuration" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$httpdProcess = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if ($httpdProcess) {
    Write-Host "  [OK] httpd process is running" -ForegroundColor Green
    Write-Host "    Process ID: $($httpdProcess.Id)" -ForegroundColor White
    Write-Host "    Path: $($httpdProcess.Path)" -ForegroundColor White
    
    # Try to find httpd.conf
    $httpdPath = $httpdProcess.Path
    $httpdDir = Split-Path $httpdPath -Parent
    $httpdConf = Join-Path $httpdDir "conf\httpd.conf"
    
    if (Test-Path $httpdConf) {
        Write-Host "    [FOUND] httpd.conf: $httpdConf" -ForegroundColor Green
        Write-Host "    Checking for PHP configuration..." -ForegroundColor White
        
        $confContent = Get-Content $httpdConf -ErrorAction SilentlyContinue
        $phpLines = $confContent | Where-Object { $_ -match "php|PHP" }
        
        if ($phpLines) {
            Write-Host "    PHP-related configuration found:" -ForegroundColor Yellow
            foreach ($line in $phpLines) {
                Write-Host "      $line" -ForegroundColor Gray
            }
        }
    } else {
        Write-Host "    [INFO] httpd.conf not found in default location" -ForegroundColor Yellow
    }
} else {
    Write-Host "  [INFO] httpd process not found" -ForegroundColor Yellow
}

Write-Host ""

# 3. Search entire C: drive (slow, but thorough)
Write-Host "[3] Quick Search for php.exe" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Write-Host "  This may take a while..." -ForegroundColor Yellow

$quickSearch = @(
    "C:\php\php.exe",
    "C:\xampp\php\php.exe",
    "C:\wamp\bin\php\php*\php.exe",
    "C:\wamp64\bin\php\php*\php.exe",
    "C:\laragon\bin\php\php*\php.exe"
)

foreach ($searchPath in $quickSearch) {
    $found = Get-ChildItem -Path $searchPath -ErrorAction SilentlyContinue
    if ($found) {
        Write-Host "    [FOUND] $($found.FullName)" -ForegroundColor Green
        $foundPhp += $found.DirectoryName
    }
}

Write-Host ""

# 4. Check environment variables
Write-Host "[4] Checking Environment Variables" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$phpHome = [Environment]::GetEnvironmentVariable("PHP_HOME")
if ($phpHome) {
    Write-Host "  [FOUND] PHP_HOME: $phpHome" -ForegroundColor Green
    $foundPhp += $phpHome
} else {
    Write-Host "  PHP_HOME not set" -ForegroundColor Yellow
}

$pathEnv = [Environment]::GetEnvironmentVariable("Path", "User")
if ($pathEnv -match "php") {
    Write-Host "  [FOUND] PHP found in User PATH" -ForegroundColor Green
    $pathParts = $pathEnv -split ";"
    foreach ($part in $pathParts) {
        if ($part -match "php") {
            Write-Host "    $part" -ForegroundColor White
            $foundPhp += $part
        }
    }
}

$systemPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
if ($systemPath -match "php") {
    Write-Host "  [FOUND] PHP found in System PATH" -ForegroundColor Green
    $pathParts = $systemPath -split ";"
    foreach ($part in $pathParts) {
        if ($part -match "php") {
            Write-Host "    $part" -ForegroundColor White
            $foundPhp += $part
        }
    }
}

Write-Host ""

# 5. Summary and recommendations
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($foundPhp.Count -gt 0) {
    Write-Host "[OK] PHP locations found:" -ForegroundColor Green
    $uniquePhp = $foundPhp | Select-Object -Unique
    foreach ($phpPath in $uniquePhp) {
        Write-Host "  - $phpPath" -ForegroundColor White
        
        # Check if php.exe exists in this path
        $phpExe = Join-Path $phpPath "php.exe"
        if (Test-Path $phpExe) {
            Write-Host "    [OK] php.exe found" -ForegroundColor Green
            
            # Test PHP version
            try {
                $phpVersion = & $phpExe -v 2>&1 | Select-Object -First 1
                Write-Host "    Version: $phpVersion" -ForegroundColor Cyan
            } catch {
                Write-Host "    Could not get version" -ForegroundColor Yellow
            }
        } else {
            Write-Host "    [WARN] php.exe not found in this path" -ForegroundColor Yellow
        }
    }
    
    Write-Host ""
    Write-Host "To add PHP to PATH, run:" -ForegroundColor Yellow
    Write-Host "  `$env:Path += `";$($uniquePhp[0])`"" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Or permanently:" -ForegroundColor Yellow
    Write-Host "  [Environment]::SetEnvironmentVariable(`"Path`", `$env:Path + `";$($uniquePhp[0])`", `"User`")" -ForegroundColor Cyan
} else {
    Write-Host "[WARN] PHP not found in common locations" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Possible reasons:" -ForegroundColor Yellow
    Write-Host "  1. PHP is installed in a non-standard location" -ForegroundColor White
    Write-Host "  2. PHP is only available through web server (mod_php)" -ForegroundColor White
    Write-Host "  3. PHP is not installed as a standalone executable" -ForegroundColor White
    Write-Host ""
    Write-Host "If PHP is only available through web server, you can:" -ForegroundColor Yellow
    Write-Host "  - Use the web server's PHP for web requests" -ForegroundColor White
    Write-Host "  - Install PHP separately for command-line use" -ForegroundColor White
}

Write-Host ""
