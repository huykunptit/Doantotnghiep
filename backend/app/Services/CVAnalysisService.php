<?php

namespace App\Services;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            'parse_ok' => false,
        ];

        $text = match ($extension) {
            'pdf' => $this->extractTextFromPdf($absolutePath, $pipeline),
            'docx' => $this->extractTextFromDocx($absolutePath, $pipeline),
            'doc' => '',
            default => '',
        };

        $text = $this->normalizeWhitespace($text);

        // Local regex PDF thường ra binary/garbage với TopCV → gọi ai-service (pdfminer + OCR)
        if (!$this->isMeaningfulText($text)) {
            $remote = $this->extractViaAiService($absolutePath, $originalFilename, $pipeline);
            if ($this->isMeaningfulText($remote['text'] ?? '')) {
                $text = $this->normalizeWhitespace((string) $remote['text']);
                $skills = $this->extractSkills($text);
                if (!empty($remote['skills']) && is_array($remote['skills'])) {
                    $skills = array_values(array_unique(array_merge(
                        $skills,
                        array_filter($remote['skills'])
                    )));
                }
                $profile = $this->buildProfileHints($text);
                $pipeline['parse_ok'] = true;

                return [
                    'text' => $text,
                    'skills' => $skills,
                    'profile' => $profile,
                    'pipeline' => $pipeline,
                ];
            }
            $text = '';
        }

        // Loại text rác từ parser PHP cũ
        if (!$this->isMeaningfulText($text)) {
            $text = '';
        }

        $skills = $this->extractSkills($text);
        $profile = $this->buildProfileHints($text);
        $pipeline['parse_ok'] = mb_strlen($text) >= 40 || count($skills) > 0;

        return [
            'text' => $text,
            'skills' => $skills,
            'profile' => $profile,
            'pipeline' => $pipeline,
        ];
    }

    /**
     * @return array{email:?string,phone:?string,summary:?string,has_education:bool,has_projects:bool,has_experience:bool}
     */
    public function buildProfileHints(string $text): array
    {
        $lower = mb_strtolower($text);
        $email = null;
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m)) {
            $email = $m[0];
        }

        $phone = null;
        if (preg_match('/(?:\+?84|0)(?:[\s.\-]?\d){8,11}/', $text, $m)) {
            $phone = preg_replace('/\s+/', ' ', trim($m[0]));
        }

        $hasEducation = (bool) preg_match(
            '/học vấn|education|university|đại học|cao đẳng|ptit|học viện|bachelor|cử nhân|khoa\s/iu',
            $lower
        );
        $hasProjects = (bool) preg_match(
            '/dự án|project|portfolio|đồ án|capstone|github\.com/iu',
            $lower
        );
        $hasExperience = (bool) preg_match(
            '/kinh nghiệm|experience|internship|thực tập|fresher|junior|làm việc tại/iu',
            $lower
        );

        $summary = null;
        if (mb_strlen($text) > 120) {
            $summary = mb_substr($text, 0, 280);
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'summary' => $summary,
            'has_education' => $hasEducation,
            'has_projects' => $hasProjects,
            'has_experience' => $hasExperience,
        ];
    }

    public function extractSkills(string $text): array
    {
        $normalized = ' ' . mb_strtolower($text) . ' ';

        $dictionary = [
            'PHP' => ['php'],
            'Laravel' => ['laravel'],
            'Symfony' => ['symfony'],
            'MySQL' => ['mysql'],
            'PostgreSQL' => ['postgresql', 'postgres'],
            'MongoDB' => ['mongodb', 'mongo'],
            'Redis' => ['redis'],
            'REST API' => ['rest api', 'restful', 'api'],
            'JavaScript' => ['javascript', 'js'],
            'TypeScript' => ['typescript', 'ts'],
            'Vue.js' => ['vue.js', 'vuejs', 'vue'],
            'Nuxt' => ['nuxt'],
            'React' => ['react', 'reactjs'],
            'Next.js' => ['next.js', 'nextjs'],
            'Node.js' => ['node.js', 'nodejs', 'node'],
            'HTML/CSS' => ['html', 'css', 'tailwind', 'bootstrap'],
            'Docker' => ['docker'],
            'Git' => ['git', 'github', 'gitlab'],
            'Linux' => ['linux', 'ubuntu'],
            'Java' => ['java', 'spring boot', 'spring'],
            'Python' => ['python', 'django', 'flask', 'fastapi'],
            'C/C++' => ['c++', 'c/c++'],
            'Flutter' => ['flutter', 'dart'],
            'AWS' => ['aws', 'amazon web services'],
            'Azure' => ['azure'],
            'Testing' => ['phpunit', 'unit test', 'testing', 'kiểm thử', 'jest', 'cypress'],
            'Agile/Scrum' => ['agile', 'scrum'],
            'Excel' => ['excel', 'google sheet'],
            'Power BI' => ['power bi', 'powerbi'],
            'SQL' => [' sql ', 'sql server', 'truy vấn sql'],
            'Machine Learning' => ['machine learning', 'ml ', 'deep learning'],
            'Data Analysis' => ['data analyst', 'phân tích dữ liệu', 'data analysis'],
            'Communication' => ['giao tiếp', 'communication', 'teamwork', 'làm việc nhóm'],
        ];

        $skills = [];
        foreach ($dictionary as $label => $keywords) {
            foreach ($keywords as $keyword) {
                $needle = mb_strtolower($keyword);
                if (str_contains($normalized, $needle)) {
                    $skills[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($skills));
    }

    /**
     * @return array{text:string,skills:array,method:?string}
     */
    private function extractViaAiService(string $absolutePath, string $originalFilename, array &$pipeline): array
    {
        if (!is_readable($absolutePath)) {
            return ['text' => '', 'skills' => [], 'method' => null];
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return ['text' => '', 'skills' => [], 'method' => null];
        }

        $aiSettings = AiSetting::current();
        $url = rtrim((string) config('services.ai_service.url'), '/') . '/parse-cv';

        // Ưu tiên gemini/claude cho OCR PDF scan
        $provider = null;
        $apiKey = null;
        $model = null;
        foreach (['gemini', 'claude', 'chatgpt', 'openrouter'] as $candidate) {
            $key = $aiSettings->resolveApiKey($candidate);
            if ($key) {
                $provider = $candidate;
                $apiKey = $key;
                $model = $aiSettings->resolveModel($candidate);
                break;
            }
        }

        $sharedSecret = (string) config('services.ai_service.shared_secret');

        try {
            $response = Http::connectTimeout(5)
                ->timeout(90)
                ->withHeaders($sharedSecret !== '' ? ['X-Internal-Token' => $sharedSecret] : [])
                ->post($url, [
                    'file_base64' => base64_encode($bytes),
                    'file_name' => $originalFilename,
                    'user_id' => 0,
                    'provider' => $provider,
                    'model' => $model,
                    'api_key' => $apiKey,
                ]);

            if (!$response->successful()) {
                Log::warning('AI parse-cv HTTP ' . $response->status() . ': ' . $response->body());
                return ['text' => '', 'skills' => [], 'method' => null];
            }

            $data = $response->json() ?: [];
            $pipeline['text_extraction_method'] = (string) ($data['method'] ?? 'ai_service');
            $pipeline['ocr_attempted'] = (bool) $apiKey;
            $pipeline['ocr_used'] = (bool) ($data['ocr_used'] ?? false);

            return [
                'text' => (string) ($data['text'] ?? ''),
                'skills' => is_array($data['skills'] ?? null) ? $data['skills'] : [],
                'method' => $data['method'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('AI parse-cv failed: ' . $e->getMessage());

            return ['text' => '', 'skills' => [], 'method' => null];
        }
    }

    private function isMeaningfulText(string $text): bool
    {
        $text = trim($text);
        if (mb_strlen($text) < 40) {
            return false;
        }

        // Đếm chữ cái Latin + tiếng Việt + số
        if (!preg_match_all('/[A-Za-zÀ-ỹ0-9]/u', $text, $m)) {
            return false;
        }

        $letters = count($m[0]);
        // Text rác binary thường có rất ít ký tự chữ so với độ dài
        return $letters >= 40 && ($letters / max(mb_strlen($text), 1)) >= 0.35;
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

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractTextFromPdf(string $absolutePath, array &$pipeline): string
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false || $content === '') {
            return '';
        }

        $chunks = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)\s*Tj/s', $content, $m)) {
            foreach ($m[0] as $frag) {
                if (preg_match('/\((.*)\)\s*Tj/s', $frag, $inner)) {
                    $chunks[] = $this->decodePdfText($inner[1]);
                }
            }
        }

        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = $this->decodePdfStream($stream);
                if ($decoded === '') {
                    continue;
                }

                if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $textMatches)) {
                    foreach ($textMatches[1] as $fragment) {
                        $chunks[] = $this->decodePdfText($fragment);
                    }
                }
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
                    foreach ($arrayMatches[1] as $fragmentBlock) {
                        if (preg_match_all('/\((.*?)\)/s', $fragmentBlock, $fragmentMatches)) {
                            foreach ($fragmentMatches[1] as $fragment) {
                                $chunks[] = $this->decodePdfText($fragment);
                            }
                        }
                    }
                }
                if (preg_match_all('/BT(.*?)ET/s', $decoded, $btBlocks)) {
                    foreach ($btBlocks[1] as $block) {
                        if (preg_match_all('/\((.*?)\)/s', $block, $btText)) {
                            foreach ($btText[1] as $fragment) {
                                $chunks[] = $this->decodePdfText($fragment);
                            }
                        }
                    }
                }
            }
        }

        $text = implode("\n", array_filter($chunks));
        if ($text !== '') {
            $pipeline['text_extraction_method'] = 'pdf_stream';
        }

        return $text;
    }

    private function decodePdfStream(string $stream): string
    {
        $stream = ltrim($stream, "\r\n");
        $decoded = @gzuncompress($stream);
        if ($decoded === false) {
            $decoded = @gzinflate($stream);
        }
        if ($decoded === false && function_exists('gzdecode')) {
            $decoded = @gzdecode($stream);
        }
        if ($decoded === false) {
            $decoded = $stream;
        }

        return is_string($decoded) ? $decoded : '';
    }

    private function decodePdfText(string $fragment): string
    {
        $fragment = str_replace(['\\(', '\\)', '\\n', '\\r', '\\t'], ['(', ')', "\n", "\r", "\t"], $fragment);
        $fragment = preg_replace_callback('/\\\\([0-7]{3})/', function (array $matches) {
            return chr(octdec($matches[1]));
        }, $fragment) ?? $fragment;

        return $fragment;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/[[:^print:]\x{00AD}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
