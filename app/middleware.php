<?php

declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Views\Twig;

return function (App $app) {
    $app->add(function (Request $request, RequestHandler $handler) use ($app) {
        $twig = $app->getContainer()->get(Twig::class);
        $twig->getEnvironment()->addGlobal('current_path', $request->getUri()->getPath());

        return $handler->handle($request);
    });

    $app->add(SessionMiddleware::class);
};
