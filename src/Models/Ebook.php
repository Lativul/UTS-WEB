<?php
declare(strict_types=1);

namespace RuangBaca\Models;

final class Ebook extends MediaItem
{
    public function getFormatLabel(): string
    {
        return 'E-book digital';
    }
}
