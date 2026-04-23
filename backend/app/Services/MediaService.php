<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * Get the configured disk. Defaults to 'public' for local storage
     * or 's3'/'minio' if specified in env.
     */
    public function getDisk(): string
    {
        $preferredDisk = env('FILESYSTEM_DISK', 'public');

        return match ($preferredDisk) {
            's3' => class_exists(\League\Flysystem\AwsS3V3\PortableVisibilityConverter::class) ? 's3' : 'public',
            default => $preferredDisk,
        };
    }

    /**
     * Upload a file to the configured disk.
     * 
     * @param UploadedFile $file
     * @param string $folder Relative path (e.g. "courses/1/lessons/5")
     * @param string $visibility 'public' or 'private'
     * @return array Metadata about the uploaded file
     */
    public function upload(UploadedFile $file, string $folder, string $visibility = 'public'): array
    {
        $disk = $this->getDisk();
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        
        $path = Storage::disk($disk)->putFileAs(
            $folder,
            $file,
            $filename,
            $visibility
        );

        if (!$path) {
            throw new \Exception("Failed to upload file to disk: {$disk}");
        }

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => round($file->getSize() / 1024 / 1024, 2), // MB
            'mime' => $file->getMimeType(),
            'disk' => $disk
        ];
    }

    /**
     * Get a accessible URL for the file.
     */
    public function getUrl(string $path): string
    {
        $disk = $this->getDisk();

        // For local public files
        if ($disk === 'public') {
            return Storage::disk($disk)->url($path);
        }

        // For cloud storage, usually need a temporary URL for security
        try {
            return Storage::disk($disk)->temporaryUrl(
                $path,
                now()->addMinutes(60)
            );
        } catch (\Throwable $e) {
            // Fallback for disks that don't support temporary URLs
            return Storage::disk($disk)->url($path);
        }
    }

    /**
     * Delete a file from storage.
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->getDisk())->delete($path);
    }

    /**
     * Check if file exists.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->getDisk())->exists($path);
    }
}
