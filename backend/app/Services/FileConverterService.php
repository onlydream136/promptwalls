<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class FileConverterService
{
    /**
     * Convert a file to plain text based on its type.
     */
    public function convertToText(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt', 'csv', 'log' => $this->readPlainText($filePath),
            'pdf' => $this->convertPdf($filePath),
            'docx', 'doc' => $this->convertWord($filePath),
            'xlsx', 'xls' => $this->convertSpreadsheet($filePath),
            'pptx', 'ppt' => $this->convertPresentation($filePath),
            'rtf' => $this->convertRtf($filePath),
            default => $this->readPlainText($filePath),
        };
    }

    /**
     * Check if the file is an image (needs OCR instead of text conversion).
     */
    public function isImage(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp']);
    }

    /**
     * Get the file type from extension.
     */
    public function getFileType(string $filePath): string
    {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }

    private function readPlainText(string $filePath): string
    {
        return file_get_contents($filePath) ?: '';
    }

    private function convertPdf(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    private function convertWord(string $filePath): string
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
        } catch (\Exception $e) {
            // File may not be a real docx (e.g. plain text with .docx extension)
            return $this->readPlainText($filePath);
        }

        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractElementText($element) . "\n";
            }
        }

        return trim($text);
    }

    private function extractElementText($element): string
    {
        // PreserveText: getText() returns array with HYPERLINK field codes mixed in
        if ($element instanceof \PhpOffice\PhpWord\Element\PreserveText) {
            $parts = $element->getText();
            if (!is_array($parts)) {
                return is_string($parts) ? $parts : '';
            }
            $clean = [];
            foreach ($parts as $part) {
                if (!is_string($part)) continue;
                // Skip HYPERLINK field codes, extract the URL/email from them
                // Note: quotes may be HTML-encoded as &quot;
                if (preg_match('/\{\s*HYPERLINK\s+(?:&quot;|")?mailto:([^"&\s}]+)/i', $part, $m)) {
                    $clean[] = $m[1];
                } elseif (preg_match('/\{\s*HYPERLINK\s+(?:&quot;|")?([^"&\s}]+)/i', $part, $m)) {
                    $clean[] = $m[1];
                } elseif (!preg_match('/HYPERLINK/i', $part)) {
                    $clean[] = $part;
                }
            }
            return implode('', $clean);
        }

        // Link element: extract display text only
        if ($element instanceof \PhpOffice\PhpWord\Element\Link) {
            return $element->getText() ?? '';
        }

        // Text element: leaf node
        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
            return $element->getText() ?? '';
        }

        // Field element: skip raw field codes
        if ($element instanceof \PhpOffice\PhpWord\Element\Field) {
            return '';
        }

        // Container elements (TextRun, Table, Row, Cell, etc.): recurse children only
        if (method_exists($element, 'getElements')) {
            $text = '';
            foreach ($element->getElements() as $child) {
                $text .= $this->extractElementText($child);
            }
            return $text;
        }

        // Fallback
        if (method_exists($element, 'getText')) {
            $result = $element->getText();
            if (is_array($result)) {
                return implode(' ', array_filter($result, 'is_string'));
            }
            return is_string($result) ? $result : '';
        }

        return '';
    }

    private function convertSpreadsheet(string $filePath): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($filePath);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $text .= "=== Sheet: {$sheet->getTitle()} ===\n";
            foreach ($sheet->toArray() as $row) {
                $text .= implode("\t", array_map(fn($cell) => (string) $cell, $row)) . "\n";
            }
            $text .= "\n";
        }

        return trim($text);
    }

    private function convertPresentation(string $filePath): string
    {
        // PhpPresentation support
        $text = '';
        try {
            $reader = \PhpOffice\PhpPresentation\IOFactory::createReader('PowerPoint2007');
            $presentation = $reader->load($filePath);

            foreach ($presentation->getAllSlides() as $index => $slide) {
                $text .= "=== Slide " . ($index + 1) . " ===\n";
                foreach ($slide->getShapeCollection() as $shape) {
                    if (method_exists($shape, 'getText')) {
                        $text .= $shape->getText() . "\n";
                    }
                }
            }
        } catch (\Exception $e) {
            $text = "Error reading presentation: {$e->getMessage()}";
        }

        return trim($text);
    }

    private function convertRtf(string $filePath): string
    {
        $content = file_get_contents($filePath);
        // Basic RTF to text: strip RTF control words
        $text = preg_replace('/\{\\\\[^{}]*\}/', '', $content);
        $text = preg_replace('/\\\\[a-z]+\d*\s?/', '', $text);
        $text = preg_replace('/[{}]/', '', $text);
        return trim($text);
    }
}
