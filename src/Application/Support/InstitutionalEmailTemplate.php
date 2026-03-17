<?php

declare(strict_types=1);

namespace App\Application\Support;

final class InstitutionalEmailTemplate
{
    public static function buildLayout(string $title, string $contentHtml, ?string $logoSrc = null): string
    {
        $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE'), ENT_QUOTES, 'UTF-8');
        $baseUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');
        $siteUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        $resolvedLogoSrc = $logoSrc ?: ($baseUrl . '/assets/img/brands/cede4_logo.png');
        $logoUrl = htmlspecialchars($resolvedLogoSrc, ENT_QUOTES, 'UTF-8');

        $institutionalEmail = trim((string) (
        $_ENV['MAIL_PUBLIC_EMAIL'] ?? ($_ENV['MAIL_FROM_ADDRESS'] ?? 'cede@cedern.org')
        ));
        if ($institutionalEmail === '') {
            $institutionalEmail = 'cede@cedern.org';
        }

        $institutionalEmailEscaped = htmlspecialchars($institutionalEmail, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <div
              style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:20px;color:#0f172a;"
            >
              <div
                style="max-width:620px;margin:0 auto;background:#ffffff;
                border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;"
              >
                <div
                  style="padding:20px 20px 16px;background:#ffffff;color:#0f172a;
                  border-bottom:1px solid #e2e8f0;text-align:center;"
                >
                  <div style="margin:0 auto 12px;max-width:220px;">
                    <img
                      src="{$logoUrl}"
                      alt="{$siteName}"
                      style="display:block;max-width:220px;width:100%;height:auto;margin:0 auto;"
                    >
                  </div>
                  <h1 style="margin:8px 0 0;font-size:20px;line-height:1.25;color:#1e293b;">
                    {$titleSafe}
                  </h1>
                  <p style="margin:7px 0 0;font-size:12px;color:#475569;">Comunicação oficial · CEDE</p>
                </div>
                <div style="padding:18px 20px;font-size:14px;line-height:1.6;color:#1e293b;">
                  {$contentHtml}
                </div>
                <div
                  style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;
                  font-size:12px;color:#64748b;"
                >
                  <div style="margin:0 0 4px;">
                    Mensagem automática enviada pelo site {$siteName}.
                  </div>
                  <div style="margin:0;">
                    E-mail:
                    <a href="mailto:{$institutionalEmailEscaped}" style="color:#1d4ed8;text-decoration:none;">
                      {$institutionalEmailEscaped}
                    </a>
                    · Site:
                    <a href="{$siteUrl}" style="color:#1d4ed8;text-decoration:none;">{$siteUrl}</a>
                  </div>
                </div>
              </div>
            </div>
        HTML;
    }
}
