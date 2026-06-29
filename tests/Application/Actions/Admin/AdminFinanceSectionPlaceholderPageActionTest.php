<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminFinanceSectionPlaceholderPageAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminFinanceSectionPlaceholderPageActionTest extends TestCase
{
    public function testRendersPlaceholderForCantina(): void
    {
        $action = $this->createAction();
        $request = $this->createRequest('GET', '/painel/financas/cantina')
            ->withAttribute('section', 'cantina');

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-finance-section-placeholder.twig', $action->capturedTemplate);
        $this->assertSame('cantina', $action->capturedData['finance_section_key']);
        $this->assertSame('Cantina', $action->capturedData['finance_section_label']);
        $this->assertSame('Cantina | Gestão Financeira | CEDE', $action->capturedData['page_title']);
        $this->assertSame('https://cedern.org/painel/financas/cantina', $action->capturedData['page_url']);
    }

    public function testRedirectsUnknownSectionToFinanceDashboard(): void
    {
        $action = $this->createAction();
        $request = $this->createRequest('GET', '/painel/financas/estoque')
            ->withAttribute('section', 'estoque');

        $response = $action($request, new Response());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/painel/financas', $response->getHeaderLine('Location'));
    }

    private function createAction(): AdminFinanceSectionPlaceholderPageAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new class ($logger, $twig) extends AdminFinanceSectionPlaceholderPageAction {
            public string $capturedTemplate = '';

            /** @var array<string, mixed> */
            public array $capturedData = [];

            protected function renderPage(
                ResponseInterface $response,
                string $template,
                array $data = []
            ): ResponseInterface {
                $this->capturedTemplate = $template;
                $this->capturedData = $data;

                return $response;
            }
        };
    }
}
