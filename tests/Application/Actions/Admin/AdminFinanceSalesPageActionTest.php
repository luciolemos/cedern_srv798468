<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminFinanceSalesPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminFinanceSalesPageActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAppliesCombinedFiltersAndExposesSummaryMetrics(): void
    {
        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-15 13:00:00', 'Ana', 'pix', 'Maria', 'completed', 45.0, 'Allan Kardec x1'),
            $this->buildSale(2, 'VD-002', '2026-07-05 13:00:00', 'Bruno', 'pix', 'Maria', 'completed', 80.0, 'Coleção infantil x2'),
            $this->buildSale(3, 'VD-003', '2026-06-18 13:00:00', 'Carla', 'pix', 'Maria', 'cancelled', 50.0, 'Allan Kardec x1'),
            $this->buildSale(4, 'VD-004', '2026-06-20 13:00:00', 'Diego', 'cash', 'João', 'completed', 50.0, 'Revista espírita x1'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'q' => 'allan',
            'status_filter' => 'completed',
            'payment_filter' => 'pix',
            'seller_filter' => 'Maria',
            'period_field' => 'sold_at',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'amount_min' => '40',
            'amount_max' => '60',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-finance-sales.twig', $action->capturedTemplate);
        $this->assertCount(1, $action->capturedData['finance_sales']);
        $this->assertSame(1, $action->capturedData['finance_sales'][0]['id']);
        $this->assertSame('Maria', $action->capturedData['finance_sales_filters']['seller_filter']);
        $this->assertSame('pix', $action->capturedData['finance_sales_filters']['payment_filter']);
        $this->assertSame('allan', $action->capturedData['finance_sales_search']);
        $this->assertSame(1, $action->capturedData['finance_sales_summary']['completed_count']);
        $this->assertSame('R$ 45,00', $action->capturedData['finance_sales_summary']['completed_total_label']);
    }

    public function testSortsByTotalAmountAndPaginatesSecondPage(): void
    {
        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-10 13:00:00', 'A', 'cash', 'Maria', 'completed', 20.0, 'Livro A'),
            $this->buildSale(2, 'VD-002', '2026-06-11 13:00:00', 'B', 'cash', 'Maria', 'completed', 90.0, 'Livro B'),
            $this->buildSale(3, 'VD-003', '2026-06-12 13:00:00', 'C', 'cash', 'Maria', 'completed', 50.0, 'Livro C'),
            $this->buildSale(4, 'VD-004', '2026-06-13 13:00:00', 'D', 'cash', 'Maria', 'completed', 70.0, 'Livro D'),
            $this->buildSale(5, 'VD-005', '2026-06-14 13:00:00', 'E', 'cash', 'Maria', 'completed', 40.0, 'Livro E'),
            $this->buildSale(6, 'VD-006', '2026-06-15 13:00:00', 'F', 'cash', 'Maria', 'completed', 10.0, 'Livro F'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'sort' => 'total_amount',
            'dir' => 'desc',
            'per_page' => '5',
            'page' => '2',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $action->capturedData['finance_sales']);
        $this->assertSame(10.0, $action->capturedData['finance_sales'][0]['total_amount']);

        $pagination = $action->capturedData['finance_sales_pagination'];
        $this->assertSame(2, $pagination['current_page']);
        $this->assertSame(2, $pagination['total_pages']);
        $this->assertSame(6, $pagination['total_items']);
        $this->assertSame(6, $pagination['start_item']);
        $this->assertSame(6, $pagination['end_item']);
        $this->assertSame('5', $pagination['page_size']);
    }

    public function testConsumesFlashAndSupportsAllPageSize(): void
    {
        $_SESSION['_codex_flash'][AdminFinanceSalesPageAction::FLASH_KEY] = [
            'status' => 'not-found',
        ];

        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-10 13:00:00', 'A', 'cash', 'Maria', 'completed', 20.0, 'Livro A'),
            $this->buildSale(2, 'VD-002', '2026-06-11 13:00:00', 'B', 'pix', 'Maria', 'cancelled', 15.0, 'Livro B'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'per_page' => 'all',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('not-found', $action->capturedData['admin_status']);
        $this->assertCount(2, $action->capturedData['finance_sales']);
        $this->assertSame('all', $action->capturedData['finance_sales_pagination']['page_size']);
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     */
    private function createAction(array $sales): AdminFinanceSalesPageAction
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllSalesForAdmin()
            ->willReturn($sales)
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new class (
            $logger,
            $twig,
            $bookshopRepositoryProphecy->reveal()
        ) extends AdminFinanceSalesPageAction {
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

    private function buildSale(
        int $id,
        string $saleCode,
        string $soldAt,
        string $customerName,
        string $paymentMethod,
        string $sellerName,
        string $status,
        float $totalAmount,
        string $itemsSummary
    ): array {
        return [
            'id' => $id,
            'sale_code' => $saleCode,
            'sold_at' => $soldAt,
            'sold_at_label' => $soldAt,
            'customer_name' => $customerName,
            'customer_name_display' => $customerName,
            'customer_phone_display' => '',
            'customer_email' => '',
            'customer_cpf_display' => '',
            'payment_method' => $paymentMethod,
            'payment_method_label' => strtoupper($paymentMethod),
            'created_by_name' => $sellerName,
            'cancelled_by_name' => $status === 'cancelled' ? 'Equipe Financeira' : '',
            'status' => $status,
            'status_label' => strtoupper($status),
            'item_count' => 1,
            'items_summary' => $itemsSummary,
            'items_summary_short' => $itemsSummary,
            'subtotal_amount' => $totalAmount,
            'subtotal_amount_label' => 'R$ ' . number_format($totalAmount, 2, ',', '.'),
            'discount_amount' => 0.0,
            'discount_amount_label' => 'R$ 0,00',
            'total_amount' => $totalAmount,
            'total_amount_label' => 'R$ ' . number_format($totalAmount, 2, ',', '.'),
            'received_amount_label' => '',
            'change_amount_label' => '',
            'cancelled_at' => $status === 'cancelled' ? '2026-06-20 15:00:00' : null,
            'cancelled_at_label' => $status === 'cancelled' ? '2026-06-20 15:00:00' : '',
        ];
    }
}
