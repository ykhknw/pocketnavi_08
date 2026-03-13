# PHP Timeout and Performance Settings Check

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PHP Timeout and Performance Settings" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$phpExe = "d:\xampp\php\php.exe"
$phpIni = "d:\xampp\php\php.ini"

# 1. Check PHP executable
Write-Host "[1] PHP Executable Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

if (Test-Path $phpExe) {
    Write-Host "  [OK] PHP found: $phpExe" -ForegroundColor Green
    $phpVersion = & $phpExe -v 2>&1 | Select-Object -First 1
    Write-Host "  Version: $phpVersion" -ForegroundColor White
} else {
    Write-Host "  [NG] PHP not found" -ForegroundColor Red
    exit
}

Write-Host ""

# 2. Check php.ini settings
Write-Host "[2] PHP Configuration (php.ini) Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

if (Test-Path $phpIni) {
    Write-Host "  [OK] php.ini found: $phpIni" -ForegroundColor Green
    
    # Get current settings using php -i
    Write-Host "  Current PHP settings:" -ForegroundColor Cyan
    
    $phpInfo = & $phpExe -i 2>&1
    
    $importantSettings = @(
        "max_execution_time",
        "max_input_time",
        "memory_limit",
        "default_socket_timeout",
        "mysql.connect_timeout",
        "mysqli.default_socket",
        "pdo_mysql.default_socket"
    )
    
    foreach ($setting in $importantSettings) {
        $value = $phpInfo | Where-Object { $_ -match "^$setting\s*=>" } | ForEach-Object {
            if ($_ -match "=>\s*(.+)") {
                $matches[1].Trim()
            }
        }
        
        if ($value) {
            Write-Host "    $setting = $value" -ForegroundColor White
        } else {
            # Try to get from php.ini file directly
            $iniLine = Get-Content $phpIni | Where-Object { $_ -match "^$setting\s*=" -and $_ -notmatch "^;" } | Select-Object -First 1
            if ($iniLine) {
                Write-Host "    $iniLine" -ForegroundColor White
            } else {
                Write-Host "    $setting = (not set or commented)" -ForegroundColor Gray
            }
        }
    }
} else {
    Write-Host "  [NG] php.ini not found" -ForegroundColor Red
}

Write-Host ""

# 3. Check Apache error log for timeout or slow queries
Write-Host "[3] Apache Error Log Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$errorLog = "d:\xampp\apache\logs\error.log"
if (Test-Path $errorLog) {
    Write-Host "  [OK] error.log found: $errorLog" -ForegroundColor Green
    
    Write-Host "  Searching for timeout or slow query messages..." -ForegroundColor White
    
    $logContent = Get-Content $errorLog -Tail 100 -ErrorAction SilentlyContinue
    
    # Search for common timeout/slow patterns
    $timeoutPatterns = @(
        "timeout",
        "timed out",
        "slow",
        "exceeded",
        "max_execution_time",
        "Connection timed out",
        "mysql.*timeout",
        "PDO.*timeout"
    )
    
    $foundIssues = @()
    foreach ($pattern in $timeoutPatterns) {
        $matches = $logContent | Where-Object { $_ -match $pattern -and $_ -notmatch "^\s*$" }
        if ($matches) {
            $foundIssues += $matches
        }
    }
    
    if ($foundIssues.Count -gt 0) {
        Write-Host "  [WARN] Found potential timeout/slow query issues:" -ForegroundColor Yellow
        $foundIssues | Select-Object -First 10 | ForEach-Object {
            Write-Host "    $_" -ForegroundColor Red
        }
    } else {
        Write-Host "  [OK] No obvious timeout issues in recent logs" -ForegroundColor Green
    }
    
    Write-Host ""
    Write-Host "  Last 20 lines of error.log:" -ForegroundColor Cyan
    Get-Content $errorLog -Tail 20 -ErrorAction SilentlyContinue | ForEach-Object {
        Write-Host "    $_" -ForegroundColor Gray
    }
} else {
    Write-Host "  [WARN] error.log not found" -ForegroundColor Yellow
}

Write-Host ""

# 4. Test database connection timeout
Write-Host "[4] Database Connection Test" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$testScript = @"
<?php
`$start = microtime(true);
try {
    `$pdo = new PDO('mysql:host=localhost;port=3306', 'root', '');
    `$end = microtime(true);
    `$time = round((`$end - `$start) * 1000, 2);
    echo "Connection time: `$time ms\n";
} catch (Exception `$e) {
    `$end = microtime(true);
    `$time = round((`$end - `$start) * 1000, 2);
    echo "Connection failed after `$time ms: " . `$e->getMessage() . "\n";
}
"@

$testFile = Join-Path $env:TEMP "php_db_test.php"
$testScript | Out-File -FilePath $testFile -Encoding UTF8

Write-Host "  Testing database connection..." -ForegroundColor White
$dbTestResult = & $phpExe $testFile 2>&1
Write-Host "  $dbTestResult" -ForegroundColor White

Remove-Item $testFile -ErrorAction SilentlyContinue

Write-Host ""

# 5. Recommendations
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Recommendations" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "If you're experiencing 1-minute delays, check:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. default_socket_timeout in php.ini" -ForegroundColor White
Write-Host "   Should be 60 or less (default is 60 seconds)" -ForegroundColor Gray
Write-Host "   Location: d:\xampp\php\php.ini" -ForegroundColor Gray
Write-Host ""
Write-Host "2. max_execution_time in php.ini" -ForegroundColor White
Write-Host "   Should be 30 or 60 (default is 30 seconds)" -ForegroundColor Gray
Write-Host ""
Write-Host "3. MySQL connection timeout" -ForegroundColor White
Write-Host "   Check if database connection is timing out" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Chrome DevTools Network tab" -ForegroundColor White
Write-Host "   Check which resource is taking 1 minute to load" -ForegroundColor Gray
Write-Host ""
Write-Host "To edit php.ini:" -ForegroundColor Yellow
Write-Host "  notepad d:\xampp\php\php.ini" -ForegroundColor Cyan
Write-Host ""
Write-Host "After editing, restart Apache in XAMPP Control Panel" -ForegroundColor Yellow

Write-Host ""
