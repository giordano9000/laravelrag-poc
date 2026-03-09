<?php

namespace App\Services;

class TextChunker
{
    public function __construct(
        private int $chunkSize = 2000,
        private int $overlap = 200,
        private array $separators = ["\n\n", "\n", ". ", " "],
    ) {}

    /**
     * @return array<int, array{content: string, index: int}>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if (strlen($text) === 0) {
            return [];
        }

        if (strlen($text) <= $this->chunkSize) {
            return [['content' => $text, 'index' => 0]];
        }

        $chunks = $this->splitRecursive($text, $this->separators);

        return array_values(array_map(
            fn (string $chunk, int $i) => ['content' => $chunk, 'index' => $i],
            $chunks,
            array_keys($chunks),
        ));
    }

    private function splitRecursive(string $text, array $separators): array
    {
        if (empty($separators)) {
            return $this->splitBySize($text);
        }

        $separator = array_shift($separators);
        $parts = explode($separator, $text);

        if (count($parts) === 1) {
            return $this->splitRecursive($text, $separators);
        }

        $chunks = [];
        $current = '';

        foreach ($parts as $part) {
            $candidate = $current === '' ? $part : $current . $separator . $part;

            if (strlen($candidate) > $this->chunkSize && $current !== '') {
                $chunks[] = trim($current);
                // Start new chunk with overlap from end of previous
                $overlapText = $this->getOverlapText($current, $separator);
                $current = $overlapText !== '' ? $overlapText . $separator . $part : $part;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function splitBySize(string $text): array
    {
        $chunks = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $chunk = substr($text, $offset, $this->chunkSize);
            $chunks[] = trim($chunk);
            $offset += $this->chunkSize - $this->overlap;
        }

        return $chunks;
    }

    private function getOverlapText(string $text, string $separator): string
    {
        if ($this->overlap <= 0) {
            return '';
        }

        $end = substr($text, -$this->overlap);

        // Try to break at a separator boundary
        $pos = strpos($end, $separator);
        if ($pos !== false) {
            return substr($end, $pos + strlen($separator));
        }

        return $end;
    }
}
