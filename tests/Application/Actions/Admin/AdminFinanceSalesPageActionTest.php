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

final class TestableAdminFinanceSalesPageAction extends AdminFinanceSalesPageAction
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
        $this->assertSame(
            [
                'context_labels' => [
                    'Busca: allan',
                    'Status: somente concluídas',
                    'Pagamento: PIX',
                    'Vendedor: Maria',
                    'Período da venda: 01/06/2026 a 30/06/2026',
                    'Valor: R$ 40,00 a R$ 60,00',
                ],
                'completed_count' => 1,
                'completed_count_label' => '1 venda concluída',
                'recognized_total_label' => 'R$ 45,00',
            ],
            $action->capturedData['finance_sales_filter_summary']
        );
    }

    public function testExposesFilterSummaryWithoutDateRange(): void
    {
        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-10 13:00:00', 'A', 'cash', 'Maria', 'completed', 20.0, 'Livro A'),
            $this->buildSale(2, 'VD-002', '2026-06-11 13:00:00', 'B', 'pix', 'Maria', 'completed', 90.0, 'Livro B'),
            $this->buildSale(3, 'VD-003', '2026-06-12 13:00:00', 'C', 'pix', 'João', 'cancelled', 50.0, 'Livro C'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'payment_filter' => 'pix',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                'context_labels' => [
                    'Pagamento: PIX',
                ],
                'completed_count' => 1,
                'completed_count_label' => '1 venda concluída',
                'recognized_total_label' => 'R$ 90,00',
            ],
            $action->capturedData['finance_sales_filter_summary']
        );
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
        $this->assertNull($action->capturedData['finance_sales_filter_summary']);
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

    public function testBuildsExportUrlWithCurrentFilters(): void
    {
        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-15 13:00:00', 'Ana', 'pix', 'Maria', 'completed', 45.0, 'Allan Kardec x1'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'q' => 'ana',
            'status_filter' => 'completed',
            'payment_filter' => 'pix',
            'seller_filter' => 'Maria',
            'period_field' => 'sold_at',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'amount_min' => '40',
            'amount_max' => '60',
            'sort' => 'total_amount',
            'dir' => 'asc',
        ]);

        $action($request, new Response());

        $this->assertStringContainsString('/painel/financas?', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('q=ana', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('status_filter=completed', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('payment_filter=pix', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('seller_filter=Maria', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('amount_min=40.00', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('amount_max=60.00', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('sort=total_amount', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('dir=asc', $action->capturedData['finance_sales_export_csv_url']);
        $this->assertStringContainsString('export=csv', $action->capturedData['finance_sales_export_csv_url']);
    }

    public function testExportsFilteredSalesAsCsv(): void
    {
        $sales = [
            $this->buildSale(1, 'VD-001', '2026-06-15 13:00:00', 'Ana', 'pix', 'Maria', 'completed', 45.0, 'Allan Kardec x1'),
            $this->buildSale(2, 'VD-002', '2026-06-18 15:10:00', 'Bruno', 'cash', 'João', 'cancelled', 35.0, 'Livro C x1'),
        ];

        $action = $this->createAction($sales);
        $request = $this->createRequest('GET', '/painel/financas')->withQueryParams([
            'payment_filter' => 'pix',
            'export' => 'csv',
        ]);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="financeiro-livraria-', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('data_venda;codigo_venda;cliente;cpf;telefone;email;itens;quantidade_itens;pagamento;valor_total;valor_recebido;troco;vendedor;status;cancelada_em;cancelada_por', mb_strtolower($body));
        $this->assertStringContainsString('VD-001', $body);
        $this->assertStringContainsString('Ana', $body);
        $this->assertStringContainsString('Allan Kardec x1', $body);
        $this->assertStringContainsString('PIX', $body);
        $this->assertStringContainsString('R$ 45,00', $body);
        $this->assertStringNotContainsString('VD-002', $body);
    }

    /**
     * @param array<int, array<string, mixed>> $sales
     */
    private function createAction(array $sales): TestableAdminFinanceSalesPageAction
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

        return new TestableAdminFinanceSalesPageAction(
            $logger,
            $twig,
            $bookshopRepositoryProphecy->reveal()
        );
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
