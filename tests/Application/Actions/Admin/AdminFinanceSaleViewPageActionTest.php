<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminFinanceSaleViewPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAdminFinanceSaleViewPageAction extends AdminFinanceSaleViewPageAction
{
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
}

class AdminFinanceSaleViewPageActionTest extends TestCase
{
    public function testRendersSaleUsingFinanceContext(): void
    {
        $sale = [
            'id' => 12,
            'sale_code' => 'VD-000012',
            'status' => 'completed',
            'items' => [],
        ];

        $action = $this->createAction($sale);
        $request = $this->createRequest('GET', '/painel/financas/vendas/12')->withAttribute('id', 12);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-bookshop-sale-view.twig', $action->capturedTemplate);
        $this->assertSame($sale, $action->capturedData['bookshop_sale']);
        $this->assertSame('/painel/financas', $action->capturedData['bookshop_sale_list_url']);
        $this->assertFalse($action->capturedData['bookshop_sale_show_inventory_link']);
        $this->assertFalse($action->capturedData['bookshop_sale_show_cancel_button']);
        $this->assertFalse($action->capturedData['bookshop_sale_show_create_button']);
        $this->assertSame('https://cedern.org/painel/financas/vendas/12', $action->capturedData['page_url']);
    }

    public function testRedirectsToFinanceListWhenSaleDoesNotExist(): void
    {
        $action = $this->createAction(null);
        $request = $this->createRequest('GET', '/painel/financas/vendas/999')->withAttribute('id', 999);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/financas', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, mixed>|null $sale
     */
    private function createAction(?array $sale): TestableAdminFinanceSaleViewPageAction
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findSaleByIdForAdmin(12)
            ->willReturn($sale);
        $bookshopRepositoryProphecy
            ->findSaleByIdForAdmin(999)
            ->willReturn($sale);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new TestableAdminFinanceSaleViewPageAction(
            $logger,
            $twig,
            $bookshopRepositoryProphecy->reveal()
        );
    }
}
