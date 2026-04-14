<?php

namespace App\Services;

use App\Services\DTOs\ExtractedContent;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentProcessor
{
    /**
     * Check if a mime type is supported for import/processing.
     * @deprecated Use MimeTypeService::isSupported() instead
     */
    public static function isSupportedMimeType(?string $mimeType): bool
    {
        return MimeTypeService::isSupported($mimeType);
    }

    public function extractText(string $filePath, string $mimeType): ExtractedContent
    {
        if ($this->isSpreadsheet($mimeType)) {
            return $this->extractFromSpreadsheet($filePath);
        }

        $text = match (true) {
            $mimeType === 'application/pdf' => $this->extractFromPdf($filePath),
            $mimeType === 'text/plain' => $this->extractFromTxt($filePath),
            $this->isWord($mimeType) => $this->extractFromWord($filePath, $mimeType),
            str_starts_with($mimeType, 'image/') => $this->extractFromImage($filePath),
            default => throw new \RuntimeException("Unsupported mime type: {$mimeType}"),
        };

        // Sanitize to valid UTF-8 to prevent json_encode failures
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return new ExtractedContent(text: $text, type: 'text');
    }

    private function extractFromPdf(string $filePath): string
    {
        // Try text extraction first
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Check if we got meaningful text (not just garbage from bad font encoding)
        if ($this->isValidExtractedText($text)) {
            return $text;
        }

        // Fallback: OCR via pdftoppm + tesseract (for scanned PDFs or PDFs with bad font encoding)
        return $this->extractFromPdfWithOcr($filePath);
    }

    /**
     * Check if extracted text is valid and readable, not garbled from font encoding issues.
     */
    private function isValidExtractedText(string $text): bool
    {
        $text = trim($text);

        // Too short - probably empty or failed extraction
        if (mb_strlen($text) < 50) {
            return false;
        }

        // Count "normal" alphanumeric characters vs total
        $alphanumeric = preg_match_all('/[a-zA-Z0-9àèéìòùÀÈÉÌÒÙäöüÄÖÜß]/u', $text);
        $total = mb_strlen($text);

        // If less than 40% alphanumeric, it's probably garbled text
        $ratio = $alphanumeric / max($total, 1);
        if ($ratio < 0.4) {
            return false;
        }

        // Check for common garbled patterns (sequences of symbols, equals signs, brackets)
        $garbagePatterns = preg_match_all('/[=\[\]{}|<>]{3,}|[^\w\s,.;:!?\'"()-]{5,}/u', $text);
        if ($garbagePatterns > 10) {
            return false;
        }

        return true;
    }

    private function extractFromPdfWithOcr(string $filePath): string
    {
        $tempDir = sys_get_temp_dir() . '/pdf_ocr_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Convert PDF pages to images
            $cmd = sprintf(
                'pdftoppm -png -r 300 %s %s/page',
                escapeshellarg($filePath),
                escapeshellarg($tempDir)
            );
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('pdftoppm failed: ' . implode("\n", $output));
            }

            // OCR each page image
            $pages = glob($tempDir . '/page-*.png');
            sort($pages);

            $text = [];
            foreach ($pages as $pageImage) {
                $ocr = new TesseractOCR($pageImage);
                $ocr->lang('ita', 'eng');
                $pageText = $ocr->run();

                if (trim($pageText) !== '') {
                    $text[] = $pageText;
                }
            }

            return implode("\n\n", $text);
        } finally {
            // Cleanup
            array_map('unlink', glob($tempDir . '/*'));
            rmdir($tempDir);
        }
    }

    private function extractFromTxt(string $filePath): string
    {
        return file_get_contents($filePath);
    }

    private function extractFromSpreadsheet(string $filePath): ExtractedContent
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheets = [];
        $previewLines = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();

            try {
                $data = $sheet->toArray(null, true, true, false);
            } catch (\Throwable) {
                try {
                    $data = $sheet->toArray(null, false, false, false);
                } catch (\Throwable) {
                    continue;
                }
            }

            if (empty($data)) {
                continue;
            }

            // First row as headers
            $rawHeaders = array_shift($data);
            $headers = [];
            foreach ($rawHeaders as $i => $h) {
                $hStr = ($h !== null && is_scalar($h)) ? trim((string) $h) : '';
                $headers[] = $hStr !== '' ? $hStr : 'Column ' . ($i + 1);
            }

            $headerCount = count($headers);

            // Process rows: pad to header length, remove fully empty rows
            $rows = [];
            foreach ($data as $row) {
                // Pad row to header count
                $row = array_pad(array_values($row), $headerCount, null);
                $row = array_slice($row, 0, $headerCount);

                // Skip fully empty rows
                $hasValue = false;
                foreach ($row as $cell) {
                    if ($cell !== null && $cell !== '') {
                        $hasValue = true;
                        break;
                    }
                }
                if (!$hasValue) {
                    continue;
                }

                $rows[] = $row;
            }

            if (empty($rows)) {
                continue;
            }

            $sheets[] = [
                'name' => $sheetName,
                'headers' => $headers,
                'rows' => $rows,
            ];

            // Build preview text
            $previewLines[] = "Sheet: {$sheetName} ({$headerCount} colonne, " . count($rows) . " righe)";
            $previewLines[] = "Colonne: " . implode(', ', $headers);
        }

        $previewText = implode("\n", $previewLines);
        $previewText = mb_convert_encoding($previewText, 'UTF-8', 'UTF-8');

        return new ExtractedContent(
            text: $previewText,
            type: 'spreadsheet',
            sheets: $sheets,
        );
    }

    private function extractFromImage(string $filePath): string
    {
        $ocr = new TesseractOCR($filePath);
        $ocr->lang('ita', 'eng');

        return $ocr->run();
    }

    private function extractFromWord(string $filePath, string $mimeType): string
    {
        if ($mimeType === 'application/msword') {
            return $this->extractFromDoc($filePath);
        }

        return $this->extractFromDocx($filePath);
    }

    private function extractFromDoc(string $filePath): string
    {
        $output = [];
        $returnCode = 0;
        exec('antiword ' . escapeshellarg($filePath) . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('antiword failed: ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function extractFromDocx(string $filePath): string
    {
        $phpWord = WordIOFactory::load($filePath, 'Word2007');
        $text = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $extractedText = $this->extractTextFromElement($element);

                // Pulisci il testo da spazi eccessivi e caratteri di controllo
                $extractedText = preg_replace('/\s+/', ' ', $extractedText);
                $extractedText = trim($extractedText);

                // Salta se è troppo corto o contiene solo caratteri non-alfanumerici
                if (strlen($extractedText) < 3) {
                    continue;
                }

                // Salta se è principalmente spazi/caratteri speciali
                if (preg_match('/^[\s\p{Z}\p{C}]+$/u', $extractedText)) {
                    continue;
                }

                if (!empty($extractedText)) {
                    $text[] = $extractedText;
                }
            }
        }

        return implode("\n", $text);
    }

    private function extractTextFromElement($element): string
    {
        $className = get_class($element);

        // Skip PageBreak
        if ($className === 'PhpOffice\PhpWord\Element\PageBreak') {
            return '';
        }

        // TextRun - extract from children only
        if ($className === 'PhpOffice\PhpWord\Element\TextRun') {
            $text = [];
            if (method_exists($element, 'getElements')) {
                foreach ($element->getElements() as $childElement) {
                    $childClass = get_class($childElement);

                    // Only extract from Text elements, skip TextBreak and others
                    if ($childClass === 'PhpOffice\PhpWord\Element\Text') {
                        $childText = $childElement->getText();
                        if ($childText !== null && trim($childText) !== '') {
                            $text[] = $childText;
                        }
                    }
                }
            }
            return implode(' ', $text);
        }

        // Table
        if ($className === 'PhpOffice\PhpWord\Element\Table') {
            $tableText = [];
            foreach ($element->getRows() as $row) {
                $rowText = [];
                foreach ($row->getCells() as $cell) {
                    $cellText = [];
                    foreach ($cell->getElements() as $cellElement) {
                        $extracted = $this->extractTextFromElement($cellElement);
                        if (!empty(trim($extracted))) {
                            $cellText[] = $extracted;
                        }
                    }
                    if (!empty($cellText)) {
                        $rowText[] = implode(' ', $cellText);
                    }
                }
                if (!empty($rowText)) {
                    $tableText[] = implode(' | ', $rowText);
                }
            }
            return implode("\n", $tableText);
        }

        // Direct Text element
        if ($className === 'PhpOffice\PhpWord\Element\Text') {
            $text = $element->getText();
            return ($text !== null) ? $text : '';
        }

        // TextBreak, Image, and other elements - skip
        return '';
    }

    private function isWord(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function isSpreadsheet(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ]);
    }
}
