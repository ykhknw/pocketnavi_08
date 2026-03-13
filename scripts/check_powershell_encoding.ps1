# PowerShell Encoding Check and Setup Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PowerShell Encoding Check" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Current Output Encoding
Write-Host "[1] Current Output Encoding" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$outputEncoding = [Console]::OutputEncoding
Write-Host "  OutputEncoding: $($outputEncoding.EncodingName) ($($outputEncoding.CodePage))" -ForegroundColor White

# 2. Console Code Page
Write-Host ""
Write-Host "[2] Console Code Page" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$codePage = [Console]::OutputEncoding.CodePage
Write-Host "  Code Page: $codePage" -ForegroundColor White

# Code Page Descriptions
$codePageNames = @{
    65001 = "UTF-8"
    932 = "Shift-JIS (Japanese)"
    437 = "OEM United States"
    850 = "OEM Multilingual Latin I"
}

if ($codePageNames.ContainsKey($codePage)) {
    Write-Host "  Description: $($codePageNames[$codePage])" -ForegroundColor White
} else {
    Write-Host "  Description: Other (CodePage: $codePage)" -ForegroundColor White
}

# 3. System Default Encoding
Write-Host ""
Write-Host "[3] System Default Encoding" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$defaultEncoding = [System.Text.Encoding]::Default
Write-Host "  Default Encoding: $($defaultEncoding.EncodingName) ($($defaultEncoding.CodePage))" -ForegroundColor White

# 4. UTF-8 Encoding Check
Write-Host ""
Write-Host "[4] UTF-8 Encoding Check" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
$utf8Encoding = [System.Text.Encoding]::UTF8
Write-Host "  UTF-8 Encoding: $($utf8Encoding.EncodingName) ($($utf8Encoding.CodePage))" -ForegroundColor White

# 5. Check if current setting is UTF-8
Write-Host ""
Write-Host "[5] Current Setting Status" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
if ($codePage -eq 65001) {
    Write-Host "  [OK] Current setting is UTF-8" -ForegroundColor Green
} else {
    Write-Host "  [WARN] Current setting is NOT UTF-8" -ForegroundColor Yellow
    Write-Host "    Recommendation: Change to UTF-8" -ForegroundColor Yellow
}

# 6. How to change to UTF-8
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "How to Change to UTF-8" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Method 1: Change for current session only" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Write-Host "Run the following command:" -ForegroundColor White
Write-Host "  [Console]::OutputEncoding = [System.Text.Encoding]::UTF8" -ForegroundColor Cyan
Write-Host ""
Write-Host "Method 2: Change permanently (add to PowerShell profile)" -ForegroundColor Yellow
Write-Host "----------------------------------------" -ForegroundColor Gray
Write-Host "Open profile with:" -ForegroundColor White
Write-Host "  notepad `$PROFILE" -ForegroundColor Cyan
Write-Host ""
Write-Host "Add the following to profile:" -ForegroundColor White
Write-Host "  [Console]::OutputEncoding = [System.Text.Encoding]::UTF8" -ForegroundColor Cyan
Write-Host "  chcp 65001 | Out-Null" -ForegroundColor Cyan
Write-Host ""

# 7. Test: Japanese character display
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Japanese Character Display Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "If the following Japanese characters display correctly, it's OK:" -ForegroundColor White
Write-Host "  Hiragana: aiueo" -ForegroundColor Green
Write-Host "  Katakana: kakikukeko" -ForegroundColor Green
Write-Host ""

# 8. Option to change to UTF-8 immediately
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Change to UTF-8 Now?" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
$response = Read-Host "Change to UTF-8? (Y/N)"

if ($response -eq "Y" -or $response -eq "y") {
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
    chcp 65001 | Out-Null
    Write-Host ""
    Write-Host "[OK] Changed to UTF-8" -ForegroundColor Green
    Write-Host ""
    Write-Host "Re-check:" -ForegroundColor Yellow
    $newCodePage = [Console]::OutputEncoding.CodePage
    Write-Host "  New Code Page: $newCodePage" -ForegroundColor White
    if ($newCodePage -eq 65001) {
        Write-Host "  [OK] Successfully changed to UTF-8" -ForegroundColor Green
    }
} else {
    Write-Host ""
    Write-Host "Change skipped" -ForegroundColor Yellow
}

Write-Host ""
