<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishSeedAvatars extends Command
{
    protected $signature = 'avatars:publish';

    protected $description = 'Copy demo avatar JPGs into public storage so /storage/seed/avatars works after deploy';

    public function handle(): int
    {
        $source = database_path('seeders/assets/avatars');
        $target = storage_path('app/public/seed/avatars');

        if (! File::isDirectory($source)) {
            $this->warn("Avatar source missing: {$source}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($target);

        $copied = 0;
        foreach (File::files($source) as $file) {
            if (strtolower($file->getExtension()) !== 'jpg') {
                continue;
            }
            File::copy($file->getPathname(), $target.DIRECTORY_SEPARATOR.$file->getFilename());
            $copied++;
        }

        $this->info("Published {$copied} avatar(s) to {$target}");

        return self::SUCCESS;
    }
}
