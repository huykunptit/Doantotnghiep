<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class SetupMinIO extends Command
{
    protected $signature = 'minio:setup';
    protected $description = 'Setup MinIO bucket for video storage';

    public function handle()
    {
        $this->info('Setting up MinIO...');

        try {
            $client = new S3Client([
                'credentials' => [
                    'key' => config('filesystems.disks.minio.key'),
                    'secret' => config('filesystems.disks.minio.secret'),
                ],
                'endpoint' => config('filesystems.disks.minio.endpoint'),
                'region' => config('filesystems.disks.minio.region'),
                'version' => 'latest',
                'use_path_style_endpoint' => true,
            ]);

            $bucket = config('filesystems.disks.minio.bucket');

            // Check if bucket exists
            if ($client->doesBucketExist($bucket)) {
                $this->info("✅ Bucket '{$bucket}' already exists.");
            } else {
                $this->info("Creating bucket '{$bucket}'...");
                $client->createBucket(['Bucket' => $bucket]);
                $this->info("✅ Bucket '{$bucket}' created successfully!");
            }

            // Test upload
            $this->info("\nTesting upload...");
            $testFile = 'test.txt';
            Storage::disk('minio')->put($testFile, 'MinIO is working!');
            
            if (Storage::disk('minio')->exists($testFile)) {
                $this->info("✅ Test file uploaded successfully!");
                
                // Test presigned URL
                $url = Storage::disk('minio')->temporaryUrl($testFile, now()->addMinutes(5));
                $this->info("✅ Presigned URL generated:");
                $this->line($url);
                
                // Cleanup
                Storage::disk('minio')->delete($testFile);
                $this->info("✅ Test file deleted.");
            }

            $this->newLine();
            $this->info('🎉 MinIO setup completed successfully!');
            $this->info("You can now upload videos to bucket '{$bucket}'");

        } catch (AwsException $e) {
            $this->error('❌ MinIO setup failed:');
            $this->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error:');
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}

