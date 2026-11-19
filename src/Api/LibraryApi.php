<?php
declare(strict_types=1);

namespace RuangBaca\Api;

use DateTimeImmutable;
use RuangBaca\Contracts\LoanService;
use RuangBaca\Exceptions\ApiException;
use RuangBaca\Models\MediaFactory;
use RuangBaca\Models\Member;
use RuangBaca\Services\JsonLoanService;
use RuangBaca\Storage\JsonStorage;

final class LibraryApi
{
    private JsonStorage $storage;
    private LoanService $loanService;

    public function __construct(?string $storagePath = null)
    {
        $path = $storagePath ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'storage.json';
        $this->storage = new JsonStorage($path);
        if (!$this->storage->exists() || filesize($path) === 0) {
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
        $books = array_map(
            static fn (array $book): array => MediaFactory::create($book)->toArray(),
            $data['books'] ?? [],
        );

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
