<?php

namespace App\Services;

class TextChunker
{
    private const MIN_CHUNK_LENGTH = 20;

    public function __construct(
        private int $chunkSize = 600,
        private int $overlap = 100,
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

        // Filter out chunks that are too short or contain no meaningful content
        $chunks = array_values(array_filter($chunks, fn (string $chunk) => $this->isMeaningful($chunk)));

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
            // If a single part exceeds chunkSize, split it with remaining separators
            if (strlen($part) > $this->chunkSize) {
                if (trim($current) !== '') {
                    $chunks[] = trim($current);
                    $current = '';
                }
                $subChunks = $this->splitRecursive($part, $separators);
                array_push($chunks, ...$subChunks);
                continue;
            }

            $candidate = $current === '' ? $part : $current . $separator . $part;

            if (strlen($candidate) > $this->chunkSize && $current !== '') {
                $chunks[] = trim($current);
                $current = $part;
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

    private function isMeaningful(string $chunk): bool
    {
        // Strip punctuation and whitespace to check actual content
        $stripped = preg_replace('/[\s\p{P}]+/u', '', $chunk);

        return mb_strlen($stripped) >= self::MIN_CHUNK_LENGTH;
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
