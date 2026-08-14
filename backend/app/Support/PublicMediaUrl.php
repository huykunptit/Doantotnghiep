<?php

namespace App\Support;

class PublicMediaUrl
{
    /**
     * Browser-reachable URL that works behind nginx, Cloudflare, and localhost.
     * Never returns an internal Docker hostname or APP_URL host.
     */
    public static function toPublic(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        if (preg_match('#^https?://#i', $path)) {
            $parts = parse_url($path);
            if ($parts === false) {
                return $path;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));
            $urlPath = (string) ($parts['path'] ?? '');
            $port = isset($parts['port']) ? (int) $parts['port'] : null;
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

            $internalHosts = [
                'localhost',
                '127.0.0.1',
                'backend',
                'nginx',
                'minio',
                'host.docker.internal',
            ];

            $isMinio = $host === 'minio'
                || str_contains($host, 'minio')
                || $port === 9000
                || $port === 9001;

            if ($isMinio) {
                return '/minio/'.ltrim($urlPath, '/');
            }

            $rewriteHost = in_array($host, $internalHosts, true)
                || ($appHost !== '' && $host === $appHost);

            if ($rewriteHost && $urlPath !== '') {
                return $urlPath;
            }

            return $path;
        }

        if (
            str_starts_with($path, '/storage/')
            || str_starts_with($path, '/minio/')
            || str_starts_with($path, '/uploads/')
            || str_starts_with($path, '/images/')
        ) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public static function toStorageKey(?string $path): ?string
    {
        $public = self::toPublic($path);
        if (! $public) {
            return null;
        }

        foreach (['/storage/', '/uploads/'] as $prefix) {
            if (str_starts_with($public, $prefix)) {
                return substr($public, strlen($prefix));
            }
        }

        if (str_starts_with($public, '/')) {
            return null;
        }

        return ltrim($public, '/');
    }
}
