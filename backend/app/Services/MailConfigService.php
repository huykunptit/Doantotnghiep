<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Config;

class MailConfigService
{
    /**
     * Apply SMTP from site_settings (admin UI) when host is configured.
     * Falls back to .env MAIL_* otherwise.
     */
    public static function applyFromSiteSettings(): bool
    {
        $host = trim((string) SiteSetting::get('smtp_host'));
        if ($host === '') {
            return false;
        }

        $port = (int) (SiteSetting::get('smtp_port') ?: 587);
        $username = SiteSetting::get('smtp_username');
        $password = SiteSetting::get('smtp_password');
        $encryption = SiteSetting::get('smtp_encryption', 'tls');
        $fromAddress = SiteSetting::get('smtp_from_address') ?: config('mail.from.address');
        $fromName = SiteSetting::get('smtp_from_name') ?: config('mail.from.name');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.timeout', 15);

        if ($encryption === 'none' || $encryption === null || $encryption === '') {
            Config::set('mail.mailers.smtp.scheme', null);
            Config::set('mail.mailers.smtp.encryption', null);
        } elseif ($encryption === 'ssl') {
            Config::set('mail.mailers.smtp.scheme', 'smtps');
            Config::set('mail.mailers.smtp.encryption', 'ssl');
        } else {
            Config::set('mail.mailers.smtp.scheme', null);
            Config::set('mail.mailers.smtp.encryption', 'tls');
        }

        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);

        return true;
    }

    public static function isSmtpConfigured(): bool
    {
        return trim((string) SiteSetting::get('smtp_host')) !== '';
    }
}
