# Menjalankan server Ruang Baca di http://127.0.0.1:8080
# PHP 8.2 di komputer ini diblokir kebijakan Application Control, karena itu dipakai PHP 8.1.

$php = "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"

if (-not (Test-Path $php)) {
    Write-Host "PHP 8.1 tidak ditemukan di: $php" -ForegroundColor Red
    Write-Host "Ubah variabel \$php di file ini ke lokasi php.exe yang tersedia." -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "  Server Ruang Baca menyala di http://127.0.0.1:8080" -ForegroundColor Cyan
Write-Host "  Tekan Ctrl + C untuk menghentikan server." -ForegroundColor DarkGray
Write-Host ""

$root = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }

& $php -S 127.0.0.1:8080 -t $root
