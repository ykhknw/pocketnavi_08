# XAMPP Configuration Check Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "XAMPP Configuration Check" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$xamppPath = "d:\xampp"

# 1. XAMPP Directory Check
Write-Host "[1] XAMPP Directory Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

if (Test-Path $xamppPath) {
    Write-Host "  [OK] XAMPP directory found: $xamppPath" -ForegroundColor Green
} else {
    Write-Host "  [NG] XAMPP directory not found at: $xamppPath" -ForegroundColor Red
    Write-Host "  Searching for XAMPP..." -ForegroundColor Yellow
    
    $possiblePaths = @(
        "C:\xampp",
        "D:\xampp",
        "E:\xampp",
        "$env:ProgramFiles\xampp",
        "${env:ProgramFiles(x86)}\xampp"
    )
    
    foreach ($path in $possiblePaths) {
        if (Test-Path $path) {
            Write-Host "    [FOUND] $path" -ForegroundColor Green
            $xamppPath = $path
            break
        }
    }
}

Write-Host ""

# 2. PHP Location Check
Write-Host "[2] PHP Location Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$phpPath = Join-Path $xamppPath "php"
$phpExe = Join-Path $phpPath "php.exe"

if (Test-Path $phpExe) {
    Write-Host "  [OK] PHP found: $phpExe" -ForegroundColor Green
    
    # Get PHP version
    try {
        $phpVersion = & $phpExe -v 2>&1 | Select-Object -First 1
        Write-Host "  Version: $phpVersion" -ForegroundColor White
    } catch {
        Write-Host "  Could not get version" -ForegroundColor Yellow
    }
    
    # Check php.ini
    $phpIni = Join-Path $phpPath "php.ini"
    if (Test-Path $phpIni) {
        Write-Host "  [OK] php.ini found: $phpIni" -ForegroundColor Green
    } else {
        Write-Host "  [WARN] php.ini not found" -ForegroundColor Yellow
    }
} else {
    Write-Host "  [NG] PHP not found at: $phpExe" -ForegroundColor Red
}

Write-Host ""

# 3. Apache Configuration Check
Write-Host "[3] Apache Configuration Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$apacheConf = Join-Path $xamppPath "apache\conf\httpd.conf"
if (Test-Path $apacheConf) {
    Write-Host "  [OK] httpd.conf found: $apacheConf" -ForegroundColor Green
    
    # Read httpd.conf and find PHP-related settings
    $confContent = Get-Content $apacheConf -ErrorAction SilentlyContinue
    
    Write-Host "  Searching for PHP configuration..." -ForegroundColor White
    
    # Find LoadModule php lines
    $phpModules = $confContent | Where-Object { $_ -match "LoadModule.*php" -or $_ -match "php.*module" }
    if ($phpModules) {
        Write-Host "  PHP module configuration:" -ForegroundColor Cyan
        foreach ($line in $phpModules) {
            Write-Host "    $line" -ForegroundColor White
        }
    }
    
    # Find PHPIniDir
    $phpIniDir = $confContent | Where-Object { $_ -match "PHPIniDir" }
    if ($phpIniDir) {
        Write-Host "  PHPIniDir setting:" -ForegroundColor Cyan
        foreach ($line in $phpIniDir) {
            Write-Host "    $line" -ForegroundColor White
        }
    }
    
    # Find AddHandler or SetHandler for PHP
    $phpHandlers = $confContent | Where-Object { $_ -match "AddHandler.*php" -or $_ -match "SetHandler.*php" }
    if ($phpHandlers) {
        Write-Host "  PHP handler configuration:" -ForegroundColor Cyan
        foreach ($line in $phpHandlers) {
            Write-Host "    $line" -ForegroundColor White
        }
    }
    
    # Find DocumentRoot
    $documentRoot = $confContent | Where-Object { $_ -match "^DocumentRoot" }
    if ($documentRoot) {
        Write-Host "  DocumentRoot:" -ForegroundColor Cyan
        foreach ($line in $documentRoot) {
            Write-Host "    $line" -ForegroundColor White
        }
    }
    
    # Find Directory settings
    $directorySettings = $confContent | Where-Object { $_ -match "<Directory" -or $_ -match "</Directory>" } | Select-Object -First 10
    if ($directorySettings) {
        Write-Host "  Directory settings (first 10):" -ForegroundColor Cyan
        foreach ($line in $directorySettings) {
            Write-Host "    $line" -ForegroundColor White
        }
    }
    
} else {
    Write-Host "  [NG] httpd.conf not found at: $apacheConf" -ForegroundColor Red
}

