<?php

namespace App\Services\DTOs;

class ExtractedContent
{
    public function __construct(
        public readonly string $text,
        public readonly string $type = 'text',
        public readonly array $sheets = [],
    ) {}
}
