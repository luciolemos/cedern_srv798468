<?php

declare(strict_types=1);

namespace App\Application\Support;

use PHPMailer\PHPMailer\PHPMailer;

final class SmtpSettings
{
    public static function resolveConfiguredEncryption(int $smtpPort): string
    {
        $explicitEncryption = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? '')));

        return match ($explicitEncryption) {
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            'none', 'off', 'disabled' => '',
            default => $smtpPort === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS,
        };
    }
}
