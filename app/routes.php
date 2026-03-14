<?php

declare(strict_types=1);

use App\Application\Actions\Page\AboutPageAction;
use App\Application\Actions\Page\AboutMissionPageAction;
use App\Application\Actions\Page\AboutValuesPageAction;
use App\Application\Actions\Page\AboutHistoryPageAction;
use App\Application\Actions\Page\AboutBrandPageAction;
use App\Application\Actions\Page\AgendaPageAction;
use App\Application\Actions\Page\AgendaGospelStudyPageAction;
use App\Application\Actions\Page\AgendaPublicLecturePageAction;
use App\Application\Actions\Page\AgendaYouthPageAction;
use App\Application\Actions\Page\ContactPageAction;
use App\Application\Actions\Page\EsdePageAction;
use App\Application\Actions\Page\FaqPageAction;
use App\Application\Actions\Page\FaqDoctrinePageAction;
use App\Application\Actions\Page\FaqParticipationPageAction;
use App\Application\Actions\Page\FaqPracticesPageAction;
use App\Application\Actions\Page\FraternalServicePageAction;
use App\Application\Actions\Page\HomePageAction;
use App\Application\Actions\Page\PublicLecturesPageAction;
use App\Application\Actions\Page\StudiesPageAction;
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

    $app->get('/', HomePageAction::class);
    $app->get('/quem-somos', AboutPageAction::class);
    $app->get('/quem-somos/missao', AboutMissionPageAction::class);
    $app->get('/quem-somos/valores', AboutValuesPageAction::class);
    $app->get('/quem-somos/historia', AboutHistoryPageAction::class);
    $app->get('/quem-somos/nossa-marca', AboutBrandPageAction::class);
    $app->get('/estudos', StudiesPageAction::class);
    $app->get('/estudos/esde', EsdePageAction::class);
    $app->get('/estudos/palestras', PublicLecturesPageAction::class);
    $app->get('/estudos/atendimento-fraterno', FraternalServicePageAction::class);
    $app->get('/agenda', AgendaPageAction::class);
    $app->get('/agenda/estudo-do-evangelho', AgendaGospelStudyPageAction::class);
    $app->get('/agenda/palestra-publica', AgendaPublicLecturePageAction::class);
    $app->get('/agenda/juventude-espirita', AgendaYouthPageAction::class);
    $app->get('/faq', FaqPageAction::class);
    $app->get('/faq/doutrina', FaqDoctrinePageAction::class);
    $app->get('/faq/participacao', FaqParticipationPageAction::class);
    $app->get('/faq/praticas', FaqPracticesPageAction::class);
    $app->get('/contato', ContactPageAction::class);

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
            ['template' => 'pages/about.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/about-detail.twig', 'context' => ['homeContent' => $homeContent, 'about' => $homeContent['aboutPages']['missao'] ?? []]],
            ['template' => 'pages/about-brand.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/studies.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/study-detail.twig', 'context' => ['homeContent' => $homeContent, 'study' => $homeContent['studiesPages']['esde'] ?? []]],
            ['template' => 'pages/agenda.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/agenda-detail.twig', 'context' => ['homeContent' => $homeContent, 'agenda' => $homeContent['agendaPages']['estudo-do-evangelho'] ?? []]],
            ['template' => 'pages/faq.twig', 'context' => ['homeContent' => $homeContent]],
            ['template' => 'pages/faq-category.twig', 'context' => ['homeContent' => $homeContent, 'faq_category_slug' => 'doutrina']],
            ['template' => 'pages/contact.twig', 'context' => ['homeContent' => $homeContent]],
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
