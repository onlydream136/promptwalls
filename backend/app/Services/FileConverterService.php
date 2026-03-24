<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use App\Models\WordPair;
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

    /**
     * Save desensitized content back to the original file format.
     * Returns the output file path.
     */
    public function saveDesensitized(string $sourcePath, string $desensitizedText, string $outputDir, string $filename, int $fileRecordId = 0): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Get replacement map from WordPair records (by user_id for consistency)
        $replacements = [];
        if ($fileRecordId > 0) {
            $fileRecord = \App\Models\FileRecord::find($fileRecordId);
            if ($fileRecord && $fileRecord->user_id) {
                $wordPairs = WordPair::where('user_id', $fileRecord->user_id)->get();
            } else {
                $wordPairs = WordPair::where('file_record_id', $fileRecordId)->get();
            }
            foreach ($wordPairs as $pair) {
                $replacements[$pair->original_value] = $pair->placeholder;
            }
        }

        return match ($extension) {
            'docx', 'doc' => $this->saveAsDocx($sourcePath, $desensitizedText, $outputDir, $filename, $replacements),
            'xlsx', 'xls' => $this->saveAsXlsx($sourcePath, $desensitizedText, $outputDir, $filename, $replacements),
            'csv' => $this->saveAsOriginal($desensitizedText, $outputDir, $filename, 'csv'),
            'txt', 'log' => $this->saveAsOriginal($desensitizedText, $outputDir, $filename, $extension),
            default => $this->saveAsOriginal($desensitizedText, $outputDir, $filename, 'txt'),
        };
    }

    /**
     * Save desensitized DOCX by replacing text in the original document.
     */
    private function saveAsDocx(string $sourcePath, string $desensitizedText, string $outputDir, string $filename, array $replacements): string
    {
        $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'desensitized_' . $filename;
        if (!str_ends_with(strtolower($outputFile), '.docx')) {
            $outputFile = preg_replace('/\.[^.]+$/', '.docx', $outputFile);
        }

        try {
            if (empty($replacements)) {
                copy($sourcePath, $outputFile);
                return $outputFile;
            }

            // Directly manipulate DOCX XML to replace text (handles links, text, everything)
            copy($sourcePath, $outputFile);
            $zip = new \ZipArchive();
            if ($zip->open($outputFile) !== true) {
                throw new \Exception('Cannot open DOCX as ZIP');
            }

            // Process all XML files that may contain text
            $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'];
            foreach ($xmlFiles as $xmlFile) {
                $xml = $zip->getFromName($xmlFile);
                if ($xml === false) continue;

                $modified = false;
                foreach ($replacements as $original => $placeholder) {
                    $escaped = htmlspecialchars($original, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    if (strpos($xml, $escaped) !== false) {
                        $escapedPlaceholder = htmlspecialchars($placeholder, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                        $xml = str_replace($escaped, $escapedPlaceholder, $xml);
                        // Also replace in hyperlink URLs (mailto:, http://, etc.)
                        $xml = str_replace(urlencode($original), urlencode($placeholder), $xml);
                        $modified = true;
                    }
                    // Also try raw (non-escaped) replacement for URLs in attributes
                    if (strpos($xml, $original) !== false) {
                        $xml = str_replace($original, $placeholder, $xml);
                        $modified = true;
                    }
                }

                if ($modified) {
                    $zip->addFromString($xmlFile, $xml);
                }
            }

            $zip->close();
        } catch (\Exception $e) {
            $baseName = preg_replace('/\.[^.]+$/', '', $filename);
            $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'desensitized_' . $baseName . '.txt';
            file_put_contents($outputFile, $desensitizedText);
        }

        return $outputFile;
    }

    /**
     * Save desensitized XLSX by replacing text in the original spreadsheet.
     */
    private function saveAsXlsx(string $sourcePath, string $desensitizedText, string $outputDir, string $filename, array $replacements): string
    {
        $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'desensitized_' . $filename;
        if (!str_ends_with(strtolower($outputFile), '.xlsx')) {
            $outputFile = preg_replace('/\.[^.]+$/', '.xlsx', $outputFile);
        }

        try {
            $spreadsheet = SpreadsheetIOFactory::load($sourcePath);

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    foreach ($row->getCellIterator() as $cell) {
                        $cellValue = (string) $cell->getValue();
                        if (!empty($cellValue)) {
                            $newValue = str_replace(
                                array_keys($replacements),
                                array_values($replacements),
                                $cellValue
                            );
                            if ($newValue !== $cellValue) {
                                $cell->setValue($newValue);
                            }
                        }
                    }
                }
            }

            $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($outputFile);
        } catch (\Exception $e) {
            $baseName = preg_replace('/\.[^.]+$/', '', $filename);
            $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'desensitized_' . $baseName . '.txt';
            file_put_contents($outputFile, $desensitizedText);
        }

        return $outputFile;
    }

    /**
     * Save as original text-based format (txt, csv, etc.)
     */
    private function saveAsOriginal(string $desensitizedText, string $outputDir, string $filename, string $ext): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'desensitized_' . $baseName . '.' . $ext;
        file_put_contents($outputFile, $desensitizedText);
        return $outputFile;
    }

    /**
     * Replace text content in PhpWord elements recursively.
     */
    private function replaceInElements($elements, array $replacements): void
    {
        if (empty($replacements)) return;

        foreach ($elements as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                $text = $element->getText();
                if ($text) {
                    $newText = str_replace(array_keys($replacements), array_values($replacements), $text);
                    if ($newText !== $text) {
                        $element->setText($newText);
                    }
                }
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Link) {
                // Links: replace text and clear the hyperlink to make it plain text
                $linkText = $element->getText();
                if ($linkText) {
                    $newText = str_replace(array_keys($replacements), array_values($replacements), $linkText);
                    try {
                        $ref = new \ReflectionClass($element);
                        $prop = $ref->getProperty('text');
                        $prop->setAccessible(true);
                        $prop->setValue($element, $newText);
                        // Clear the source URL so it becomes plain text, prevents PhpWord from dropping the element
                        $srcProp = $ref->getProperty('source');
                        $srcProp->setAccessible(true);
                        $srcProp->setValue($element, '');
                    } catch (\Exception $e) {
                        // Skip if reflection fails
                    }
                }
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                // TextRun contains child elements (Text, Link, etc.)
                $this->replaceInElements($element->getElements(), $replacements);
            } elseif (method_exists($element, 'getElements')) {
                $this->replaceInElements($element->getElements(), $replacements);
            }
        }
    }
}
