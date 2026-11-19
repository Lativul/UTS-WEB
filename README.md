# Ruang Baca

Prototipe katalog perpustakaan digital untuk tugas Pemrograman Web. Antarmuka memakai warna cream, putih, dan cyan, serta bisa digunakan pada layar desktop maupun ponsel.

## Menjalankan

Cara tercepat, buka terminal PowerShell di folder proyek lalu jalankan:

```powershell
.\jalankan.ps1
```

Skrip itu menyalakan server di `http://127.0.0.1:8080`; hentikan dengan `Ctrl + C`.

alternatif lain untuk menjalankan program http://localhost/Web 
Bila ingin mengetik perintahnya langsung tanpa skrip:

```powershell
& "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" -S 127.0.0.1:8080 -t .
```

Perintah `php` biasa mengarah ke PHP 8.2 yang diblokir kebijakan Application Control di komputer ini, karena itu dipakai path PHP 8.1 di atas.

Buka `http://127.0.0.1:8080` di browser. Data contoh disimpan di `storage.json`; jika file belum ada, aplikasi akan mengisinya dengan koleksi, anggota, dan transaksi awal.

## Fitur

- Cari buku berdasarkan judul, penulis, atau kategori.
- Daftarkan anggota dengan validasi nama, email, dan alamat email unik.
- Pinjam buku untuk anggota terdaftar; jumlah stok diperbarui dan tanggal jatuh tempo diatur tujuh hari setelah peminjaman.
- Pantau stok, e-book, anggota, dan transaksi aktif melalui ringkasan dashboard.

## Struktur OOP

- `MediaItem` merupakan kelas abstrak dengan properti privat; `Ebook` dan `PrintedBook` mewarisi kelas tersebut dan memberikan label format masing-masing.
- `Member` mengenkapsulasi data anggota.
- `LoanService` mendefinisikan kontrak layanan peminjaman dan `JsonLoanService` mengimplementasikannya.
- `JsonStorage` menangani penyimpanan JSON dengan penguncian file saat data diubah.

Antarmuka ditulis menggunakan HTML semantik, CSS responsif, dan JavaScript tanpa framework. PHP menangani API, validasi sisi server, model OOP, dan penyimpanan data.
