<?php

declare(strict_types=1);

namespace App\Support;

final class DeploymentEnvironment
{
    /**
     * @var array<string, array{label: string, tone: string}>
     */
    private const STAGE_DEFINITIONS = [
        'development' => [
            'label' => 'Desenvolvimento',
            'tone' => 'dev',
        ],
        'homolog' => [
            'label' => 'Homologação',
            'tone' => 'test',
        ],
        'production' => [
            'label' => 'Produção',
            'tone' => 'prod',
        ],
    ];

    /**
     * @param array<string, mixed> $env
     * @return array{stage: string, label: string, tone: string, title_prefix: string}
     */
    public static function resolve(array $env): array
    {
        $stage = self::resolveStage($env);
        $definition = self::STAGE_DEFINITIONS[$stage] ?? self::STAGE_DEFINITIONS['production'];
        $labelOverride = trim((string) ($env['APP_DEPLOY_STAGE_LABEL'] ?? ''));
        $label = $labelOverride !== '' ? $labelOverride : $definition['label'];

        return [
            'stage' => $stage,
            'label' => $label,
            'tone' => $definition['tone'],
            'title_prefix' => $stage === 'production' ? '' : '[' . $label . '] ',
        ];
    }

    /**
     * @param array<string, mixed> $env
     */
    private static function resolveStage(array $env): string
    {
        $explicitStage = self::normalizeStage((string) ($env['APP_DEPLOY_STAGE'] ?? ''));
        if ($explicitStage !== null) {
            return $explicitStage;
        }

        $urlStage = self::resolveStageFromUrl((string) ($env['APP_DEFAULT_PAGE_URL'] ?? ''));
        if ($urlStage !== null) {
            return $urlStage;
        }

        return self::normalizeStage((string) ($env['APP_ENV'] ?? 'production')) ?? 'production';
    }

    private static function resolveStageFromUrl(string $rawUrl): ?string
    {
        $url = trim($rawUrl);
        if ($url === '') {
            return null;
        }

        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST)));
        if ($host === '') {
            return null;
        }

        if (str_contains($host, 'homolog')) {
            return 'homolog';
        }

        if (
            str_contains($host, 'localhost')
            || str_contains($host, '.local')
            || str_contains($host, '.test')
        ) {
            return 'development';
        }

        return null;
    }

    private static function normalizeStage(string $rawStage): ?string
    {
        $stage = strtolower(trim($rawStage));

        return match ($stage) {
            'dev', 'development', 'local' => 'development',
            'test', 'testing', 'qa', 'homolog', 'staging' => 'homolog',
            'prod', 'production', 'live' => 'production',
            default => null,
        };
    }
}
