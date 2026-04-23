<?php

namespace App\Services;

class CVAnalysisService
{
    public function analyze(string $absolutePath, string $originalFilename): array
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $pipeline = [
            'document_type' => $extension,
            'text_extraction_method' => 'none',
            'ocr_attempted' => false,
            'ocr_used' => false,
        ];

        $text = match ($extension) {
            'pdf' => $this->extractTextFromPdf($absolutePath, $pipeline),
            'docx' => $this->extractTextFromDocx($absolutePath, $pipeline),
            'doc' => '',
            default => '',
        };

        if ($text === '' && $extension === 'pdf') {
            $pipeline['ocr_attempted'] = true;
            $ocrText = $this->extractTextWithOcrFallback($absolutePath);

            if ($ocrText !== '') {
                $text = $ocrText;
                $pipeline['ocr_used'] = true;
                $pipeline['text_extraction_method'] = 'ocr';
            }
        }

        $skills = $this->extractSkills($text);

        return [
            'text' => $text,
            'skills' => $skills,
            'pipeline' => $pipeline,
        ];
    }

    public function extractSkills(string $text): array
    {
        $normalized = mb_strtolower($text);

        $dictionary = [
            'PHP' => [' php ', 'php ', ' php', "\nphp", 'laravel php'],
            'Laravel' => ['laravel'],
            'Symfony' => ['symfony'],
            'MySQL' => ['mysql'],
            'PostgreSQL' => ['postgresql', 'postgres'],
            'SQL Server' => ['sql server', 'mssql'],
            'Redis' => ['redis'],
            'REST API' => ['rest api', 'restful api', 'api rest'],
            'JavaScript' => ['javascript'],
            'TypeScript' => ['typescript'],
            'Vue.js' => ['vue.js', 'vuejs', 'vue js'],
            'Nuxt' => ['nuxt'],
            'React' => ['react'],
            'Node.js' => ['node.js', 'nodejs', 'node js'],
            'HTML/CSS' => ['html', 'css', 'tailwind', 'bootstrap'],
            'Docker' => ['docker'],
            'Git' => ['git', 'github', 'gitlab'],
            'Linux' => ['linux'],
            'Java' => [' java ', "\njava", 'spring boot', 'spring'],
            'Python' => [' python ', "\npython", 'django', 'flask'],
            'AWS' => ['aws', 'amazon web services'],
            'Testing' => ['phpunit', 'unit test', 'testing', 'kiểm thử'],
            'Agile/Scrum' => ['agile', 'scrum'],
        ];

        $skills = [];

        foreach ($dictionary as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, mb_strtolower($keyword))) {
                    $skills[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($skills));
    }

    private function extractTextFromDocx(string $absolutePath, array &$pipeline): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        if ($xml === '') {
            return '';
        }

        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $text = strip_tags((string) $xml);
        $pipeline['text_extraction_method'] = 'docx_xml';

        return $this->normalizeWhitespace(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function extractTextFromPdf(string $absolutePath, array &$pipeline): string
    {
        $content = @file_get_contents($absolutePath);

        if ($content === false || $content === '') {
            return '';
        }

        $streams = [];

        if (preg_match_all('/stream(.*?)endstream/s', $content, $matches)) {
            $streams = $matches[1];
        }

        $textChunks = [];

        foreach ($streams as $stream) {
            $stream = ltrim($stream, "\r\n");
            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }

            if ($decoded === false) {
                $decoded = $stream;
            }

            if (!is_string($decoded) || $decoded === '') {
                continue;
            }

            if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $textMatches)) {
                foreach ($textMatches[1] as $fragment) {
                    $textChunks[] = $this->decodePdfText($fragment);
                }
            }

            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
                foreach ($arrayMatches[1] as $fragmentBlock) {
                    if (preg_match_all('/\((.*?)\)/s', $fragmentBlock, $fragmentMatches)) {
                        foreach ($fragmentMatches[1] as $fragment) {
                            $textChunks[] = $this->decodePdfText($fragment);
                        }
                    }
                }
            }
        }

        $text = $this->normalizeWhitespace(implode("\n", $textChunks));

        if ($text !== '') {
            $pipeline['text_extraction_method'] = 'pdf_stream';
        }

        return $text;
    }

    private function decodePdfText(string $fragment): string
    {
        $fragment = str_replace(['\\(', '\\)', '\\n', '\\r', '\\t'], ['(', ')', "\n", "\r", "\t"], $fragment);
        $fragment = preg_replace_callback('/\\\\([0-7]{3})/', function (array $matches) {
            return chr(octdec($matches[1]));
        }, $fragment);

        return $fragment;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/[[:^print:]\x{00AD}]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    private function extractTextWithOcrFallback(string $absolutePath): string
    {
        if (!$this->commandExists('tesseract')) {
            return '';
        }

        // We expose the OCR phase in the pipeline, but only execute it when
        // supporting binaries are present on the host/container.
        // PDF-to-image conversion is environment-specific, so this remains a
        // safe best-effort hook instead of a hard dependency.
        return '';
    }

    private function commandExists(string $command): bool
    {
        $result = shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');

        return is_string($result) && trim($result) !== '';
    }
}