Write-Host ""

# 4. Apache Logs Check
Write-Host "[4] Apache Logs Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$apacheLogs = Join-Path $xamppPath "apache\logs"
if (Test-Path $apacheLogs) {
    Write-Host "  [OK] Apache logs directory found: $apacheLogs" -ForegroundColor Green
    
    $errorLog = Join-Path $apacheLogs "error.log"
    if (Test-Path $errorLog) {
        Write-Host "  [OK] error.log found" -ForegroundColor Green
        Write-Host "  Last 10 lines of error.log:" -ForegroundColor White
        Get-Content $errorLog -Tail 10 -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "    $_" -ForegroundColor Gray
        }
    }
    
    $accessLog = Join-Path $apacheLogs "access.log"
    if (Test-Path $accessLog) {
        Write-Host "  [OK] access.log found" -ForegroundColor Green
    }
} else {
    Write-Host "  [WARN] Apache logs directory not found" -ForegroundColor Yellow
}

Write-Host ""

# 5. PHP Configuration Check
Write-Host "[5] PHP Configuration Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

if (Test-Path $phpIni) {
    $phpIniContent = Get-Content $phpIni -ErrorAction SilentlyContinue
    
    # Check important PHP settings
    $importantSettings = @(
        "max_execution_time",
        "memory_limit",
        "post_max_size",
        "upload_max_filesize",
        "default_socket_timeout",
        "error_log",
        "display_errors",
        "log_errors"
    )
    
    Write-Host "  Important PHP settings:" -ForegroundColor Cyan
    foreach ($setting in $importantSettings) {
        $settingLine = $phpIniContent | Where-Object { $_ -match "^$setting\s*=" -or $_ -match "^;\s*$setting\s*=" } | Select-Object -First 1
        if ($settingLine) {
            Write-Host "    $settingLine" -ForegroundColor White
        } else {
            Write-Host "    $setting : not found" -ForegroundColor Gray
        }
    }
} else {
    Write-Host "  [WARN] php.ini not found" -ForegroundColor Yellow
}

Write-Host ""

# 6. Summary and Recommendations
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Summary and Recommendations" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (Test-Path $phpExe) {
    Write-Host "[OK] PHP found at: $phpExe" -ForegroundColor Green
    Write-Host ""
    Write-Host "To add PHP to PATH, run:" -ForegroundColor Yellow
    Write-Host "  `$env:Path += `";$phpPath`"" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Or permanently:" -ForegroundColor Yellow
    Write-Host "  [Environment]::SetEnvironmentVariable(`"Path`", `$env:Path + `";$phpPath`", `"User`")" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "After adding to PATH, restart PowerShell and test:" -ForegroundColor Yellow
    Write-Host "  php -v" -ForegroundColor Cyan
    Write-Host "  php scripts\diagnose_performance.php" -ForegroundColor Cyan
} else {
    Write-Host "[WARN] PHP executable not found" -ForegroundColor Yellow
    Write-Host "  Please check XAMPP installation" -ForegroundColor White
}

Write-Host ""
Write-Host "Apache configuration file:" -ForegroundColor Yellow
Write-Host "  $apacheConf" -ForegroundColor Cyan
Write-Host ""
Write-Host "PHP configuration file:" -ForegroundColor Yellow
Write-Host "  $phpIni" -ForegroundColor Cyan
Write-Host ""
Write-Host "To edit these files:" -ForegroundColor Yellow
Write-Host "  notepad `"$apacheConf`"" -ForegroundColor Cyan
Write-Host "  notepad `"$phpIni`"" -ForegroundColor Cyan

Write-Host ""
