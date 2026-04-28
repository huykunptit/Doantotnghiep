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
    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $package = $lesson->scormPackage;
        if (!$package) {
            return response()->json(['message' => 'No package found'], 404);
        }

        return response()->json($package);
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
            'entry_url' => ['nullable', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:scorm,h5p'],
            'scorm_file' => ['nullable', 'file', 'mimes:zip', 'max:51200'],
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

        if ($validated['type'] === 'scorm' && $request->file('scorm_file') instanceof UploadedFile) {
            [$entryUrl, $detectedVersion] = $this->extractScormPackage($request->file('scorm_file'), $uuid);
        }

        $version = $validated['version']
            ?? $detectedVersion
            ?? (($validated['type'] ?? 'scorm') === 'h5p' ? 'h5p' : '1.2');

        $package = ScormPackage::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'uuid' => $uuid,
                'version' => $version,
                'entry_url' => $entryUrl,
                'identifier' => $validated['identifier'] ?? null,
                'title' => $validated['title'] ?? $lesson->title,
            ]
        );

        $lesson->update([
            'type' => $validated['type'] ?? 'scorm',
        ]);

        return response()->json([
            'message' => 'SCORM/H5P package saved successfully',
            'scorm_package' => $package,
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
            Storage::disk('public')->deleteDirectory('scorm/' . $package->uuid);
        }

        $lesson->scormPackage()->delete();

        return response()->json(['message' => 'SCORM/H5P package removed']);
    }

    /**
     * Extract a SCORM package zip and return [entryUrl, detectedVersion].
     * detectedVersion is '1.2' or '2004' (or null if it couldn't be inferred).
     *
     * @return array{0:string,1:?string}
     */
    private function extractScormPackage(UploadedFile $file, string $uuid): array
    {
        $disk = Storage::disk('public');
        $directory = 'scorm/' . $uuid;
        $absoluteDirectory = $disk->path($directory);

        if (File::exists($absoluteDirectory)) {
            File::deleteDirectory($absoluteDirectory);
        }

        File::ensureDirectoryExists($absoluteDirectory);

        $archivePath = $absoluteDirectory . '/package.zip';
        $file->move($absoluteDirectory, 'package.zip');

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Cannot open SCORM package');
        }

        $zip->extractTo($absoluteDirectory);
        $zip->close();

        File::delete($archivePath);

        $entryRelativePath = $this->detectScormEntry($absoluteDirectory);
        $version = $this->detectScormVersion($absoluteDirectory);

        return [
            $disk->url($directory . '/' . $entryRelativePath),
            $version,
        ];
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

        $candidateFiles = [
            'index_lms.html',
            'index.html',
            'story.html',
            'launch.html',
        ];

        foreach ($candidateFiles as $candidate) {
            if (File::exists($directory . '/' . $candidate)) {
                return $candidate;
            }
        }

        $htmlFile = collect(File::allFiles($directory))
            ->first(fn ($file) => in_array(strtolower($file->getExtension()), ['html', 'htm']));

        if ($htmlFile) {
            return ltrim(str_replace($directory, '', $htmlFile->getPathname()), DIRECTORY_SEPARATOR);
        }

        throw new \RuntimeException('Cannot detect SCORM entry file');
    }

    /**
     * Detect SCORM spec version by inspecting imsmanifest.xml. We look at
     *   <metadata><schema>ADL SCORM</schema><schemaversion>...</schemaversion>
     * and at the manifest's xmlns attributes (`adlcp_rootv1p2` ⇒ 1.2,
     * `adlcp_v1p3` / `adlseq_v1p3` / `adlnav_v1p3` ⇒ 2004).
     */
    private function detectScormVersion(string $directory): ?string
    {
        $manifestPath = $directory . '/imsmanifest.xml';
        if (!File::exists($manifestPath)) return null;

        $raw = @file_get_contents($manifestPath);
        if ($raw === false) return null;

        // Cheapest signal: look at the manifest's xmlns declarations.
        if (str_contains($raw, 'adlcp_rootv1p2')) {
            return '1.2';
        }
        if (preg_match('/adl(cp|seq|nav)_v1p3/', $raw)) {
            return '2004';
        }

        // Fall back to the metadata/schemaversion element.
        $manifest = @simplexml_load_string($raw);
        if ($manifest) {
            $schemaVersion = (string) ($manifest->metadata->schemaversion ?? '');
            if ($schemaVersion !== '') {
                if (str_starts_with($schemaVersion, '1.2')) return '1.2';
                if (str_contains($schemaVersion, '2004') || str_contains($schemaVersion, 'CAM 1.3')) return '2004';
            }
        }

        return null;
    }
}
