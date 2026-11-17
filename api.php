<?php
declare(strict_types=1);

namespace RuangBaca;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

abstract class MediaItem
{
    public function __construct(
        private int $id,
        private string $title,
        private string $author,
        private string $category,
        private int $stock,
        private string $format,
        private bool $featured = false,
    ) {
    }

    abstract public function getFormatLabel(): string;

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'category' => $this->category,
            'stock' => $this->stock,
            'format' => $this->format,
            'format_label' => $this->getFormatLabel(),
            'featured' => $this->featured,
        ];
    }
}

final class Ebook extends MediaItem
{
    public function getFormatLabel(): string
    {
        return 'E-book digital';
    }
}

final class PrintedBook extends MediaItem
{
    public function getFormatLabel(): string
    {
        return 'Buku cetak';
    }
}

final class Member
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
    ) {
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}

final class JsonStorage
{
    public function __construct(private string $path)
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Folder penyimpanan data tidak dapat ditulis.');
        }
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

interface LoanService
{
    public function borrow(int $mediaId, int $memberId): array;
}

final class JsonLoanService implements LoanService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function borrow(int $mediaId, int $memberId): array
    {
        return $this->storage->transact(static function (array &$data) use ($mediaId, $memberId): array {
            $mediaIndex = self::findIndex($data['books'] ?? [], $mediaId);
            $memberIndex = self::findIndex($data['members'] ?? [], $memberId);
            if ($mediaIndex === null) {
                throw new ApiException('Buku yang dipilih tidak ditemukan.', 404);
            }
            if ($memberIndex === null) {
                throw new ApiException('Anggota yang dipilih tidak ditemukan.', 404);
            }
            if ((int) $data['books'][$mediaIndex]['stock'] < 1) {
                throw new ApiException('Maaf, stok buku ini sudah habis.', 409);
            }

            $book = $data['books'][$mediaIndex];
            $member = $data['members'][$memberIndex];
            $loanDate = new DateTimeImmutable('today');
            $loan = [
                'id' => self::nextIdentifier($data['loans'] ?? []),
                'media_id' => $mediaId,
                'member_id' => $memberId,
                'loan_date' => $loanDate->format('Y-m-d'),
                'due_date' => $loanDate->modify('+7 days')->format('Y-m-d'),
                'status' => 'active',
            ];

            $data['books'][$mediaIndex]['stock']--;
            $data['loans'][] = $loan;
            return [
                'loan' => $loan,
                'media_title' => $book['title'],
                'member_name' => $member['name'],
            ];
        });
    }

    private static function findIndex(array $rows, int $id): ?int
    {
        foreach ($rows as $index => $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $index;
            }
        }
        return null;
    }

    public static function nextIdentifier(array $rows): int
    {
        return empty($rows) ? 1 : max(array_map(static fn (array $row): int => (int) $row['id'], $rows)) + 1;
    }
}

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public int $statusCode = 400)
    {
        parent::__construct($message);
    }
}

final class LibraryApi
{
    private JsonStorage $storage;
    private LoanService $loanService;

    public function __construct()
    {
        $this->storage = new JsonStorage(__DIR__ . DIRECTORY_SEPARATOR . 'storage.json');
        if (!$this->storage->exists() || filesize(__DIR__ . DIRECTORY_SEPARATOR . 'storage.json') === 0) {
            $this->storage->write(self::seedData());
        }
        $this->loanService = new JsonLoanService($this->storage);
    }

    public function handle(string $action, string $method): array
    {
        if ($action === 'bootstrap' && $method === 'GET') {
            return $this->bootstrap();
        }

        $input = $this->input();
        if ($action === 'borrow' && $method === 'POST') {
            $mediaId = filter_var($input['media_id'] ?? null, FILTER_VALIDATE_INT);
            $memberId = filter_var($input['member_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$mediaId || !$memberId) {
                throw new ApiException('Pilih buku dan anggota yang valid.');
            }
            return $this->loanService->borrow($mediaId, $memberId);
        }
        if ($action === 'add_member' && $method === 'POST') {
            return $this->addMember($input);
        }
        throw new ApiException('Aksi tidak tersedia.', 404);
    }

    private function bootstrap(): array
    {
        $data = $this->storage->read();
        $books = array_map(static function (array $book): array {
            $class = ($book['format'] ?? '') === 'ebook' ? Ebook::class : PrintedBook::class;
            return (new $class(
                (int) $book['id'],
                (string) $book['title'],
                (string) $book['author'],
                (string) $book['category'],
                (int) $book['stock'],
                (string) $book['format'],
                (bool) ($book['featured'] ?? false),
            ))->toArray();
        }, $data['books'] ?? []);

        $members = array_map(
            static fn (array $member): array => (new Member(
                (int) $member['id'],
                (string) $member['name'],
                (string) $member['email'],
            ))->toArray(),
            $data['members'] ?? [],
        );
        $memberNames = [];
        foreach ($members as $member) {
            $memberNames[$member['id']] = $member['name'];
        }
        $bookDetails = [];
        foreach ($books as $book) {
            $bookDetails[$book['id']] = $book;
        }
        $loans = array_map(static function (array $loan) use ($memberNames, $bookDetails): array {
            $book = $bookDetails[$loan['media_id']] ?? [];
            return [
                ...$loan,
                'member_name' => $memberNames[$loan['member_id']] ?? 'Anggota tidak ditemukan',
                'media_title' => $book['title'] ?? 'Buku tidak ditemukan',
                'author' => $book['author'] ?? '',
            ];
        }, $data['loans'] ?? []);

        return ['books' => $books, 'members' => $members, 'loans' => $loans];
    }

    private function addMember(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            throw new ApiException('Nama harus terdiri dari 2 sampai 60 karakter.');
        }
        if (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Masukkan alamat email yang valid.');
        }

        return $this->storage->transact(static function (array &$data) use ($name, $email): array {
            foreach ($data['members'] ?? [] as $member) {
                if (mb_strtolower($member['email']) === mb_strtolower($email)) {
                    throw new ApiException('Alamat email ini sudah terdaftar.');
                }
            }
            $members = $data['members'] ?? [];
            $member = (new Member(JsonLoanService::nextIdentifier($members), $name, $email))->toArray();
            $data['members'][] = $member;
            return $member;
        });
    }

