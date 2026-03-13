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
        
        $contentPath = __DIR__ . '/content/home.php';
        $homeContent = [];

        if (file_exists($contentPath)) {
            $loaded = require $contentPath;
            if (is_array($loaded)) {
                $homeContent = $loaded;
            } else {
                // If require returns 1 or true, it implies the file was found but didn't return data
                $homeContent = ['_debug_error' => 'Arquivo encontrado mas sem retorno de dados'];
            }
        } else {
            $homeContent = ['_debug_error' => 'Arquivo de conteudo nao encontrado: ' . $contentPath];
        }

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

    $app->group('/api/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });
};
