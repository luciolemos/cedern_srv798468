<?php

declare(strict_types=1);

namespace App\Application\Support;

final class InstitutionalEmailTemplate
{
    public static function buildInstitutionHeaderMeta(
        ?string $institutionName = null,
        ?string $cnpj = null
    ): string {
        $resolvedInstitutionName = trim((string) $institutionName);
        if ($resolvedInstitutionName === '') {
            $resolvedInstitutionName = 'CENTRO DE ESTUDOS DA DOUTRINA ESPÍRITA';
        }

        $resolvedCnpj = trim((string) $cnpj);
        if ($resolvedCnpj === '') {
            $resolvedCnpj = '04.242.556/0001-45';
        }

        $safeInstitutionName = htmlspecialchars($resolvedInstitutionName, ENT_QUOTES, 'UTF-8');
        $safeCnpj = htmlspecialchars($resolvedCnpj, ENT_QUOTES, 'UTF-8');

        return '<p style="margin:0 0 4px;font-size:12px;line-height:1.35;'
            . 'letter-spacing:0.03em;color:#1e293b;font-weight:700;white-space:nowrap;">'
            . $safeInstitutionName . '</p>'
            . '<p style="margin:0 0 10px;font-size:11px;line-height:1.3;color:#64748b;">'
            . 'CNPJ: ' . $safeCnpj . '</p>';
    }

    public static function buildLayout(
        string $title,
        string $contentHtml,
        ?string $logoSrc = null,
        ?string $headerMetaHtml = null
    ): string {
        $titleSafe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars((string) ($_ENV['APP_DEFAULT_SITE_NAME'] ?? 'CEDE'), ENT_QUOTES, 'UTF-8');
        $baseUrl = rtrim((string) ($_ENV['APP_DEFAULT_PAGE_URL'] ?? 'https://cedern.org/'), '/');
        $siteUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        $resolvedLogoSrc = $logoSrc ?: ($baseUrl . '/assets/img/brands/cede4_logo.png');
        $logoUrl = htmlspecialchars($resolvedLogoSrc, ENT_QUOTES, 'UTF-8');
        $resolvedHeaderMetaHtml = trim((string) $headerMetaHtml);

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
                  {$resolvedHeaderMetaHtml}
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

    /**
     * @param array<int, array{href: string, label: string, is_primary?: bool}> $actions
     */
    public static function buildActionGroup(array $actions): string
    {
        $rows = [];

        foreach ($actions as $action) {
            $href = htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8');
            $isPrimary = (bool) ($action['is_primary'] ?? false);

            if ($href === '' || $label === '') {
                continue;
            }

            $style = $isPrimary
                ? 'display:block;padding:11px 15px;border-radius:10px;background:#2563eb;'
                    . 'color:#ffffff;text-decoration:none;font-weight:600;text-align:center;'
                : 'display:block;padding:11px 15px;border-radius:10px;border:1px solid #cbd5e1;'
                    . 'background:#ffffff;color:#1e293b;text-decoration:none;font-weight:600;text-align:center;';

            $rows[] = '<tr><td style="padding:0 0 8px;">'
                . '<a href="' . $href . '" style="' . $style . '">' . $label . '</a>'
                . '</td></tr>';
        }

        if ($rows === []) {
            return '';
        }

        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" '
            . 'style="margin:0 0 10px;width:100%;max-width:320px;">'
            . implode('', $rows)
            . '</table>';
    }
}
