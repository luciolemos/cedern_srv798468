<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractAdminBookshopAction extends AbstractPageAction
{
    private const DEFAULT_BOOKSHOP_COVER_UPLOAD_DIR = 'public/assets/img/bookshop-covers';
    private const DEFAULT_BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX = 'assets/img/bookshop-covers';
    private const FALLBACK_BOOKSHOP_COVER_UPLOAD_DIR = 'var/cache/bookshop-covers';
    private const FALLBACK_BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX = 'media/livraria/capas';
    private const TEMP_BOOKSHOP_COVER_UPLOAD_SUBDIR = 'natalcode/bookshop-covers';
    private const BOOKSHOP_COVER_TARGET_MAX_WIDTH = 1024;
    private const BOOKSHOP_COVER_TARGET_MAX_HEIGHT = 1536;
    private const BOOKSHOP_COVER_TARGET_RATIO = 0.6666666667;
    private const BOOKSHOP_COVER_MARGIN_SCAN_LIMIT_RATIO = 0.12;
    private const BOOKSHOP_COVER_BACKGROUND_TOLERANCE = 22;
    private const BOOKSHOP_COVER_BACKGROUND_MATCH_RATIO = 0.985;

    /**
     * @var array<int, string>
     */
    private const ROMANCE_LANGUAGE_OPTIONS = [
        'Português',
        'Espanhol',
        'Francês',
        'Italiano',
        'Romeno',
        'Catalão',
        'Galego',
    ];

    private const DEFAULT_BOOKSHOP_LANGUAGE = 'Português';

    protected BookshopRepository $bookshopRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, BookshopRepository $bookshopRepository)
    {
        parent::__construct($logger, $twig);
        $this->bookshopRepository = $bookshopRepository;
    }

    protected function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = (string) preg_replace('/[^a-z0-9-]+/', '-', $normalized);

        return trim($normalized, '-');
    }

    protected function normalizeMoneyInput(mixed $value): string
    {
        $normalized = trim((string) $value);
        $normalized = str_replace(['R$', ' '], '', $normalized);

        if ($normalized === '') {
            return '0.00';
        }

        $hasComma = strpos($normalized, ',') !== false;
        $hasDot = strpos($normalized, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return '0.00';
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    protected function normalizeIntegerInput(mixed $value, int $default = 0): int
    {
        $normalized = trim((string) $value);
        if ($normalized === '' || !preg_match('/^-?\d+$/', $normalized)) {
            return $default;
        }

        return (int) $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected function getBookshopLanguageOptions(): array
    {
        return self::ROMANCE_LANGUAGE_OPTIONS;
    }

    protected function getDefaultBookshopLanguage(): string
    {
        return self::DEFAULT_BOOKSHOP_LANGUAGE;
    }

    /**
     * @return array{path?: string, mime_type?: string, size_bytes?: int, error?: string}
     */
    protected function storeBookshopCover(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar a capa. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (5 * 1024 * 1024)) {
            return ['error' => 'A capa deve ter no máximo 5MB.'];
        }

        $clientMimeType = strtolower((string) $file->getClientMediaType());
        $clientFilename = strtolower(trim((string) $file->getClientFilename()));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $fileExtension = strtolower((string) pathinfo($clientFilename, PATHINFO_EXTENSION));

        if (!isset($allowedMimeTypes[$clientMimeType]) && !in_array($fileExtension, $allowedExtensions, true)) {
            return ['error' => 'Formato inválido para a capa. Use JPG, PNG ou WEBP.'];
        }

        $destination = $this->resolveWritableBookshopCoverDestination();
        if ($destination === null) {
            return ['error' => 'O servidor não conseguiu gravar capas da livraria. Verifique as permissões do ambiente.'];
        }

        $targetDirectory = $destination['directory'];
        $publicPrefix = $destination['public_prefix'];

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $fileToken = sprintf('cover_%s_%s', $timestamp, $randomSuffix);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gerar nome de arquivo para capa da livraria.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar a capa enviada.'];
        }

        $temporaryUploadPath = $targetDirectory . '/' . $fileToken . '.upload';

        try {
            $file->moveTo($temporaryUploadPath);
        } catch (\Throwable $exception) {
            $this->logger->error('Falha ao gravar capa da livraria.', [
                'exception' => $exception,
                'target_path' => $temporaryUploadPath,
            ]);

            return ['error' => 'Não foi possível salvar a capa no servidor.'];
        }

        $detectedImage = @getimagesize($temporaryUploadPath);
        $detectedMimeType = strtolower((string) ($detectedImage['mime'] ?? ''));
        $resolvedExtension = $allowedMimeTypes[$detectedMimeType]
            ?? $allowedMimeTypes[$clientMimeType]
            ?? ($fileExtension !== '' ? $fileExtension : 'jpg');
        $targetPath = $targetDirectory . '/' . $fileToken . '.' . $resolvedExtension;
        $finalMimeType = $detectedMimeType !== '' ? $detectedMimeType : ($clientMimeType !== '' ? $clientMimeType : 'image/jpeg');

        if (!isset($allowedMimeTypes[$finalMimeType])) {
            @unlink($temporaryUploadPath);

            return ['error' => 'Formato inválido para a capa. Use JPG, PNG ou WEBP.'];
        }

        $finalSize = $size;

        try {
            $normalizationResult = $this->normalizeBookshopCoverImage($temporaryUploadPath, $targetPath, $finalMimeType);

            if ($normalizationResult === null) {
                if (!@rename($temporaryUploadPath, $targetPath)) {
                    throw new \RuntimeException('Falha ao mover a capa tratada para o destino final.');
                }

                $finalSize = is_file($targetPath) ? ((int) filesize($targetPath) ?: $size) : $size;
            } else {
                $finalMimeType = $normalizationResult['mime_type'];
                $finalSize = $normalizationResult['size_bytes'];
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao normalizar capa da livraria. Salvando arquivo bruto.', [
                'exception' => $exception,
                'target_path' => $targetPath,
            ]);

            if (!@rename($temporaryUploadPath, $targetPath)) {
                @unlink($temporaryUploadPath);

                return ['error' => 'Não foi possível salvar a capa no servidor.'];
            }

            $finalSize = is_file($targetPath) ? ((int) filesize($targetPath) ?: $size) : $size;
        } finally {
            if (is_file($temporaryUploadPath)) {
                @unlink($temporaryUploadPath);
            }
        }

        $fileName = basename($targetPath);

        return [
            'path' => $this->buildManagedBookshopCoverRelativePath($fileName, $publicPrefix),
            'mime_type' => $finalMimeType,
            'size_bytes' => $finalSize,
        ];
    }

    protected function deleteStoredBookshopCoverIfManaged(?string $relativePath): void
    {
        $absolutePath = $this->resolveManagedBookshopCoverAbsolutePath($relativePath);
        if ($absolutePath === null) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    protected function normalizeBookshopLanguage(mixed $value): string
    {
        $language = trim((string) $value);
        $language = trim($language, " \t\n\r\0\x0B|");
        if ($language === '') {
            return self::DEFAULT_BOOKSHOP_LANGUAGE;
        }

        $normalizedLanguage = $this->normalizeBookshopLanguageKey($language);

        foreach (self::ROMANCE_LANGUAGE_OPTIONS as $option) {
            if ($this->normalizeBookshopLanguageKey($option) === $normalizedLanguage) {
                return $option;
            }
        }

        return $language;
    }

    /**
     * @return array{member_id: int, member_name: string}
     */
    protected function resolveAdminActor(): array
    {
        $this->ensureSessionStarted();

        return [
            'member_id' => (int) ($_SESSION['member_user_id'] ?? 0),
            'member_name' => trim((string) ($_SESSION['member_name'] ?? 'Admin')),
        ];
    }

    protected function resolveBookshopCoverUploadDirectory(): string
    {
        return $this->resolveConfiguredUploadDirectory(
            'BOOKSHOP_COVER_UPLOAD_DIR',
            self::DEFAULT_BOOKSHOP_COVER_UPLOAD_DIR
        );
    }

    protected function resolveBookshopCoverUploadPublicPrefix(): string
    {
        return $this->resolveConfiguredUploadPublicPrefix(
            'BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX',
            self::DEFAULT_BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX
        );
    }

    protected function resolveBookshopCoverFallbackUploadDirectory(): string
    {
        return $this->resolveProjectRoot() . '/' . self::FALLBACK_BOOKSHOP_COVER_UPLOAD_DIR;
    }

    protected function resolveBookshopCoverFallbackPublicPrefix(): string
    {
        return self::FALLBACK_BOOKSHOP_COVER_UPLOAD_PUBLIC_PREFIX;
    }

    protected function resolveBookshopCoverTemporaryUploadDirectory(): string
    {
        $temporaryBaseDirectory = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');

        return $temporaryBaseDirectory . '/' . self::TEMP_BOOKSHOP_COVER_UPLOAD_SUBDIR;
    }

    protected function buildManagedBookshopCoverRelativePath(string $fileName, ?string $publicPrefix = null): string
    {
        $resolvedPrefix = trim((string) ($publicPrefix ?? $this->resolveBookshopCoverUploadPublicPrefix()), '/');

        return $resolvedPrefix . '/' . ltrim($fileName, '/');
    }

    protected function resolveManagedBookshopCoverAbsolutePath(?string $relativePath): ?string
    {
        $primaryPath = $this->resolveManagedAbsolutePath(
            $relativePath,
            $this->resolveBookshopCoverUploadPublicPrefix(),
            $this->resolveBookshopCoverUploadDirectory()
        );

        if ($primaryPath !== null) {
            return $primaryPath;
        }

        return $this->resolveManagedPrivateBookshopCoverAbsolutePath($relativePath);
    }

    private function normalizeBookshopLanguageKey(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $normalized = strtr($normalized, [
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    /**
     * @return array{mime_type: string, size_bytes: int}|null
     */
    private function normalizeBookshopCoverImage(string $sourcePath, string $targetPath, string $sourceMimeType): ?array
    {
        if (!$this->canNormalizeBookshopCoverImage()) {
            return null;
        }

        $sourceImage = $this->createBookshopCoverSourceImage($sourcePath, $sourceMimeType);
        if ($sourceImage === null) {
            return null;
        }

        try {
            $orientedImage = $this->applyBookshopCoverExifOrientation($sourceImage, $sourcePath, $sourceMimeType);
            if ($orientedImage !== $sourceImage) {
                imagedestroy($sourceImage);
                $sourceImage = $orientedImage;
            }

            $flattenedImage = $this->flattenBookshopCoverImage($sourceImage);
            if ($flattenedImage !== $sourceImage) {
                imagedestroy($sourceImage);
                $sourceImage = $flattenedImage;
            }

            $trimmedImage = $this->trimBookshopCoverMargins($sourceImage);
            if ($trimmedImage !== $sourceImage) {
                imagedestroy($sourceImage);
                $sourceImage = $trimmedImage;
            }

            $backgroundColor = $this->sampleBookshopCoverBackgroundColor($sourceImage);
            $geometry = $this->resolveBookshopCoverCanvasGeometry(imagesx($sourceImage), imagesy($sourceImage));
            $normalizedCanvas = imagecreatetruecolor($geometry['canvas_width'], $geometry['canvas_height']);
            if ($normalizedCanvas === false) {
                throw new \RuntimeException('Falha ao criar canvas da capa normalizada.');
            }

            $fill = imagecolorallocate(
                $normalizedCanvas,
                $backgroundColor['r'],
                $backgroundColor['g'],
                $backgroundColor['b']
            );
            imagefill($normalizedCanvas, 0, 0, $fill);
            imagealphablending($normalizedCanvas, true);

            imagecopyresampled(
                $normalizedCanvas,
                $sourceImage,
                $geometry['offset_x'],
                $geometry['offset_y'],
                0,
                0,
                $geometry['draw_width'],
                $geometry['draw_height'],
                imagesx($sourceImage),
                imagesy($sourceImage)
            );

            $this->saveNormalizedBookshopCoverImage($normalizedCanvas, $targetPath, $sourceMimeType);
            imagedestroy($normalizedCanvas);
        } finally {
            imagedestroy($sourceImage);
        }

        $normalizedSize = is_file($targetPath) ? (int) filesize($targetPath) : 0;

        return [
            'mime_type' => $sourceMimeType,
            'size_bytes' => $normalizedSize > 0 ? $normalizedSize : 0,
        ];
    }

    private function canNormalizeBookshopCoverImage(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('getimagesize');
    }

    private function createBookshopCoverSourceImage(string $sourcePath, string $sourceMimeType): mixed
    {
        return match ($sourceMimeType) {
            'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg')
                ? @imagecreatefromjpeg($sourcePath)
                : null,
            'image/png' => function_exists('imagecreatefrompng')
                ? @imagecreatefrompng($sourcePath)
                : null,
            'image/webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($sourcePath)
                : null,
            default => null,
        };
    }

    private function applyBookshopCoverExifOrientation(mixed $image, string $sourcePath, string $sourceMimeType): mixed
    {
        if (
            $sourceMimeType !== 'image/jpeg'
            && $sourceMimeType !== 'image/jpg'
            || !function_exists('exif_read_data')
        ) {
            return $image;
        }

        $exifData = @exif_read_data($sourcePath);
        $orientation = (int) ($exifData['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flipBookshopCoverImage($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotateBookshopCoverImage($image, 180),
            4 => $this->flipBookshopCoverImage($image, IMG_FLIP_VERTICAL),
            5 => $this->flipBookshopCoverImage($this->rotateBookshopCoverImage($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotateBookshopCoverImage($image, -90),
            7 => $this->flipBookshopCoverImage($this->rotateBookshopCoverImage($image, 90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotateBookshopCoverImage($image, 90),
            default => $image,
        };
    }

    private function rotateBookshopCoverImage(mixed $image, int $degrees): mixed
    {
        if (!function_exists('imagerotate')) {
            return $image;
        }

        $rotated = @imagerotate($image, $degrees, 0);

        return $rotated !== false ? $rotated : $image;
    }

    private function flipBookshopCoverImage(mixed $image, int $mode): mixed
    {
        if (!function_exists('imageflip')) {
            return $image;
        }

        @imageflip($image, $mode);

        return $image;
    }

    private function flattenBookshopCoverImage(mixed $image): mixed
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $flattened = imagecreatetruecolor($width, $height);
        if ($flattened === false) {
            return $image;
        }

        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefill($flattened, 0, 0, $white);
        imagealphablending($flattened, true);
        imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);

        return $flattened;
    }

    private function trimBookshopCoverMargins(mixed $image): mixed
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 48 || $height < 72) {
            return $image;
        }

        $backgroundColor = $this->sampleBookshopCoverBackgroundColor($image);
        $maxHorizontalTrim = max(0, (int) floor($width * self::BOOKSHOP_COVER_MARGIN_SCAN_LIMIT_RATIO));
        $maxVerticalTrim = max(0, (int) floor($height * self::BOOKSHOP_COVER_MARGIN_SCAN_LIMIT_RATIO));

        $leftTrim = $this->scanBookshopCoverVerticalMargin($image, 0, 1, $maxHorizontalTrim, $backgroundColor);
        $rightTrim = $this->scanBookshopCoverVerticalMargin($image, $width - 1, -1, $maxHorizontalTrim, $backgroundColor);
        $topTrim = $this->scanBookshopCoverHorizontalMargin($image, 0, 1, $maxVerticalTrim, $backgroundColor);
        $bottomTrim = $this->scanBookshopCoverHorizontalMargin($image, $height - 1, -1, $maxVerticalTrim, $backgroundColor);

        $croppedWidth = $width - $leftTrim - $rightTrim;
        $croppedHeight = $height - $topTrim - $bottomTrim;

        if ($croppedWidth < 48 || $croppedHeight < 72) {
            return $image;
        }

        if ($leftTrim === 0 && $rightTrim === 0 && $topTrim === 0 && $bottomTrim === 0) {
            return $image;
        }

        $croppedImage = @imagecrop($image, [
            'x' => $leftTrim,
            'y' => $topTrim,
            'width' => $croppedWidth,
            'height' => $croppedHeight,
        ]);

        return $croppedImage !== false ? $croppedImage : $image;
    }

    private function scanBookshopCoverVerticalMargin(
        mixed $image,
        int $startX,
        int $direction,
        int $maxTrim,
        array $backgroundColor
    ): int {
        $height = imagesy($image);
        $step = max(1, (int) floor($height / 96));
        $trim = 0;

        for ($offset = 0; $offset < $maxTrim; $offset++) {
            $x = $startX + ($offset * $direction);
            $samples = 0;
            $backgroundMatches = 0;

            for ($y = 0; $y < $height; $y += $step) {
                $samples++;

                if ($this->isBookshopCoverPixelNearBackground($image, $x, $y, $backgroundColor)) {
                    $backgroundMatches++;
                }
            }

            $matchRatio = $backgroundMatches / $samples;
            if ($matchRatio < self::BOOKSHOP_COVER_BACKGROUND_MATCH_RATIO) {
                break;
            }

            $trim++;
        }

        return $trim;
    }

    private function scanBookshopCoverHorizontalMargin(
        mixed $image,
        int $startY,
        int $direction,
        int $maxTrim,
        array $backgroundColor
    ): int {
        $width = imagesx($image);
        $step = max(1, (int) floor($width / 96));
        $trim = 0;

        for ($offset = 0; $offset < $maxTrim; $offset++) {
            $y = $startY + ($offset * $direction);
            $samples = 0;
            $backgroundMatches = 0;

            for ($x = 0; $x < $width; $x += $step) {
                $samples++;

                if ($this->isBookshopCoverPixelNearBackground($image, $x, $y, $backgroundColor)) {
                    $backgroundMatches++;
                }
            }

            $matchRatio = $backgroundMatches / $samples;
            if ($matchRatio < self::BOOKSHOP_COVER_BACKGROUND_MATCH_RATIO) {
                break;
            }

            $trim++;
        }

        return $trim;
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function sampleBookshopCoverBackgroundColor(mixed $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $samplePoints = [
            [1, 1],
            [max(1, $width - 2), 1],
            [1, max(1, $height - 2)],
            [max(1, $width - 2), max(1, $height - 2)],
        ];

        $red = 0;
        $green = 0;
        $blue = 0;
        $sampleCount = 0;

        foreach ($samplePoints as [$x, $y]) {
            $color = $this->readBookshopCoverPixelColor($image, $x, $y);
            $red += $color['r'];
            $green += $color['g'];
            $blue += $color['b'];
            $sampleCount++;
        }

        return [
            'r' => (int) round($red / $sampleCount),
            'g' => (int) round($green / $sampleCount),
            'b' => (int) round($blue / $sampleCount),
        ];
    }

    private function isBookshopCoverPixelNearBackground(
        mixed $image,
        int $x,
        int $y,
        array $backgroundColor
    ): bool {
        $color = $this->readBookshopCoverPixelColor($image, $x, $y);

        return max(
            abs($color['r'] - $backgroundColor['r']),
            abs($color['g'] - $backgroundColor['g']),
            abs($color['b'] - $backgroundColor['b'])
        ) <= self::BOOKSHOP_COVER_BACKGROUND_TOLERANCE;
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function readBookshopCoverPixelColor(mixed $image, int $x, int $y): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $safeX = min(max($x, 0), max(0, $width - 1));
        $safeY = min(max($y, 0), max(0, $height - 1));
        $rgba = imagecolorat($image, $safeX, $safeY);

        return [
            'r' => ($rgba >> 16) & 0xFF,
            'g' => ($rgba >> 8) & 0xFF,
            'b' => $rgba & 0xFF,
        ];
    }

    /**
     * @return array{canvas_width: int, canvas_height: int, draw_width: int, draw_height: int, offset_x: int, offset_y: int}
     */
    private function resolveBookshopCoverCanvasGeometry(int $sourceWidth, int $sourceHeight): array
    {
        $drawWidth = $sourceWidth;
        $drawHeight = $sourceHeight;
        $currentRatio = $sourceHeight > 0 ? ($sourceWidth / $sourceHeight) : self::BOOKSHOP_COVER_TARGET_RATIO;

        if ($currentRatio > self::BOOKSHOP_COVER_TARGET_RATIO) {
            $canvasWidth = $drawWidth;
            if ($canvasWidth % 2 !== 0) {
                $canvasWidth++;
            }
            $canvasHeight = (int) (($canvasWidth / 2) * 3);
        } else {
            $canvasHeight = $drawHeight;
            $heightRemainder = $canvasHeight % 3;
            if ($heightRemainder !== 0) {
                $canvasHeight += 3 - $heightRemainder;
            }
            $canvasWidth = (int) (($canvasHeight / 3) * 2);
        }

        if (
            $canvasWidth > self::BOOKSHOP_COVER_TARGET_MAX_WIDTH
            || $canvasHeight > self::BOOKSHOP_COVER_TARGET_MAX_HEIGHT
        ) {
            $scale = min(
                self::BOOKSHOP_COVER_TARGET_MAX_WIDTH / max(1, $canvasWidth),
                self::BOOKSHOP_COVER_TARGET_MAX_HEIGHT / max(1, $canvasHeight)
            );

            $drawWidth = max(1, (int) round($drawWidth * $scale));
            $drawHeight = max(1, (int) round($drawHeight * $scale));
            $canvasWidth = self::BOOKSHOP_COVER_TARGET_MAX_WIDTH;
            $canvasHeight = self::BOOKSHOP_COVER_TARGET_MAX_HEIGHT;
        }

        return [
            'canvas_width' => $canvasWidth,
            'canvas_height' => $canvasHeight,
            'draw_width' => $drawWidth,
            'draw_height' => $drawHeight,
            'offset_x' => max(0, (int) floor(($canvasWidth - $drawWidth) / 2)),
            'offset_y' => max(0, (int) floor(($canvasHeight - $drawHeight) / 2)),
        ];
    }

    private function saveNormalizedBookshopCoverImage(mixed $image, string $targetPath, string $mimeType): void
    {
        $saved = match ($mimeType) {
            'image/png' => function_exists('imagepng')
                ? @imagepng($image, $targetPath, 6)
                : false,
            'image/webp' => function_exists('imagewebp')
                ? @imagewebp($image, $targetPath, 88)
                : false,
            default => function_exists('imagejpeg')
                ? @imagejpeg($image, $targetPath, 88)
                : false,
        };

        if ($saved === false) {
            throw new \RuntimeException('Falha ao salvar a capa normalizada.');
        }
    }

    private function resolveProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * @return array{directory: string, public_prefix: string}|null
     */
    private function resolveWritableBookshopCoverDestination(): ?array
    {
        $candidates = [
            [
                'directory' => $this->resolveBookshopCoverUploadDirectory(),
                'public_prefix' => $this->resolveBookshopCoverUploadPublicPrefix(),
            ],
            [
                'directory' => $this->resolveBookshopCoverFallbackUploadDirectory(),
                'public_prefix' => $this->resolveBookshopCoverFallbackPublicPrefix(),
            ],
            [
                'directory' => $this->resolveBookshopCoverTemporaryUploadDirectory(),
                'public_prefix' => $this->resolveBookshopCoverFallbackPublicPrefix(),
            ],
        ];

        foreach ($candidates as $candidate) {
            if ($this->ensureWritableDirectory($candidate['directory'])) {
                return $candidate;
            }

            $this->logger->warning('Diretório de capas da livraria indisponível para escrita.', [
                'directory' => $candidate['directory'],
                'public_prefix' => $candidate['public_prefix'],
            ]);
        }

        return null;
    }

    protected function resolveManagedPrivateBookshopCoverAbsolutePath(?string $relativePath): ?string
    {
        $privatePublicPrefix = $this->resolveBookshopCoverFallbackPublicPrefix();
        $normalizedPath = ltrim(trim((string) $relativePath), '/');

        if (
            $normalizedPath === ''
            || $privatePublicPrefix === ''
            || !str_starts_with($normalizedPath, $privatePublicPrefix . '/')
        ) {
            return null;
        }

        foreach ($this->resolveBookshopPrivateCoverDirectories() as $directory) {
            $absolutePath = $this->resolveManagedAbsolutePath($normalizedPath, $privatePublicPrefix, $directory);
            if ($absolutePath !== null && is_file($absolutePath)) {
                return $absolutePath;
            }
        }

        foreach ($this->resolveBookshopPrivateCoverDirectories() as $directory) {
            $absolutePath = $this->resolveManagedAbsolutePath($normalizedPath, $privatePublicPrefix, $directory);
            if ($absolutePath !== null) {
                return $absolutePath;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveBookshopPrivateCoverDirectories(): array
    {
        return [
            $this->resolveBookshopCoverFallbackUploadDirectory(),
            $this->resolveBookshopCoverTemporaryUploadDirectory(),
        ];
    }

    private function ensureWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        if (is_writable($directory)) {
            return true;
        }

        @chmod($directory, 0775);
        clearstatcache(true, $directory);

        return is_writable($directory);
    }

    private function resolveConfiguredUploadDirectory(string $envKey, string $defaultDirectory): string
    {
        $configuredDirectory = trim((string) ($_ENV[$envKey] ?? ''));
        $normalizedDirectory = $configuredDirectory !== ''
            ? $configuredDirectory
            : $defaultDirectory;

        $normalizedDirectory = str_replace('\\', '/', $normalizedDirectory);

        if ($this->isAbsolutePath($normalizedDirectory)) {
            return rtrim($normalizedDirectory, '/');
        }

        return $this->resolveProjectRoot() . '/' . ltrim($normalizedDirectory, '/');
    }

    private function resolveConfiguredUploadPublicPrefix(string $envKey, string $defaultPrefix): string
    {
        $configuredPrefix = trim((string) ($_ENV[$envKey] ?? ''));
        $normalizedPrefix = $configuredPrefix !== ''
            ? $configuredPrefix
            : $defaultPrefix;

        return trim(str_replace('\\', '/', $normalizedPrefix), '/');
    }

    private function resolveManagedAbsolutePath(?string $relativePath, string $publicPrefix, string $directory): ?string
    {
        $normalizedPath = ltrim(trim((string) $relativePath), '/');

        if (
            $normalizedPath === ''
            || $publicPrefix === ''
            || !str_starts_with($normalizedPath, $publicPrefix . '/')
        ) {
            return null;
        }

        $relativeFilePath = ltrim(substr($normalizedPath, strlen($publicPrefix)), '/');
        if (
            $relativeFilePath === ''
            || str_contains($relativeFilePath, '../')
            || str_contains($relativeFilePath, '..\\')
        ) {
            return null;
        }

        return $directory . '/' . $relativeFilePath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
