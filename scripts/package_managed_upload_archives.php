<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$options = parsePackageOptions($argv);

loadEnvironment($projectRoot);

if (!commandExists('zip')) {
    fwrite(STDERR, "The 'zip' command is required but was not found.\n");
    exit(1);
}

$outputDirectory = resolveProjectPath($projectRoot, $options['output_dir'] ?? 'var/exports/managed-storage-zips');
if (!is_dir($outputDirectory) && !@mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Could not create output directory: {$outputDirectory}\n");
    exit(1);
}

$packages = resolvePackages($projectRoot);
$createdArchives = [];

foreach ($packages as $package) {
    $sourceDirectory = $package['source_directory'];

    if (!is_dir($sourceDirectory)) {
        fwrite(STDERR, "Skipping missing directory: {$sourceDirectory}\n");
        continue;
    }

    if (!directoryHasPackableFiles($sourceDirectory)) {
        fwrite(STDERR, "Skipping empty directory: {$sourceDirectory}\n");
        continue;
    }

    $archivePath = $outputDirectory . '/' . $package['archive_name'] . '.zip';
    if (is_file($archivePath)) {
        @unlink($archivePath);
    }

    $command = sprintf(
        'cd %s && zip -rq %s . -x %s',
        escapeshellarg($sourceDirectory),
        escapeshellarg($archivePath),
        escapeshellarg('.gitignore')
    );

    exec($command, $unusedOutput, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Failed to create archive: {$archivePath}\n");
        exit($exitCode);
    }

    $createdArchives[] = $archivePath;
}

echo PHP_EOL;
echo 'Archives created in: ' . $outputDirectory . PHP_EOL;

foreach ($createdArchives as $archivePath) {
    $fileSize = is_file($archivePath) ? ((int) filesize($archivePath)) : 0;
    echo sprintf("%s\t%s\n", formatBytes($fileSize), $archivePath);
}

echo PHP_EOL;
echo "Run these commands on your local computer to download the archives:\n";

$remoteUser = $options['user'] ?? (getenv('USER') !== false ? (string) getenv('USER') : get_current_user());
$remoteHost = $options['host'] ?? detectHostName();

foreach ($createdArchives as $archivePath) {
    echo sprintf("scp %s@%s:%s .\n", $remoteUser, $remoteHost, $archivePath);
}

if (!isset($options['host']) && hostMayBeLocallyScoped($remoteHost)) {
    echo PHP_EOL;
    echo "If this host does not resolve from your local machine, rerun with:\n";
    echo "  php scripts/package_managed_upload_archives.php --host SEU_HOST_PUBLICO\n";
}

/**
 * @return array{user?: string, host?: string, output_dir?: string}
 */
function parsePackageOptions(array $argv): array
{
    $options = [];
    $arguments = array_slice($argv, 1);

    while ($arguments !== []) {
        $argument = array_shift($arguments);
        if ($argument === '--user') {
            $options['user'] = takePackageOptionValue($argument, $arguments);
            continue;
        }

        if ($argument === '--host') {
            $options['host'] = takePackageOptionValue($argument, $arguments);
            continue;
        }

        if ($argument === '--output-dir') {
            $options['output_dir'] = takePackageOptionValue($argument, $arguments);
            continue;
        }

        fwrite(STDERR, "Unknown argument: {$argument}\n");
        fwrite(STDERR, "Usage: php scripts/package_managed_upload_archives.php [--user USER] [--host HOST] [--output-dir DIR]\n");
        exit(1);
    }

    return $options;
}

/**
 * @param array<int, string> $arguments
 */
function takePackageOptionValue(string $optionName, array &$arguments): string
{
    $value = array_shift($arguments);
    if ($value === null || trim($value) === '') {
        fwrite(STDERR, "Missing value for {$optionName}\n");
        exit(1);
    }

    return trim($value);
}

function loadEnvironment(string $projectRoot): void
{
    $envFileFromServer = '';
    $appEnvFileFromGetenv = getenv('APP_ENV_FILE');

    if ($appEnvFileFromGetenv !== false) {
        $envFileFromServer = trim((string) $appEnvFileFromGetenv);
    } elseif (isset($_SERVER['APP_ENV_FILE'])) {
        $envFileFromServer = trim((string) $_SERVER['APP_ENV_FILE']);
    } elseif (isset($_ENV['APP_ENV_FILE'])) {
        $envFileFromServer = trim((string) $_ENV['APP_ENV_FILE']);
    }

    $dotenvLoaded = false;

    if ($envFileFromServer !== '') {
        $resolvedEnvFilePath = isAbsolutePath($envFileFromServer)
            ? $envFileFromServer
            : $projectRoot . '/' . ltrim($envFileFromServer, '/');

        if (is_file($resolvedEnvFilePath)) {
            Dotenv::createImmutable(dirname($resolvedEnvFilePath), basename($resolvedEnvFilePath))->safeLoad();
            $dotenvLoaded = true;
        }
    }

    if (!$dotenvLoaded && is_file($projectRoot . '/.env')) {
        Dotenv::createImmutable($projectRoot)->safeLoad();
    }
}

