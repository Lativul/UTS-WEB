# Ruang Baca

Prototipe katalog perpustakaan digital untuk tugas Pemrograman Web. Antarmuka memakai warna cream, putih, dan cyan, serta bisa digunakan pada layar desktop maupun ponsel.

## Menjalankan

Cara tercepat, buka terminal PowerShell di folder proyek lalu jalankan:

```powershell
.\jalankan.ps1
```

Skrip itu menyalakan server di `http://127.0.0.1:8080`; hentikan dengan `Ctrl + C`.

Alternatif lewat Laragon (Apache): letakkan seluruh isi folder ini di `C:\laragon\www\Web\`, lalu buka http://localhost/Web/

Bila ingin mengetik perintahnya langsung tanpa skrip:

```powershell
& "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" -S 127.0.0.1:8080 -t .
```

Perintah `php` biasa mengarah ke PHP 8.2 yang diblokir kebijakan Application Control di komputer ini, karena itu dipakai path PHP 8.1 di atas.

Buka `http://127.0.0.1:8080` di browser. Data contoh disimpan di `data/storage.json`; jika file belum ada, aplikasi akan mengisinya dengan koleksi, anggota, dan transaksi awal.

## Struktur

```
Web/
├─ index.html            halaman dan struktur tampilan
├─ styles.css            tema cream, putih, cyan
├─ app.js                interaksi katalog dan peminjaman
├─ api.php               router tipis (hanya menangani request)
├─ autoload.php          autoloader kelas (PSR-4)
├─ jalankan.ps1          skrip menyalakan server
├─ data/
│  └─ storage.json       penyimpanan data
└─ src/
   ├─ Models/
   │  ├─ MediaItem.php       kelas abstrak media (inheritance)
   │  ├─ Ebook.php           turunan MediaItem
   │  ├─ PrintedBook.php     turunan MediaItem
   │  ├─ MediaFactory.php    factory pola pembuatan objek media
   │  └─ Member.php          enkapsulasi data anggota
   ├─ Contracts/
   │  └─ LoanService.php     interface kontrak peminjaman
   ├─ Services/
   │  └─ JsonLoanService.php implementasi peminjaman (polymorphism)
   ├─ Storage/
   │  └─ JsonStorage.php     penyimpanan JSON dengan lock file
   ├─ Exceptions/
   │  └─ ApiException.php    exception khusus API
   └─ Api/
      └─ LibraryApi.php      logika utama API (bootstrap, borrow, add_member)
```

## Fitur

- Cari buku berdasarkan judul, penulis, atau kategori.
- Daftarkan anggota dengan validasi nama, email, dan alamat email unik.
- Pinjam buku untuk anggota terdaftar; jumlah stok diperbarui dan tanggal jatuh tempo diatur tujuh hari setelah peminjaman.
- Pantau stok, e-book, anggota, dan transaksi aktif melalui ringkasan dashboard.

## Struktur OOP

- **Encapsulation**: `MediaItem` dan `Member` menyimpan properti `private` dan hanya mengekspos data lewat `toArray()`.
- **Inheritance**: `Ebook` dan `PrintedBook` mewarisi `MediaItem` dan mengimplementasikan `getFormatLabel()` masing-masing.
- **Polymorphism**: `JsonLoanService` mengimplementasikan kontrak `LoanService`, sehingga layanan peminjaman bisa diganti implementasinya tanpa mengubah kode pemanggil.
- **Factory**: `MediaFactory::create()` memilih kelas (`Ebook` atau `PrintedBook`) berdasarkan format data, memusatkan logika pembuatan objek.
- **Interface**: `LoanService` mendefinisikan kontrak `borrow()` yang harus dipenuhi setiap layanan peminjaman.
- **Namespace & Autoload**: setiap kelas memiliki namespace sesuai foldernya (`RuangBaca\Models`, `RuangBaca\Services`, dst.) dan dimuat otomatis oleh `autoload.php` (PSR-4).

Antarmuka ditulis menggunakan HTML semantik, CSS responsif, dan JavaScript tanpa framework. PHP menangani API, validasi sisi server, model OOP, dan penyimpanan data.
