<?php
declare(strict_types=1);

namespace RuangBaca\Contracts;

interface LoanService
{
    public function borrow(int $mediaId, int $memberId): array;
}
