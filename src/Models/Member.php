<?php
declare(strict_types=1);

namespace RuangBaca\Models;

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