    private function input(): array
    {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($decoded)) {
            throw new ApiException('Data yang dikirim tidak valid.');
        }
        return $decoded;
    }

    private static function seedData(): array
    {
        $books = [
            ['title' => 'Filosofi Teras', 'author' => 'Henry Manampiring', 'category' => 'Pengembangan Diri', 'stock' => 6, 'format' => 'print', 'featured' => true],
            ['title' => 'Laut Bercerita', 'author' => 'Leila S. Chudori', 'category' => 'Fiksi', 'stock' => 4, 'format' => 'print', 'featured' => true],
            ['title' => 'Atomic Habits', 'author' => 'James Clear', 'category' => 'Pengembangan Diri', 'stock' => 3, 'format' => 'ebook', 'featured' => true],
            ['title' => 'Bumi Manusia', 'author' => 'Pramoedya Ananta Toer', 'category' => 'Sastra', 'stock' => 5, 'format' => 'print', 'featured' => true],
            ['title' => 'Sebuah Seni untuk Bersikap Bodo Amat', 'author' => 'Mark Manson', 'category' => 'Pengembangan Diri', 'stock' => 4, 'format' => 'ebook', 'featured' => false],
            ['title' => 'Pulang', 'author' => 'Tere Liye', 'category' => 'Fiksi', 'stock' => 3, 'format' => 'print', 'featured' => false],
            ['title' => 'Laskar Pelangi', 'author' => 'Andrea Hirata', 'category' => 'Sastra', 'stock' => 5, 'format' => 'print', 'featured' => false],
            ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'category' => 'Sejarah', 'stock' => 2, 'format' => 'ebook', 'featured' => false],
            ['title' => 'The Psychology of Money', 'author' => 'Morgan Housel', 'category' => 'Bisnis', 'stock' => 4, 'format' => 'ebook', 'featured' => false],
            ['title' => 'Cantik Itu Luka', 'author' => 'Eka Kurniawan', 'category' => 'Sastra', 'stock' => 2, 'format' => 'print', 'featured' => false],
            ['title' => 'Pulang Pergi', 'author' => 'Tere Liye', 'category' => 'Fiksi', 'stock' => 3, 'format' => 'ebook', 'featured' => false],
            ['title' => 'Laut Bercerita: Edisi Digital', 'author' => 'Leila S. Chudori', 'category' => 'Fiksi', 'stock' => 8, 'format' => 'ebook', 'featured' => false],
        ];
        foreach ($books as $index => &$book) {
            $book['id'] = $index + 1;
        }
        unset($book);

        $members = [
            ['id' => 1, 'name' => 'Nabila Putri', 'email' => 'nabila@example.com'],
            ['id' => 2, 'name' => 'Raka Pratama', 'email' => 'raka@example.com'],
            ['id' => 3, 'name' => 'Aulia Rahma', 'email' => 'aulia@example.com'],
            ['id' => 4, 'name' => 'Dimas Saputra', 'email' => 'dimas@example.com'],
        ];
        $today = new DateTimeImmutable('today');
        $loans = [
            ['id' => 1, 'media_id' => 1, 'member_id' => 1, 'loan_date' => $today->modify('-2 days')->format('Y-m-d'), 'due_date' => $today->modify('+5 days')->format('Y-m-d'), 'status' => 'active'],
            ['id' => 2, 'media_id' => 3, 'member_id' => 3, 'loan_date' => $today->modify('-1 day')->format('Y-m-d'), 'due_date' => $today->modify('+6 days')->format('Y-m-d'), 'status' => 'active'],
        ];
        $books[0]['stock']--;
        $books[2]['stock']--;

        return ['books' => $books, 'members' => $members, 'loans' => $loans];
    }
}

$statusCode = 200;
try {
    $api = new LibraryApi();
    $result = $api->handle(
        (string) ($_GET['action'] ?? ''),
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    );
} catch (ApiException $exception) {
    $statusCode = $exception->statusCode;
    $result = ['error' => $exception->getMessage()];
} catch (Throwable $exception) {
    $statusCode = 500;
    $result = ['error' => 'Terjadi gangguan pada server. Coba muat ulang halaman.'];
    error_log($exception->__toString());
}

http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
