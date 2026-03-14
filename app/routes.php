<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Domain\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Views\Twig;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);
        $homeContent = require __DIR__ . '/content/home.php';

        return $twig->render($response, 'home.twig', [
            'homeContent' => $homeContent,
        ]);
    });

    $app->get('/users', function (Request $request, Response $response) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);
        $repository = $app->getContainer()->get(UserRepository::class);
        $users = array_map(
            static fn ($user): array => $user->jsonSerialize(),
            $repository->findAll()
        );

        return $twig->render($response, 'users.twig', ['users' => $users]);
    });

    $app->get('/health/render', function (Request $request, Response $response) use ($app) {
        $twigView = $app->getContainer()->get(Twig::class);
        $twig = $twigView->getEnvironment();
        $homeContent = require __DIR__ . '/content/home.php';

        $checks = [
            ['template' => 'components/header.twig', 'context' => []],
            ['template' => 'home/hero.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/features.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/social-proof.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/roadmap.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/faq.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'home/final-cta.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'components/theme-palette.twig', 'context' => []],
            ['template' => 'components/footer.twig', 'context' => []],
            ['template' => 'home.twig', 'context' => ['homeContent' => $homeContent]],
        ];

        $results = [];

        foreach ($checks as $check) {
            $template = $check['template'];
            $context = $check['context'];

            try {
                $html = $twig->render($template, $context);
                $results[] = [
                    'template' => $template,
                    'ok' => true,
                    'length' => strlen($html),
                ];
            } catch (\Throwable $exception) {
                $results[] = [
                    'template' => $template,
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $payload = [
            'status' => 'ok',
            'php' => PHP_VERSION,
            'results' => $results,
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/api/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });
};
