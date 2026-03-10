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
        return match (true) {
            $mimeType === 'application/pdf' => $this->extractFromPdf($filePath),
            $mimeType === 'text/plain' => $this->extractFromTxt($filePath),
            $this->isWord($mimeType) => $this->extractFromWord($filePath, $mimeType),
            $this->isSpreadsheet($mimeType) => $this->extractFromSpreadsheet($filePath),
            str_starts_with($mimeType, 'image/') => $this->extractFromImage($filePath),
            default => throw new \RuntimeException("Unsupported mime type: {$mimeType}"),
        };
    }

    private function extractFromPdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);

        return $pdf->getText();
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
            $data = $sheet->toArray();

            if (empty($data)) {
                continue;
            }

            $lines[] = "--- Sheet: {$sheetName} ---";
            $headers = array_shift($data);

            foreach ($data as $row) {
                $parts = [];
                foreach ($row as $i => $cell) {
                    if ($cell !== null && $cell !== '') {
                        $header = $headers[$i] ?? "Column {$i}";
                        $parts[] = "{$header}: {$cell}";
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
