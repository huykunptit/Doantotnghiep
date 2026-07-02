<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\ScormPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ScormPackageController extends Controller
{
    // MinIO disk name — matches config/filesystems.php
    private const DISK = 'minio';

    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $package = $lesson->scormPackage;
        if (!$package) {
            return response()->json(['message' => 'No package found'], 404);
        }

        $data = $package->toArray();
        $data['entry_url'] = $this->resolvePublicUrl($data['entry_url'] ?? null);

        return response()->json($data);
    }

    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'entry_url'  => ['nullable', 'url', 'max:2048'],
            'title'      => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'version'    => ['nullable', 'string', 'max:50'],
            'type'       => ['required', 'string', 'in:scorm,h5p'],
            'scorm_file' => ['nullable', 'file', 'mimes:zip', 'max:204800'], // 200 MB
        ]);

        if ($validated['type'] === 'scorm' && !$request->hasFile('scorm_file')) {
            return response()->json(['message' => 'SCORM package file is required'], 422);
        }

        if ($validated['type'] === 'h5p' && empty($validated['entry_url'])) {
            return response()->json(['message' => 'H5P embed URL is required'], 422);
        }

        $uuid = $lesson->scormPackage?->uuid ?? (string) Str::uuid();
        $entryUrl = $validated['entry_url'] ?? null;
        $detectedVersion = null;
        $detectedTitle = null;

        if ($validated['type'] === 'scorm' && $request->file('scorm_file') instanceof UploadedFile) {
            // Delete old MinIO objects before uploading new package
            if ($lesson->scormPackage?->uuid) {
                Storage::disk(self::DISK)->deleteDirectory('scorm/' . $lesson->scormPackage->uuid);
            }

            [$entryUrl, $detectedVersion, $detectedTitle] = $this->extractAndUpload(
                $request->file('scorm_file'),
                $uuid
            );
        }

        $version = $validated['version']
            ?? $detectedVersion
            ?? (($validated['type'] ?? 'scorm') === 'h5p' ? 'h5p' : '1.2');

        $package = ScormPackage::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'uuid'       => $uuid,
                'version'    => $version,
                'entry_url'  => $entryUrl,
                'identifier' => $validated['identifier'] ?? null,
                'title'      => $validated['title'] ?? $detectedTitle ?? $lesson->title,
            ]
        );

        $lesson->update(['type' => $validated['type'] ?? 'scorm']);

        $responseData = $package->toArray();
        $responseData['entry_url'] = $this->resolvePublicUrl($responseData['entry_url'] ?? null);

        return response()->json([
            'message'       => 'SCORM/H5P package saved successfully',
            'scorm_package' => $responseData,
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $package = $lesson->scormPackage;
        if ($package?->uuid) {
            // Remove from MinIO
            Storage::disk(self::DISK)->deleteDirectory('scorm/' . $package->uuid);
            // Also clean up any legacy local files
            $localDir = storage_path('app/public/scorm/' . $package->uuid);
            if (File::exists($localDir)) {
                File::deleteDirectory($localDir);
            }
        }

        $lesson->scormPackage()->delete();

        return response()->json(['message' => 'SCORM/H5P package removed']);
    }

    /**
     * Unzip the SCORM package into a temp directory, upload every file to MinIO,
     * then return [entryUrl (MinIO key), detectedVersion, detectedTitle].
     *
     * @return array{0:string, 1:?string, 2:?string}
     */
    private function extractAndUpload(UploadedFile $file, string $uuid): array
    {
        $tmpDir = sys_get_temp_dir() . '/scorm_' . $uuid;

        try {
            File::ensureDirectoryExists($tmpDir);

            $zipPath = $tmpDir . '/package.zip';
            $file->move($tmpDir, 'package.zip');

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Cannot open SCORM zip file');
            }
            $zip->extractTo($tmpDir);
            $zip->close();
            File::delete($zipPath);

            // Parse manifest before uploading
            $entryFile    = $this->detectScormEntry($tmpDir);
            $version      = $this->detectScormVersion($tmpDir);
            $title        = $this->detectScormTitle($tmpDir);

            // Upload every extracted file to MinIO under scorm/<uuid>/
            $prefix = 'scorm/' . $uuid;
            $allFiles = File::allFiles($tmpDir);
            foreach ($allFiles as $localFile) {
                $relativePath = ltrim(
                    str_replace('\\', '/', substr($localFile->getPathname(), strlen($tmpDir))),
                    '/'
                );
                Storage::disk(self::DISK)->put(
                    $prefix . '/' . $relativePath,
                    file_get_contents($localFile->getPathname()),
                    'public'
                );
            }

            // The entry URL we store is the MinIO object key (not a full URL).
            // It gets resolved to a public-accessible URL in resolvePublicUrl().
            $entryKey = $prefix . '/' . $entryFile;

            return [$entryKey, $version, $title];
        } finally {
            // Always clean up tmp directory
            if (File::exists($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    /**
     * Convert a stored key/path to a browser-accessible URL.
     * Keys starting with "scorm/" are proxied through nginx at /minio/<bucket>/<key>.
     * Full HTTP URLs are returned as-is.
     */
    private function resolvePublicUrl(?string $keyOrUrl): string
    {
        if (!$keyOrUrl) return '';
        // Already a full URL or already a /minio/ proxy path — return as-is.
        if (str_starts_with($keyOrUrl, 'http') || str_starts_with($keyOrUrl, '/minio/')) {
            return $keyOrUrl;
        }

        $bucket = config('filesystems.disks.' . self::DISK . '.bucket', 'lms-videos');

        // Nginx proxies /minio/ → http://minio:9000/
        return '/minio/' . $bucket . '/' . ltrim($keyOrUrl, '/');
    }

    private function detectScormEntry(string $directory): string
    {
        $manifestPath = $directory . '/imsmanifest.xml';

        if (File::exists($manifestPath)) {
            $manifest = @simplexml_load_file($manifestPath);
            if ($manifest) {
                $resources = $manifest->resources?->resource;
                if ($resources) {
                    foreach ($resources as $resource) {
                        $href = (string) $resource['href'];
                        if ($href !== '') {
                            return ltrim($href, '/');
                        }
                    }
                }
            }
        }

        foreach (['index_lms.html', 'index.html', 'story.html', 'launch.html'] as $candidate) {
            if (File::exists($directory . '/' . $candidate)) {
                return $candidate;
            }
        }

        $htmlFile = collect(File::allFiles($directory))
            ->first(fn ($f) => in_array(strtolower($f->getExtension()), ['html', 'htm']));

        if ($htmlFile) {
            return ltrim(
                str_replace('\\', '/', substr($htmlFile->getPathname(), strlen($directory))),
                '/'
            );
        }

        throw new \RuntimeException('Cannot detect SCORM entry file');
    }

    private function detectScormVersion(string $directory): ?string
    {
        $manifestPath = $directory . '/imsmanifest.xml';
        if (!File::exists($manifestPath)) return null;

        $raw = @file_get_contents($manifestPath);
        if ($raw === false) return null;

        if (str_contains($raw, 'adlcp_rootv1p2')) return '1.2';
        if (preg_match('/adl(cp|seq|nav)_v1p3/', $raw)) return '2004';

        $manifest = @simplexml_load_string($raw);
        if ($manifest) {
            $sv = (string) ($manifest->metadata->schemaversion ?? '');
            if ($sv !== '') {
                if (str_starts_with($sv, '1.2')) return '1.2';
                if (str_contains($sv, '2004') || str_contains($sv, 'CAM 1.3')) return '2004';
            }
        }

        return null;
    }

    private function detectScormTitle(string $directory): ?string
    {
        $manifestPath = $directory . '/imsmanifest.xml';
        if (!File::exists($manifestPath)) return null;

        $manifest = @simplexml_load_file($manifestPath);
        if (!$manifest) return null;

        $titles = $manifest->xpath('//*[local-name()="title"]');
        foreach ($titles as $t) {
            $text = trim((string) $t);
            if ($text !== '') return $text;
        }

        return null;
    }
}
