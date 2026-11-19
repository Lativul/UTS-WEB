<?php
// File ini tidak dipakai lagi. Logika API sekarang dipisah ke dalam src/ dan
// dijalankan dari api.php di root proyek. File ini hanya menjadi jembatan agar
// tautan lama tidak menghasilkan 404.
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api.php';
