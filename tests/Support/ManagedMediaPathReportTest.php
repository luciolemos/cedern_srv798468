<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedMediaPathReport;
use PHPUnit\Framework\TestCase;

final class ManagedMediaPathReportTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $originalEnv = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        foreach ([
            'APP_MANAGED_STORAGE_ROOT',
            'MEMBER_PROFILE_PHOTO_UPLOAD_DIR',
            'MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX',
        ] as $key) {
            $this->originalEnv[$key] = array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        rsort($this->temporaryDirectories);
        foreach ($this->temporaryDirectories as $directoryPath) {
            if (is_dir($directoryPath)) {
                @rmdir($directoryPath);
            }
        }

        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                continue;
            }

            $_ENV[$key] = $value;
        }
    }

    public function testProbeReportsSampleEntriesAndRecursiveMatches(): void
    {
        $projectRoot = sys_get_temp_dir() . '/cedern-report-project-' . bin2hex(random_bytes(4));
        $managedRoot = sys_get_temp_dir() . '/cedern-report-managed-' . bin2hex(random_bytes(4));
        $memberDirectory = $managedRoot . '/member-photos';
        $nestedDirectory = $managedRoot . '/imports/member-photos';

        mkdir($projectRoot . '/var/storage/member-photos', 0775, true);
        mkdir($memberDirectory, 0775, true);
        mkdir($nestedDirectory, 0775, true);

        $this->temporaryDirectories[] = $projectRoot . '/var/storage/member-photos';
        $this->temporaryDirectories[] = $projectRoot . '/var/storage';
        $this->temporaryDirectories[] = $projectRoot . '/var';
        $this->temporaryDirectories[] = $projectRoot;
        $this->temporaryDirectories[] = $nestedDirectory;
        $this->temporaryDirectories[] = dirname($nestedDirectory);
        $this->temporaryDirectories[] = $memberDirectory;
        $this->temporaryDirectories[] = $managedRoot;

        file_put_contents($memberDirectory . '/existing_member.jpg', 'member');
        $this->temporaryFiles[] = $memberDirectory . '/existing_member.jpg';

        file_put_contents($nestedDirectory . '/recursive_member.jpg', 'member-recursive');
        $this->temporaryFiles[] = $nestedDirectory . '/recursive_member.jpg';

        $_ENV['APP_MANAGED_STORAGE_ROOT'] = $managedRoot;
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_DIR'] = $memberDirectory;
        $_ENV['MEMBER_PROFILE_PHOTO_UPLOAD_PUBLIC_PREFIX'] = 'media/membros/fotos';

        $report = (new ManagedMediaPathReport($projectRoot))->build('member_photos', 'recursive_member.jpg');

        $configured = $report['targets']['member_photos']['configured_directory'] ?? [];
        $this->assertTrue((bool) ($configured['exists'] ?? false));
        $this->assertSame($memberDirectory, $configured['realpath'] ?? null);
        $this->assertSame('existing_member.jpg', $configured['sample_entries'][0]['name'] ?? null);

        $probe = $report['probe'] ?? [];
        $this->assertSame([], $probe['existing_matches'] ?? []);
        $this->assertNotEmpty($probe['recursive_matches'] ?? []);
        $this->assertSame(
            $nestedDirectory . '/recursive_member.jpg',
            $probe['recursive_matches'][0]['path'] ?? null
        );
    }
}
