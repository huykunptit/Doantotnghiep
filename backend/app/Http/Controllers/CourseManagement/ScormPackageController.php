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

        if ($validated['type'] === 'scorm' && $request->file('scorm_file') instanceof UploadedFile) {
            $entryUrl = $this->extractScormPackage($request->file('scorm_file'), $uuid);
        }

        $package = ScormPackage::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'uuid' => $uuid,
                'version' => $validated['version'] ?? (($validated['type'] ?? 'scorm') === 'h5p' ? 'h5p' : '1.2'),
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

    private function extractScormPackage(UploadedFile $file, string $uuid): string
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

        return $disk->url($directory . '/' . $entryRelativePath);
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
}
