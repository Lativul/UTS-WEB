<?php
declare(strict_types=1);

namespace RuangBaca\Models;

final class PrintedBook extends MediaItem
{
    public function getFormatLabel(): string
    {
        return 'Buku cetak';
    }
}
