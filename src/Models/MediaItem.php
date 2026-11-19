<?php
declare(strict_types=1);

namespace RuangBaca\Models;

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
