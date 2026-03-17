<?php

namespace App\Services;

class SpreadsheetChunker
{
    public function __construct(
        private int $maxChunkChars = 1500,
        private int $overlapRows = 2,
    ) {}

    /**
     * Chunk a single sheet into schema + data chunks.
     *
     * @param  string  $sheetName
     * @param  array   $headers
     * @param  array   $rows
     * @return array<int, array{content: string, index: int}>
     */
    public function chunkSheet(string $sheetName, array $headers, array $rows): array
    {
        $chunks = [];

        // Schema chunk always first
        $chunks[] = $this->generateSchemaChunk($sheetName, $headers, $rows);

        // Choose format based on header line length
        $headerLine = '| ' . implode(' | ', $headers) . ' |';
        if (mb_strlen($headerLine) > 300) {
            $dataChunks = $this->chunkAsKeyValue($sheetName, $headers, $rows);
        } else {
            $dataChunks = $this->chunkAsMarkdownTable($sheetName, $headers, $rows);
        }

        array_push($chunks, ...$dataChunks);

        // Re-index
        return array_map(
            fn (string $content, int $i) => ['content' => $content, 'index' => $i],
            $chunks,
            array_keys($chunks),
        );
    }

    public function generateSchemaChunk(string $sheetName, array $headers, array $rows): string
    {
        $colCount = count($headers);
        $rowCount = count($rows);

        // Detect types
        $types = [];
        foreach ($headers as $i => $header) {
            $types[] = $header . ' (' . $this->detectColumnType($rows, $i) . ')';
        }

        $lines = [
            "## Sheet: {$sheetName}",
            "Colonne ({$colCount}): " . implode(', ', $headers),
            "Righe: {$rowCount}",
            "Tipi: " . implode(', ', $types),
            "Esempio:",
        ];

        // Add example rows as markdown table (max 2)
        $exampleRows = array_slice($rows, 0, 2);
        $lines[] = '| ' . implode(' | ', $headers) . ' |';
        $lines[] = '|' . implode('|', array_fill(0, $colCount, '---')) . '|';
        foreach ($exampleRows as $row) {
            $cells = array_map(fn ($i) => $this->formatCell($row[$i] ?? null), array_keys($headers));
            $lines[] = '| ' . implode(' | ', $cells) . ' |';
        }

        return implode("\n", $lines);
    }

    public function chunkAsMarkdownTable(string $sheetName, array $headers, array $rows): array
    {
        $colCount = count($headers);
        $headerLine = '| ' . implode(' | ', $headers) . ' |';
        $separatorLine = '|' . implode('|', array_fill(0, $colCount, '---')) . '|';
        $prefix = "**Sheet: {$sheetName}**\n{$headerLine}\n{$separatorLine}\n";
        $prefixLen = mb_strlen($prefix);

        $chunks = [];
        $currentRows = [];
        $currentLen = $prefixLen;

        foreach ($rows as $rowIdx => $row) {
            $cells = array_map(fn ($i) => $this->formatCell($row[$i] ?? null), range(0, $colCount - 1));
            $rowLine = '| ' . implode(' | ', $cells) . ' |';
            $rowLen = mb_strlen($rowLine) + 1; // +1 for newline

            if ($currentLen + $rowLen > $this->maxChunkChars && !empty($currentRows)) {
                $chunks[] = $prefix . implode("\n", $currentRows);

                // Overlap: keep last N rows
                $currentRows = array_slice($currentRows, -$this->overlapRows);
                $currentLen = $prefixLen + array_sum(array_map(fn ($r) => mb_strlen($r) + 1, $currentRows));
            }

            $currentRows[] = $rowLine;
            $currentLen += $rowLen;
        }

        if (!empty($currentRows)) {
            $chunks[] = $prefix . implode("\n", $currentRows);
        }

        return $chunks;
    }

    public function chunkAsKeyValue(string $sheetName, array $headers, array $rows): array
    {
        $prefix = "**Sheet: {$sheetName}**\n";
        $prefixLen = mb_strlen($prefix);

        $chunks = [];
        $currentEntries = [];
        $currentLen = $prefixLen;

        foreach ($rows as $rowIdx => $row) {
            $kvLines = [];
            foreach ($headers as $i => $header) {
                $val = $this->formatCell($row[$i] ?? null);
                $kvLines[] = "{$header}: {$val}";
            }
            $entry = implode("\n", $kvLines);
            $entryLen = mb_strlen($entry) + 2; // +2 for double-newline separator

            if ($currentLen + $entryLen > $this->maxChunkChars && !empty($currentEntries)) {
                $chunks[] = $prefix . implode("\n\n", $currentEntries);

                // Overlap: keep last N entries
                $currentEntries = array_slice($currentEntries, -$this->overlapRows);
                $currentLen = $prefixLen + array_sum(array_map(fn ($e) => mb_strlen($e) + 2, $currentEntries));
            }

            $currentEntries[] = $entry;
            $currentLen += $entryLen;
        }

        if (!empty($currentEntries)) {
            $chunks[] = $prefix . implode("\n\n", $currentEntries);
        }

        return $chunks;
    }

    public function detectColumnType(array $rows, int $colIndex): string
    {
        $sample = array_slice($rows, 0, 20);
        $types = ['numeric' => 0, 'date' => 0, 'text' => 0];

        foreach ($sample as $row) {
            $val = $row[$colIndex] ?? null;
            if ($val === null || $val === '' || $val === '-') {
                continue;
            }

            $val = is_scalar($val) ? (string) $val : '';
            if ($val === '') {
                continue;
            }

            if (is_numeric($val)) {
                $types['numeric']++;
            } elseif ($this->looksLikeDate($val)) {
                $types['date']++;
            } else {
                $types['text']++;
            }
        }

        $total = array_sum($types);
        if ($total === 0) {
            return 'text';
        }

        // If one type dominates (>= 70%), use it; otherwise mixed
        arsort($types);
        $dominant = array_key_first($types);
        if ($types[$dominant] / $total >= 0.7) {
            return $dominant;
        }

        return 'mixed';
    }

    public function formatCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        $str = is_scalar($value) ? (string) $value : '-';

        // Strip newlines
        $str = str_replace(["\r\n", "\r", "\n"], ' ', $str);

        return $str;
    }

    private function looksLikeDate(string $val): bool
    {
        // Common date patterns
        return (bool) preg_match(
            '/^\d{1,4}[\/-]\d{1,2}[\/-]\d{1,4}$|^\d{1,2}\.\d{1,2}\.\d{2,4}$/',
            trim($val)
        );
    }
}