function commandExists(string $command): bool
{
    exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1', $unusedOutput, $exitCode);

    return $exitCode === 0;
}

/**
 * @return array<int, array{archive_name: string, source_directory: string}>
 */
function resolvePackages(string $projectRoot): array
{
    return [
        [
            'archive_name' => 'bookshop-covers',
            'source_directory' => resolveManagedDirectory($projectRoot, 'BOOKSHOP_COVER_UPLOAD_DIR', 'var/storage/bookshop/covers'),
        ],
        [
            'archive_name' => 'library-docs',
            'source_directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_UPLOAD_DIR', 'var/storage/library/docs'),
        ],
        [
            'archive_name' => 'library-covers',
            'source_directory' => resolveManagedDirectory($projectRoot, 'LIBRARY_COVER_UPLOAD_DIR', 'var/storage/library/covers'),
        ],
        [
            'archive_name' => 'member-photos',
            'source_directory' => resolveManagedDirectory($projectRoot, 'MEMBER_PROFILE_PHOTO_UPLOAD_DIR', 'var/storage/member-photos'),
        ],
        [
            'archive_name' => 'patrimony-docs',
            'source_directory' => resolveManagedDirectory($projectRoot, 'PATRIMONY_DOCUMENT_UPLOAD_DIR', 'var/storage/patrimony/docs'),
        ],
        [
            'archive_name' => 'patrimony-img',
            'source_directory' => resolveManagedDirectory($projectRoot, 'PATRIMONY_IMAGE_UPLOAD_DIR', 'var/storage/patrimony/img'),
        ],
    ];
}

function directoryHasPackableFiles(string $directory): bool
{
    $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getFilename() !== '.gitignore') {
            return true;
        }
    }

    return false;
}

function resolveManagedDirectory(string $projectRoot, string $envKey, string $defaultDirectory): string
{
    $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));

    if ($configuredDirectory !== '') {
        return resolveConfiguredManagedDirectory($projectRoot, $configuredDirectory);
    }

    return resolveManagedStorageDefaultDirectory($projectRoot, $defaultDirectory);
}

function resolveManagedStorageDefaultDirectory(string $projectRoot, string $defaultDirectory): string
{
    $managedStorageRoot = trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''));
    if ($managedStorageRoot === '') {
        return resolveProjectPath($projectRoot, $defaultDirectory);
    }

    $resolvedRoot = resolveProjectPath($projectRoot, $managedStorageRoot);
    $normalizedDefaultDirectory = ltrim(str_replace('\\', '/', $defaultDirectory), '/');
    $storagePrefix = 'var/storage/';

    if (!str_starts_with($normalizedDefaultDirectory, $storagePrefix)) {
        return resolveProjectPath($projectRoot, $defaultDirectory);
    }

    return $resolvedRoot . '/' . ltrim(substr($normalizedDefaultDirectory, strlen($storagePrefix)), '/');
}

function resolveConfiguredManagedDirectory(string $projectRoot, string $path): string
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    while (str_starts_with($normalizedPath, './')) {
        $normalizedPath = substr($normalizedPath, 2);
    }

    $managedStorageRoot = trim((string) ($_ENV['APP_MANAGED_STORAGE_ROOT'] ?? ''));
    $storagePrefix = 'var/storage/';
    $normalizedRelativePath = ltrim($normalizedPath, '/');

    if (
        $managedStorageRoot !== ''
        && !isAbsolutePath($normalizedPath)
        && str_starts_with($normalizedRelativePath, $storagePrefix)
    ) {
        $resolvedRoot = resolveProjectPath($projectRoot, $managedStorageRoot);

        return $resolvedRoot . '/' . ltrim(substr($normalizedRelativePath, strlen($storagePrefix)), '/');
    }

    return resolveProjectPath($projectRoot, $normalizedPath);
}

function resolveProjectPath(string $projectRoot, string $path): string
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    if ($normalizedPath === '') {
        return $projectRoot;
    }

    if (isAbsolutePath($normalizedPath)) {
        return rtrim($normalizedPath, '/');
    }

    return rtrim($projectRoot . '/' . ltrim($normalizedPath, '/'), '/');
}

function detectHostName(): string
{
    $configuredHost = trim((string) ($_ENV['PACKAGE_DOWNLOAD_HOST'] ?? ''));
    if ($configuredHost !== '') {
        return $configuredHost;
    }

    $fqdn = trim((string) @shell_exec('hostname -f 2>/dev/null'));
    if ($fqdn !== '' && !hostMayBeLocallyScoped($fqdn)) {
        return $fqdn;
    }

    $hostName = gethostname();

    return is_string($hostName) && $hostName !== '' ? $hostName : 'localhost';
}

function hostMayBeLocallyScoped(string $host): bool
{
    $normalizedHost = strtolower(trim($host));

    return $normalizedHost === ''
        || $normalizedHost === 'localhost'
        || !str_contains($normalizedHost, '.');
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . 'B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . 'K';
    }

    return number_format($bytes / (1024 * 1024), 1) . 'M';
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/')
        || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
}
