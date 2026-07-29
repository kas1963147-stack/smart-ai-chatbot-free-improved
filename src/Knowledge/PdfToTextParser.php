<?php
declare(strict_types=1);
/**
 * PDF to Text Parser
 * 
 * Extracts text from PDF files using the smalot/pdfparser library.
 * Formats the extracted text into basic Markdown.
 * 
 * @package Quarksol\SmartChatbot\Knowledge
 */

namespace Quarksol\SmartChatbot\Knowledge;

use Smalot\PdfParser\Parser;

if (!defined('ABSPATH')) {
    exit;
}

class PdfToTextParser {
    
    /**
     * Parse a PDF file and extract text as Markdown
     * 
     * @param string $filePath Absolute path to the PDF file
     * @return string Extracted markdown text
     * @throws \Exception If file cannot be read or parsed
     */
    public function parse(string $filePath): string {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \Exception("Cannot read PDF file: {$filePath}");
        }

        try {
            // Check if library is available
            if (!class_exists('\Smalot\PdfParser\Parser')) {
                throw new \Exception("PDF Parser library is not installed. Please run 'composer require smalot/pdfparser'.");
            }

            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            return $this->formatTextAsMarkdown($text);
            
        } catch (\Throwable $e) {
            throw new \Exception("Failed to parse PDF: " . $e->getMessage());
        }
    }

    /**
     * Format raw extracted PDF text into somewhat readable Markdown
     */
    private function formatTextAsMarkdown(string $text): string {
        // Remove excessive empty lines
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        
        // Remove null bytes and non-printable characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        $lines = explode("\n", $text);
        $formattedLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                $formattedLines[] = "";
                continue;
            }

            // Simple heuristic to detect headers: short lines, all caps, or ending with colon
            $isShort = strlen($line) < 60;
            $isAllCaps = strtoupper($line) === $line && preg_match('/[A-Z]/', $line);
            
            if ($isShort && ($isAllCaps || str_ends_with($line, ':'))) {
                $formattedLines[] = "### " . ucwords(strtolower($line));
            } else {
                // If the previous line was a regular text line, join them (PDFs often break paragraphs arbitrarily)
                if (!empty($formattedLines) && !str_starts_with(end($formattedLines), '#') && !empty(end($formattedLines))) {
                    $lastIdx = count($formattedLines) - 1;
                    $formattedLines[$lastIdx] .= " " . $line;
                } else {
                    $formattedLines[] = $line;
                }
            }
        }

        return trim(implode("\n\n", array_filter($formattedLines, fn($l) => strlen(trim($l)) > 0)));
    }
}
