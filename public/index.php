<?php

declare(strict_types=1);

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use App\Support\ManagedPublicMediaPath;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Views\Twig;
use Twig\TwigFunction;

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$projectRoot = dirname(__DIR__);

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
    $resolvedEnvFilePath = str_starts_with($envFileFromServer, '/')
        ? $envFileFromServer
        : $projectRoot . '/' . ltrim($envFileFromServer, '/');

    if (is_file($resolvedEnvFilePath)) {
        Dotenv::createImmutable(dirname($resolvedEnvFilePath), basename($resolvedEnvFilePath))->safeLoad();
        $dotenvLoaded = true;
    } else {
        error_log('[cedern bootstrap] APP_ENV_FILE not found: ' . $resolvedEnvFilePath);
    }
}

if (!$dotenvLoaded && is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

$appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
$enableContainerCompilation = !in_array($appEnv, ['dev', 'development', 'local', 'test'], true);

if ($enableContainerCompilation) {
    $appAssetVersion = trim((string) ($_ENV['APP_ASSET_VERSION'] ?? '1'));
    if ($appAssetVersion === '') {
        $appAssetVersion = '1';
    }

    $cacheVersionSuffix = preg_replace('/[^a-zA-Z0-9._-]/', '-', $appAssetVersion) ?? '';
    if ($cacheVersionSuffix === '') {
        $cacheVersionSuffix = '1';
    }

    $cacheDirectory = $projectRoot . '/var/cache/container-v' . $cacheVersionSuffix;
    $cacheDirectoryReady = is_dir($cacheDirectory) || @mkdir($cacheDirectory, 0775, true);

    if ($cacheDirectoryReady && is_writable($cacheDirectory)) {
        $containerBuilder->enableCompilation($cacheDirectory);
    } else {
        error_log(
            '[cedern bootstrap] Container compilation disabled: cache directory is not writable: '
            . $cacheDirectory
        );
    }
}

// Set up settings
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up repositories
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

$normalizeBasePath = static function (string $rawBasePath): string {
    $trimmed = trim($rawBasePath);

    if ($trimmed === '' || $trimmed === '/') {
        return '';
    }

    return '/' . trim($trimmed, '/');
};

$detectAppBasePath = static function () use ($normalizeBasePath): string {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));

    if ($scriptDirectory === '' || $scriptDirectory === '.' || $scriptDirectory === '/') {
        return '';
    }

    $scriptDirectory = rtrim($scriptDirectory, '/');

    // Root installs commonly execute through /public/index.php after rewrite.
    if ($scriptDirectory === '/public') {
        return '';
    }

    if (str_ends_with($scriptDirectory, '/public')) {
        $scriptDirectory = substr($scriptDirectory, 0, -strlen('/public')) ?: '';
    }

    return $normalizeBasePath($scriptDirectory);
};

$appBaseEnv = getenv('APP_BASE');
$appBaseRaw = trim((string) ($appBaseEnv !== false ? $appBaseEnv : ($_ENV['APP_BASE'] ?? '')));
$configuredAppBasePath = $normalizeBasePath($appBaseRaw);

if ($configuredAppBasePath === '') {
    $detectedAppBasePath = $detectAppBasePath();

    if ($detectedAppBasePath !== '') {
        $configuredAppBasePath = $detectedAppBasePath;
        $_ENV['APP_BASE'] = $detectedAppBasePath;
        putenv('APP_BASE=' . $detectedAppBasePath);
    }
}

$requestUriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if (!is_string($requestUriPath) || $requestUriPath === '') {
    $requestUriPath = '/';
}

// Safety fallback: if APP_BASE does not match the current request path, run at root.
$appBasePath = $configuredAppBasePath === ''
    || $requestUriPath === $configuredAppBasePath
    || str_starts_with($requestUriPath, $configuredAppBasePath . '/')
    ? $configuredAppBasePath
    : '';

if ($appBasePath !== '') {
    $app->setBasePath($appBasePath);
}

$callableResolver = $app->getCallableResolver();

// Register middleware
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

$routePatternToRegex = static function (string $pattern): string {
    $trimmedPattern = trim($pattern, '/');

    if ($trimmedPattern === '') {
        return '#^/$#';
    }

    $segments = array_values(array_filter(
        explode('/', $trimmedPattern),
        static fn (string $segment): bool => $segment !== ''
    ));
    $regexSegments = [];

    foreach ($segments as $segment) {
        if (preg_match('/^\{([^}:]+)(?::(.+))?\}$/', $segment, $matches) === 1) {
            $regexSegments[] = $matches[2] ?? '[^/]+';
            continue;
        }

        $regexSegments[] = preg_quote($segment, '#');
    }

    return '#^/' . implode('/', $regexSegments) . '$#';
};

$breadcrumbLinkPaths = [];
$breadcrumbLinkPatterns = [];

foreach ($app->getRouteCollector()->getRoutes() as $route) {
    if (!in_array('GET', $route->getMethods(), true)) {
        continue;
    }

    $pattern = $route->getPattern();

    if (preg_match('/\{[^}]+\}/', $pattern) === 1) {
        $breadcrumbLinkPatterns[] = $routePatternToRegex($pattern);
        continue;
    }

    $breadcrumbLinkPaths[$pattern] = true;
}

/** @var Twig $twig */
$twig = $container->get(Twig::class);
$twigEnvironment = $twig->getEnvironment();
$registerTwigFunction = static function (\Twig\Environment $environment, TwigFunction $function): void {
    try {
        $environment->addFunction($function);
    } catch (\LogicException $exception) {
        if (str_contains($exception->getMessage(), 'already registered')) {
            return;
        }

        throw $exception;
    }
};

$breadcrumbLinkPatterns = array_values(array_unique($breadcrumbLinkPatterns));
$registerTwigFunction($twigEnvironment, new TwigFunction(
    'member_profile_photo_url',
    static function (?string $rawPath, string $baseUrl = ''): string {
        return ManagedPublicMediaPath::toUrl($rawPath, 'media/membros/fotos', $baseUrl);
    }
));

$registerTwigFunction($twigEnvironment, new TwigFunction(
    'is_breadcrumb_linkable',
    static function (string $path) use ($breadcrumbLinkPaths, $breadcrumbLinkPatterns): bool {
        if (isset($breadcrumbLinkPaths[$path])) {
            return true;
        }

        foreach ($breadcrumbLinkPatterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
));

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

$displayErrorDetails = $settings->get('displayErrorDetails');
$logError = $settings->get('logError');
$logErrorDetails = $settings->get('logErrorDetails');

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler(
    $callableResolver,
    $responseFactory,
    $container->get(LoggerInterface::class)
);

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
