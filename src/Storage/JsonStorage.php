<?php
declare(strict_types=1);

namespace RuangBaca\Storage;

use RuntimeException;

final class JsonStorage
{
    public function __construct(private string $path)
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            throw new RuntimeException('Folder penyimpanan data tidak ditemukan.');
        }
        // Catatan: is_writable() tidak diandalkan di Windows untuk folder
        // yang disinkronkan OneDrive (sering salah mengembalikan false meski
        // foldernya bisa ditulis). Validasi tulis dilakukan saat operasi file.
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function read(): array
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Data perpustakaan tidak dapat dibuka.');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Data perpustakaan sedang tidak dapat dibaca.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $data = json_decode($contents ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('Format data perpustakaan tidak valid.');
            }
            return $data;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function write(array $data): void
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Data perpustakaan tidak dapat disimpan.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Data perpustakaan sedang tidak dapat disimpan.');
            }
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            rewind($handle);
            if (ftruncate($handle, 0) === false || fwrite($handle, $json) === false) {
                throw new RuntimeException('Data perpustakaan gagal ditulis.');
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function transact(callable $operation): mixed
    {
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Data perpustakaan tidak dapat diperbarui.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Data perpustakaan sedang tidak dapat diperbarui.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $data = json_decode($contents ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('Format data perpustakaan tidak valid.');
            }

            $result = $operation($data);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            rewind($handle);
            if (ftruncate($handle, 0) === false || fwrite($handle, $json) === false) {
                throw new RuntimeException('Perubahan data gagal disimpan.');
            }
            fflush($handle);
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
