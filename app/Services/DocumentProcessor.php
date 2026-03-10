<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentProcessor
{
    public function extractText(string $filePath, string $mimeType): string
    {
        $text = match (true) {
            $mimeType === 'application/pdf' => $this->extractFromPdf($filePath),
            $mimeType === 'text/plain' => $this->extractFromTxt($filePath),
            $this->isWord($mimeType) => $this->extractFromWord($filePath, $mimeType),
            $this->isSpreadsheet($mimeType) => $this->extractFromSpreadsheet($filePath),
            str_starts_with($mimeType, 'image/') => $this->extractFromImage($filePath),
            default => throw new \RuntimeException("Unsupported mime type: {$mimeType}"),
        };

        // Sanitize to valid UTF-8 to prevent json_encode failures
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
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

    private function extractFromSpreadsheet(string $filePath): string
    {
        $spreadsheet = IOFactory::load($filePath);
        $lines = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();

            try {
                $data = $sheet->toArray(null, true, true, false);
            } catch (\Throwable) {
                // Fallback: read raw values without calculating formulas
                try {
                    $data = $sheet->toArray(null, false, false, false);
                } catch (\Throwable) {
                    continue;
                }
            }

            if (empty($data)) {
                continue;
            }

            $lines[] = "--- Sheet: {$sheetName} ---";
            $headers = array_shift($data);

            foreach ($data as $row) {
                $parts = [];
                foreach ($row as $i => $cell) {
                    if ($cell !== null && $cell !== '') {
                        $cellStr = is_scalar($cell) ? (string) $cell : '';
                        if ($cellStr !== '') {
                            $header = $headers[$i] ?? "Column {$i}";
                            $headerStr = is_scalar($header) ? (string) $header : "Column {$i}";
                            $parts[] = "{$headerStr}: {$cellStr}";
                        }
                    }
                }
                if (!empty($parts)) {
                    $lines[] = implode(' | ', $parts);
                }
            }
        }

        return implode("\n", $lines);
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
                if (method_exists($element, 'getText')) {
                    $line = $element->getText();
                    if ($line !== null && trim($line) !== '') {
                        $text[] = $line;
                    }
                }
            }
        }

        return implode("\n", $text);
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
