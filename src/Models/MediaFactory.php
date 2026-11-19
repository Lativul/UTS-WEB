<?php
declare(strict_types=1);

namespace RuangBaca\Models;

final class MediaFactory
{
    public static function create(array $data): MediaItem
    {
        $class = ($data['format'] ?? '') === 'ebook' ? Ebook::class : PrintedBook::class;

        return new $class(
            (int) $data['id'],
            (string) $data['title'],
            (string) $data['author'],
            (string) $data['category'],
            (int) $data['stock'],
            (string) $data['format'],
            (bool) ($data['featured'] ?? false),
        );
    }
}
