<?php

declare(strict_types=1);

namespace Tests\Application\Diagnostics;

use Tests\TestCase;

final class DiagnosticRoutesTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        foreach ($this->trackedEnvKeys() as $key) {
            $this->originalEnv[$key] = array_key_exists($key, $_ENV)
                ? (string) $_ENV[$key]
                : null;
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
                continue;
            }

            if (is_dir($path)) {
                @rmdir($path);
            }
        }

        foreach ($this->trackedEnvKeys() as $key) {
            $originalValue = $this->originalEnv[$key] ?? null;
            if ($originalValue === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                continue;
            }

            $_ENV[$key] = $originalValue;
            $_SERVER[$key] = $originalValue;
        }
    }

    public function testStorageRouteReturnsJsonReportInTestEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/health/storage')
            ->withQueryParams([
                'kind' => 'member_photos',
                'file' => 'member_demo.jpg',
            ]);
        $response = $app->handle($request);
        $payload = $this->decodeJsonResponse((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('member_photos', $payload['probe']['kind'] ?? null);
        $this->assertSame('member_demo.jpg', $payload['probe']['file'] ?? null);
        $this->assertArrayHasKey('targets', $payload);
    }

    public function testStorageImportRouteExecutesImportAndReturnsSnapshot(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive indisponível.');
        }

        $_ENV['APP_ENV'] = 'test';

        $projectRoot = sys_get_temp_dir() . '/cedern-route-import-project-' . bin2hex(random_bytes(4));
        $managedRoot = sys_get_temp_dir() . '/cedern-route-import-managed-' . bin2hex(random_bytes(4));
        $archiveDirectory = $managedRoot . '/imports/managed-storage-zips';
        $archivePath = $archiveDirectory . '/bookshop-covers.zip';
        $targetPath = $managedRoot . '/bookshop/covers/cover_route.jpg';

        mkdir($projectRoot, 0775, true);
        mkdir($archiveDirectory, 0775, true);

        $this->trackPath($targetPath);
        $this->trackPath($managedRoot . '/bookshop/covers');
        $this->trackPath($managedRoot . '/bookshop');
        $this->trackPath($archivePath);
        $this->trackPath($archiveDirectory);
        $this->trackPath($managedRoot . '/imports');
        $this->trackPath($managedRoot);
        $this->trackPath($projectRoot);

        $this->createZipArchive($archivePath, [
            'cover_route.jpg' => 'route-cover',
        ]);

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $managedRoot;
        $_ENV['APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR'] = $archiveDirectory;

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/health/storage/import')
            ->withQueryParams([
                'execute' => '1',
                'kind' => 'bookshop_covers',
            ]);
        $response = $app->handle($request);
        $payload = $this->decodeJsonResponse((string) $response->getBody());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $payload['status'] ?? null);
        $this->assertSame('explicit_env', $payload['archive_source']['mode'] ?? null);
        $this->assertSame(
            $archiveDirectory,
            $payload['archive_source']['configured_directory']['path'] ?? null
        );
        $this->assertSame('ok', $payload['results']['bookshop_covers']['status'] ?? null);
        $this->assertSame(1, $payload['results']['bookshop_covers']['imported_files'] ?? null);
        $this->assertSame(
            0,
            $payload['results']['bookshop_covers']['post_import_snapshot']['missing_expected_file_count'] ?? null
        );
        $this->assertFileExists($targetPath);
        $this->assertSame('route-cover', file_get_contents($targetPath));
    }

    public function testStorageImportRouteReturns422ForInvalidKind(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/health/storage/import')
            ->withQueryParams([
                'execute' => '1',
                'kind' => 'invalid_kind',
            ]);
        $response = $app->handle($request);
        $payload = $this->decodeJsonResponse((string) $response->getBody());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $payload['status'] ?? null);
        $this->assertSame('import', $payload['mode'] ?? null);
        $this->assertSame('invalid_kind', $payload['requested_kind'] ?? null);
    }

    public function testMigrationsRouteMapsReportAndApplyFailuresToHttpStatusCodes(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $app = $this->getAppInstance();

        $reportRequest = $this->createRequest('GET', '/health/migrations');
        $reportResponse = $app->handle($reportRequest);
        $reportPayload = $this->decodeJsonResponse((string) $reportResponse->getBody());

        $applyRequest = $this->createRequest('GET', '/health/migrations')
            ->withQueryParams(['execute' => '1']);
        $applyResponse = $app->handle($applyRequest);
        $applyPayload = $this->decodeJsonResponse((string) $applyResponse->getBody());

        $this->assertSame(500, $reportResponse->getStatusCode());
        $this->assertSame('error', $reportPayload['status'] ?? null);
        $this->assertSame('report', $reportPayload['mode'] ?? null);

        $this->assertSame(422, $applyResponse->getStatusCode());
        $this->assertSame('error', $applyPayload['status'] ?? null);
        $this->assertSame('apply', $applyPayload['mode'] ?? null);
    }

    public function testReadinessRouteReturns503WhenCriticalChecksFail(): void
    {
        $_ENV['APP_ENV'] = 'test';

        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/health/readiness');
        $response = $app->handle($request);
        $payload = $this->decodeJsonResponse((string) $response->getBody());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('error', $payload['status'] ?? null);
        $this->assertFalse((bool) ($payload['database']['connected'] ?? true));
        $this->assertContains(
            'db_unavailable',
            array_column((array) ($payload['issues'] ?? []), 'code')
        );
    }

    public function testDiagnosticsRoutesReturn404WhenDisabledInProductionWithoutToken(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_ENABLE_DIAGNOSTIC_ROUTES'] = 'false';
        $_ENV['APP_DIAGNOSTIC_TOKEN'] = '';

        $app = $this->getAppInstance();

        $storageResponse = $app->handle($this->createRequest('GET', '/health/storage'));
        $importResponse = $app->handle($this->createRequest('GET', '/health/storage/import'));
        $readinessResponse = $app->handle($this->createRequest('GET', '/health/readiness'));

        $this->assertSame(404, $storageResponse->getStatusCode());
        $this->assertSame(404, $importResponse->getStatusCode());
        $this->assertSame(404, $readinessResponse->getStatusCode());
    }

    /**
     * @param array<string, string> $entries
     */
    private function createZipArchive(string $archivePath, array $entries): void
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->assertTrue($openResult === true, 'Falha ao criar arquivo .zip de teste.');

        foreach ($entries as $entryName => $contents) {
            $this->assertTrue($zip->addFromString($entryName, $contents));
        }

        $zip->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $body): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function trackPath(string $path): void
    {
        if (!in_array($path, $this->temporaryPaths, true)) {
            $this->temporaryPaths[] = $path;
        }
    }

    /**
     * @return list<string>
     */
    private function trackedEnvKeys(): array
    {
        return [
            'APP_ENV',
            'APP_ENABLE_DIAGNOSTIC_ROUTES',
            'APP_DIAGNOSTIC_TOKEN',
            'APP_MANAGED_STORAGE_ROOT',
            'APP_MANAGED_STORAGE_IMPORT_ARCHIVE_DIR',
        ];
    }
}
