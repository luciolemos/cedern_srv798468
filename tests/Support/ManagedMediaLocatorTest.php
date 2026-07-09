<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedMediaLocator;
use PHPUnit\Framework\TestCase;

final class ManagedMediaLocatorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        foreach ($this->temporaryDirectories as $directoryPath) {
            if (is_dir($directoryPath)) {
                @rmdir($directoryPath);
            }
        }

        parent::tearDown();
    }

    public function testResolvesExistingFileFromMatchingManagedPrefix(): void
    {
        $directory = sys_get_temp_dir() . '/cedern-managed-media-' . bin2hex(random_bytes(4));
        mkdir($directory, 0775, true);
        $this->temporaryDirectories[] = $directory;

        $filePath = $directory . '/cover_test_direct.jpg';
        file_put_contents($filePath, 'cover');
        $this->temporaryFiles[] = $filePath;

        $this->assertSame(
            $filePath,
            ManagedMediaLocator::resolve('media/livraria/capas/cover_test_direct.jpg', [
                [
                    'directory' => $directory,
                    'public_prefix' => 'media/livraria/capas',
                ],
            ])
        );
    }

    public function testFallsBackToLegacyDirectoryByFileNameWhenPublicPrefixWasNormalized(): void
    {
        $managedDirectory = sys_get_temp_dir() . '/cedern-managed-media-managed-' . bin2hex(random_bytes(4));
        $legacyDirectory = sys_get_temp_dir() . '/cedern-managed-media-legacy-' . bin2hex(random_bytes(4));
        mkdir($managedDirectory, 0775, true);
        mkdir($legacyDirectory, 0775, true);
        $this->temporaryDirectories[] = $managedDirectory;
        $this->temporaryDirectories[] = $legacyDirectory;

        $filePath = $legacyDirectory . '/cover_test_legacy.jpg';
        file_put_contents($filePath, 'legacy-cover');
        $this->temporaryFiles[] = $filePath;

        $this->assertSame(
            $filePath,
            ManagedMediaLocator::resolve('media/livraria/capas/cover_test_legacy.jpg', [
                [
                    'directory' => $managedDirectory,
                    'public_prefix' => 'media/livraria/capas',
                ],
                [
                    'directory' => $legacyDirectory,
                    'public_prefix' => 'assets/img/bookshop-covers',
                ],
            ])
        );
    }

    public function testFallsBackToAdditionalDirectoryByFileName(): void
    {
        $managedDirectory = sys_get_temp_dir() . '/cedern-managed-media-managed-' . bin2hex(random_bytes(4));
        $genericDirectory = sys_get_temp_dir() . '/cedern-managed-media-generic-' . bin2hex(random_bytes(4));
        mkdir($managedDirectory, 0775, true);
        mkdir($genericDirectory, 0775, true);
        $this->temporaryDirectories[] = $managedDirectory;
        $this->temporaryDirectories[] = $genericDirectory;

        $filePath = $genericDirectory . '/member_test_generic.jpg';
        file_put_contents($filePath, 'member-photo');
        $this->temporaryFiles[] = $filePath;

        $this->assertSame(
            $filePath,
            ManagedMediaLocator::resolve(
                'media/membros/fotos/member_test_generic.jpg',
                [
                    [
                        'directory' => $managedDirectory,
                        'public_prefix' => 'media/membros/fotos',
                    ],
                ],
                [$genericDirectory]
            )
        );
    }

    public function testReturnsManagedFallbackPathWhenNoFileExistsAnywhere(): void
    {
        $managedDirectory = sys_get_temp_dir() . '/cedern-managed-media-missing-' . bin2hex(random_bytes(4));
        mkdir($managedDirectory, 0775, true);
        $this->temporaryDirectories[] = $managedDirectory;

        $this->assertSame(
            $managedDirectory . '/missing.jpg',
            ManagedMediaLocator::resolve('media/livraria/capas/missing.jpg', [
                [
                    'directory' => $managedDirectory,
                    'public_prefix' => 'media/livraria/capas',
                ],
            ])
        );
    }
}
