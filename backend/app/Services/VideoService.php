<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class VideoService
{
    protected $disk = 'minio';
    protected $bucket = 'course-videos';

    /**
     * Upload video to MinIO
     *
     * @param UploadedFile $file
     * @param string $path Custom path (e.g., "courses/123/lessons/456")
     * @return array ['path' => string, 'size' => int, 'duration' => int]
     */
    public function uploadVideo(UploadedFile $file, string $path = ''): array
    {
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file);
        $fullPath = $path ? "$path/$filename" : $filename;

        // Upload to MinIO
        $uploaded = Storage::disk($this->disk)->putFileAs(
            $path,
            $file,
            $filename,
            'private' // Private visibility
        );

        if (!$uploaded) {
            throw new \Exception('Failed to upload video to MinIO');
        }

        // Get file size in MB
        $sizeInMB = round($file->getSize() / 1024 / 1024, 2);

        // Get video duration (requires FFmpeg - optional for now)
        $duration = $this->getVideoDuration($file);

        return [
            'path' => $uploaded,
            'size' => $sizeInMB,
            'duration' => $duration,
        ];
    }

    /**
     * Generate presigned URL for video streaming
     *
     * @param string $path MinIO object path
     * @param int $expiryMinutes Default 15 minutes
     * @return string Presigned URL
     */
    public function generatePresignedUrl(string $path, int $expiryMinutes = 15): string
    {
        try {
            $url = Storage::disk($this->disk)->temporaryUrl(
                $path,
                now()->addMinutes($expiryMinutes)
            );

            return $url;
        } catch (\Exception $e) {
            throw new \Exception('Failed to generate presigned URL: ' . $e->getMessage());
        }
    }

    /**
     * Delete video from MinIO
     *
     * @param string $path
     * @return bool
     */
    public function deleteVideo(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Check if video exists
     *
     * @param string $path
     * @return bool
     */
    public function videoExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Generate unique filename
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $random = bin2hex(random_bytes(8));

        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get video duration using FFmpeg (optional - returns 0 if not available)
     *
     * @param UploadedFile $file
     * @return int Duration in seconds
     */
    protected function getVideoDuration(UploadedFile $file): int
    {
        // TODO: Implement FFmpeg integration if needed
        // For now, return 0 (duration can be updated later via queue job)
        return 0;
    }

    /**
     * Get video metadata
     *
     * @param string $path
     * @return array
     */
    public function getVideoMetadata(string $path): array
    {
        $disk = Storage::disk($this->disk);

        return [
            'exists' => $disk->exists($path),
            'size' => $disk->exists($path) ? $disk->size($path) : 0,
            'last_modified' => $disk->exists($path) ? $disk->lastModified($path) : null,
        ];
    }
}

