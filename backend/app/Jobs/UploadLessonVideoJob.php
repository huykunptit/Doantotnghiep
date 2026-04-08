<?php

namespace App\Jobs;

use App\Models\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadLessonVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        private readonly int $lessonId,
        private readonly string $tempPath,
        private readonly string $storagePath,
        private readonly string $mimeType,
    ) {}

    public function handle(): void
    {
        $lesson = Lesson::find($this->lessonId);

        if (!$lesson) {
            Log::warning("UploadLessonVideoJob: lesson {$this->lessonId} not found");
            return;
        }

        if (!file_exists($this->tempPath)) {
            Log::warning("UploadLessonVideoJob: temp file not found at {$this->tempPath}");
            return;
        }

        // Delete old video if exists
        if ($lesson->video_url) {
            try {
                Storage::disk('s3')->delete($lesson->video_url);
            } catch (\Throwable $e) {
                Log::warning("UploadLessonVideoJob: could not delete old video: {$e->getMessage()}");
            }
        }

        $stream = fopen($this->tempPath, 'r');

        Storage::disk('s3')->put($this->storagePath, $stream, [
            'ContentType' => $this->mimeType,
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        // Clean up temp file
        @unlink($this->tempPath);

        $lesson->video_url = $this->storagePath;
        $lesson->save();

        Log::info("UploadLessonVideoJob: lesson {$this->lessonId} video uploaded to {$this->storagePath}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("UploadLessonVideoJob failed for lesson {$this->lessonId}: {$exception->getMessage()}");
    }
}
