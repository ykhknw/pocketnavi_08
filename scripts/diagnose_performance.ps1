# Performance Diagnosis PowerShell Script
# Tool to identify causes of localhost/index.php display delay on Windows

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Performance Diagnosis Tool (PowerShell)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$projectRoot = "D:\AI関連\cursor\pocketnavi_08_new"
$results = @{}

# 1. localhost name resolution check
Write-Host "[1] localhost Name Resolution Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$pingResult = Test-Connection -ComputerName localhost -Count 1 -ErrorAction SilentlyContinue
if ($pingResult) {
    Write-Host "  [OK] localhost ping successful" -ForegroundColor Green
    Write-Host "  Response time: $($pingResult.ResponseTime) ms" -ForegroundColor White
    $results['localhost_ping'] = $pingResult.ResponseTime
} else {
    Write-Host "  [NG] localhost ping failed" -ForegroundColor Red
    $results['localhost_ping'] = "failed"
}

# DNS resolution check
$dnsStartTime = Get-Date
try {
    $dnsResult = [System.Net.Dns]::GetHostEntry("localhost")
    $dnsEndTime = Get-Date
    $dnsTime = ($dnsEndTime - $dnsStartTime).TotalMilliseconds
    Write-Host "  [OK] DNS resolution successful" -ForegroundColor Green
    Write-Host "  Resolution time: $([math]::Round($dnsTime, 2)) ms" -ForegroundColor White
    Write-Host "  IP Address: $($dnsResult.AddressList[0])" -ForegroundColor White
    $results['dns_resolution'] = [math]::Round($dnsTime, 2)
    
    if ($dnsTime -gt 1000) {
        Write-Host "  [WARN] DNS resolution takes more than 1 second" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  [NG] DNS resolution failed: $_" -ForegroundColor Red
    $results['dns_resolution'] = "failed"
}
Write-Host ""

# 2. hosts file check
Write-Host "[2] hosts File Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
if (Test-Path $hostsPath) {
    $hostsContent = Get-Content $hostsPath
    $localhostEntry = $hostsContent | Where-Object { $_ -match "^\s*127\.0\.0\.1\s+localhost" -or $_ -match "^\s*::1\s+localhost" }
    
    if ($localhostEntry) {
        Write-Host "  [OK] localhost entry found" -ForegroundColor Green
        foreach ($entry in $localhostEntry) {
            Write-Host "    $entry" -ForegroundColor White
        }
        $results['hosts_file'] = "found"
    } else {
        Write-Host "  [WARN] localhost entry not found" -ForegroundColor Yellow
        Write-Host "    Recommendation: Add '127.0.0.1 localhost' to hosts file" -ForegroundColor Yellow
        $results['hosts_file'] = "not_found"
    }
} else {
    Write-Host "  [NG] hosts file not found" -ForegroundColor Red
    $results['hosts_file'] = "not_found"
}
Write-Host ""

# 3. Log files check
Write-Host "[3] Log Files Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$logDir = Join-Path $projectRoot "logs"
if (Test-Path $logDir) {
    $logFiles = Get-ChildItem -Path $logDir -Filter "*.log" | Sort-Object LastWriteTime -Descending | Select-Object -First 5
    Write-Host "  Latest log files:" -ForegroundColor White
    foreach ($logFile in $logFiles) {
        Write-Host "    $($logFile.Name) (Last updated: $($logFile.LastWriteTime))" -ForegroundColor White
    }
    
    # Display last 10 lines of latest log file
    if ($logFiles.Count -gt 0) {
        $latestLog = $logFiles[0]
        Write-Host ""
        Write-Host "  Last 10 lines of latest log:" -ForegroundColor White
        Get-Content $latestLog.FullName -Tail 10 | ForEach-Object {
            Write-Host "    $_" -ForegroundColor Gray
        }
    }
    $results['log_files'] = $logFiles.Count
} else {
    Write-Host "  [WARN] Log directory not found" -ForegroundColor Yellow
    $results['log_files'] = 0
}
Write-Host ""

# 4. PHP check
Write-Host "[4] PHP Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$phpPath = Get-Command php -ErrorAction SilentlyContinue
if ($phpPath) {
    Write-Host "  [OK] PHP found" -ForegroundColor Green
    Write-Host "    Path: $($phpPath.Source)" -ForegroundColor White
    
    $phpVersion = php -v | Select-Object -First 1
    Write-Host "    Version: $phpVersion" -ForegroundColor White
    $results['php'] = "found"
} else {
    Write-Host "  [NG] PHP not found" -ForegroundColor Red
    Write-Host "    Please check PATH environment variable" -ForegroundColor Yellow
    $results['php'] = "not_found"
}
Write-Host ""

# 5. Disk I/O check
Write-Host "[5] Disk I/O Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

try {
    $disk = Get-Counter "\PhysicalDisk(_Total)\% Disk Time" -ErrorAction SilentlyContinue
    if ($disk) {
        $diskTime = $disk.CounterSamples[0].CookedValue
        Write-Host "  Disk usage: $([math]::Round($diskTime, 2))%" -ForegroundColor White
        $results['disk_usage'] = [math]::Round($diskTime, 2)
        
        if ($diskTime -gt 80) {
            Write-Host "  [WARN] Disk usage is high" -ForegroundColor Yellow
        }
    }
} catch {
    Write-Host "  Failed to get disk I/O information" -ForegroundColor Yellow
}
Write-Host ""

# 6. Network connection check
Write-Host "[6] Network Connection Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$port = 80
$connection = Test-NetConnection -ComputerName localhost -Port $port -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
if ($connection.TcpTestSucceeded) {
    Write-Host "  [OK] Connection to localhost:$port successful" -ForegroundColor Green
    $results['network_connection'] = "success"
} else {
    Write-Host "  [NG] Connection to localhost:$port failed" -ForegroundColor Red
    Write-Host "    Please check if web server is running" -ForegroundColor Yellow
    $results['network_connection'] = "failed"
}
Write-Host ""

# 7. Process check
Write-Host "[7] Related Processes Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray

$processes = @("php", "httpd", "apache", "nginx", "mysql", "mariadb")
foreach ($procName in $processes) {
    $proc = Get-Process -Name $procName -ErrorAction SilentlyContinue
    if ($proc) {
        # Handle case where Get-Process returns multiple processes
        if ($proc -is [array]) {
            $proc = $proc[0]
        }
        Write-Host "  [OK] $procName process is running" -ForegroundColor Green
        $memoryMB = [math]::Round($proc.WS / 1MB, 2)
        Write-Host "    Memory: $memoryMB MB" -ForegroundColor White
    }
}
Write-Host ""

# Results summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Diagnosis Results Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$warnings = @()
if ($results.ContainsKey('dns_resolution') -and $results['dns_resolution'] -is [double] -and $results['dns_resolution'] -gt 1000) {
    $warnings += "DNS resolution is slow ($($results['dns_resolution'])ms)"
}
if ($results.ContainsKey('hosts_file') -and $results['hosts_file'] -eq "not_found") {
    $warnings += "localhost entry not found in hosts file"
}
if ($results.ContainsKey('network_connection') -and $results['network_connection'] -eq "failed") {
    $warnings += "Web server connection failed"
}

if ($warnings.Count -eq 0) {
    Write-Host "[OK] No obvious problems found." -ForegroundColor Green
    Write-Host "  For detailed diagnosis, run: php scripts\diagnose_performance.php" -ForegroundColor White
} else {
    Write-Host "[WARN] The following problems were detected:" -ForegroundColor Yellow
    foreach ($warning in $warnings) {
        Write-Host "  - $warning" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Check Network tab in Chrome DevTools (F12)" -ForegroundColor White
Write-Host "  2. Run: php scripts\diagnose_performance.php" -ForegroundColor White
Write-Host "  3. Refer to: diagnosis_guide.md (診断手順_パフォーマンス問題.md)" -ForegroundColor White
