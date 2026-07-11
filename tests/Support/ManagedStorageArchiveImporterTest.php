<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedStorageArchiveImporter;
use PHPUnit\Framework\TestCase;

final class ManagedStorageArchiveImporterTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        $this->originalEnv['APP_MANAGED_STORAGE_ROOT'] = array_key_exists('APP_MANAGED_STORAGE_ROOT', $_ENV)
            ? (string) $_ENV['APP_MANAGED_STORAGE_ROOT']
            : null;
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

        $originalManagedRoot = $this->originalEnv['APP_MANAGED_STORAGE_ROOT'];
        if ($originalManagedRoot === null) {
            unset($_ENV['APP_MANAGED_STORAGE_ROOT']);
        } else {
            $_ENV['APP_MANAGED_STORAGE_ROOT'] = $originalManagedRoot;
        }
    }

    public function testImportExtractsArchiveIntoManagedStorageBucket(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive indisponível.');
        }

        $projectRoot = sys_get_temp_dir() . '/cedern-import-project-' . bin2hex(random_bytes(4));
        $managedRoot = sys_get_temp_dir() . '/cedern-import-managed-' . bin2hex(random_bytes(4));
        $archiveDirectory = $managedRoot . '/imports/managed-storage-zips';
        $archivePath = $archiveDirectory . '/bookshop-covers.zip';

        mkdir($projectRoot, 0775, true);
        mkdir($archiveDirectory, 0775, true);

        $this->trackPath($archivePath);
        $this->trackPath($archiveDirectory);
        $this->trackPath($managedRoot . '/imports');
        $this->trackPath($managedRoot);
        $this->trackPath($projectRoot);

        $this->createZipArchive($archivePath, [
            'cover_demo.jpg' => 'demo-cover',
        ]);

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $managedRoot;

        $importer = new ManagedStorageArchiveImporter($projectRoot, $_ENV);
        $report = $importer->report('bookshop_covers');

        $this->assertSame(
            $archivePath,
            $report['packages']['bookshop_covers']['selected_archive']['path'] ?? null
        );

        $result = $importer->import('bookshop_covers');
        $targetPath = $managedRoot . '/bookshop/covers/cover_demo.jpg';

        $this->trackPath($targetPath);
        $this->trackPath($managedRoot . '/bookshop/covers');
        $this->trackPath($managedRoot . '/bookshop');

        $this->assertSame('ok', $result['status'] ?? null);
        $this->assertSame('ok', $result['results']['bookshop_covers']['status'] ?? null);
        $this->assertSame(1, $result['results']['bookshop_covers']['imported_files'] ?? null);
        $this->assertFileExists($targetPath);
        $this->assertSame('demo-cover', file_get_contents($targetPath));
    }

    public function testImportRejectsNestedArchiveEntries(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive indisponível.');
        }

        $projectRoot = sys_get_temp_dir() . '/cedern-import-project-' . bin2hex(random_bytes(4));
        $managedRoot = sys_get_temp_dir() . '/cedern-import-managed-' . bin2hex(random_bytes(4));
        $archiveDirectory = $managedRoot . '/imports/managed-storage-zips';
        $archivePath = $archiveDirectory . '/bookshop-covers.zip';

        mkdir($projectRoot, 0775, true);
        mkdir($archiveDirectory, 0775, true);

        $this->trackPath($archivePath);
        $this->trackPath($archiveDirectory);
        $this->trackPath($managedRoot . '/imports');
        $this->trackPath($managedRoot);
        $this->trackPath($projectRoot);

        $this->createZipArchive($archivePath, [
            'bookshop-covers/cover_demo.jpg' => 'demo-cover',
        ]);

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $managedRoot;

        $importer = new ManagedStorageArchiveImporter($projectRoot, $_ENV);
        $result = $importer->import('bookshop_covers');
        $targetPath = $managedRoot . '/bookshop/covers/cover_demo.jpg';

        $this->trackPath($managedRoot . '/bookshop/covers');
        $this->trackPath($managedRoot . '/bookshop');

        $this->assertSame('error', $result['status'] ?? null);
        $this->assertSame('error', $result['results']['bookshop_covers']['status'] ?? null);
        $this->assertNotEmpty($result['results']['bookshop_covers']['invalid_entries'] ?? []);
        $this->assertFileDoesNotExist($targetPath);
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

    private function trackPath(string $path): void
    {
        if (!in_array($path, $this->temporaryPaths, true)) {
            $this->temporaryPaths[] = $path;
        }
    }
}
