<?php
declare(strict_types=1);

namespace RuangBaca\Services;

use DateTimeImmutable;
use RuangBaca\Contracts\LoanService;
use RuangBaca\Exceptions\ApiException;
use RuangBaca\Storage\JsonStorage;

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
