<?php

namespace App\Services;

use App\Support\PublicMediaUrl;
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
        $alreadyPublic = PublicMediaUrl::toPublic($path);
        if ($alreadyPublic && (
            str_starts_with($alreadyPublic, 'http://')
            || str_starts_with($alreadyPublic, 'https://')
            || str_starts_with($alreadyPublic, '/storage/')
            || str_starts_with($alreadyPublic, '/uploads/')
            || str_starts_with($alreadyPublic, '/minio/')
            || str_starts_with($alreadyPublic, '/images/')
        )) {
            return $alreadyPublic;
        }

        $key = PublicMediaUrl::toStorageKey($path) ?? ltrim($path, '/');

        if (Storage::disk('public_uploads')->exists($key) || Storage::disk('public_uploads')->exists($path)) {
            $raw = Storage::disk('public_uploads')->exists($key) ? $key : $path;

            return PublicMediaUrl::toPublic('/uploads/'.ltrim($raw, '/')) ?? '/uploads/'.ltrim($raw, '/');
        }

        $disk = $this->getDisk();

        if ($disk === 'minio') {
            $bucket = (string) config('filesystems.disks.minio.bucket', 'lms-videos');

            return '/minio/'.$bucket.'/'.ltrim($key, '/');
        }

        if ($disk === 'public' || $disk === 'local') {
            return PublicMediaUrl::toPublic('/storage/'.ltrim($key, '/')) ?? '/storage/'.ltrim($key, '/');
        }

        try {
            return PublicMediaUrl::toPublic(Storage::disk($disk)->temporaryUrl($key, now()->addMinutes(60)))
                ?? Storage::disk($disk)->url($key);
        } catch (\Throwable $e) {
            return PublicMediaUrl::toPublic(Storage::disk($disk)->url($key))
                ?? '/storage/'.ltrim($key, '/');
        }
    }

    /**
     * Check if file exists.
     */
    public function exists(string $path): bool
    {
        $key = PublicMediaUrl::toStorageKey($path) ?? ltrim($path, '/');

        return Storage::disk($this->getDisk())->exists($key)
            || Storage::disk($this->getDisk())->exists($path);
    }

    public function delete(string $path): bool
    {
        $key = PublicMediaUrl::toStorageKey($path) ?? ltrim($path, '/');
        $disk = $this->getDisk();

        if (Storage::disk($disk)->exists($key)) {
            return Storage::disk($disk)->delete($key);
        }

        return Storage::disk($disk)->delete($path);
    }
}
