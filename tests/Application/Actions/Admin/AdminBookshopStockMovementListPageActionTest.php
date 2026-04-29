<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminBookshopStockMovementListPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminBookshopStockMovementListPageActionTest extends TestCase
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

    public function testAppliesTypeFilterAndSearchTerm(): void
    {
        $movements = [
            $this->buildMovement(1, 'entry', 'Livro Allan', 'MV-001', 3, '2026-04-20 10:00:00'),
            $this->buildMovement(2, 'entry', 'Livro Outro', 'MV-002', 2, '2026-04-21 10:00:00'),
            $this->buildMovement(3, 'donation', 'Livro Allan', 'MV-003', 1, '2026-04-22 10:00:00'),
        ];

        $action = $this->createAction($movements);
        $request = $this->createRequest('GET', '/painel/livraria/movimentacoes')->withQueryParams([
            'type_filter' => 'entry',
            'q' => 'allan',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-bookshop-stock-movements.twig', $action->capturedTemplate);
        $this->assertCount(1, $action->capturedData['bookshop_stock_movements']);
        $this->assertSame(1, $action->capturedData['bookshop_stock_movements'][0]['id']);
        $this->assertSame(
            'entry',
            $action->capturedData['bookshop_stock_movements_filters']['type_filter']
        );
        $this->assertSame('allan', $action->capturedData['bookshop_stock_movements_search']);
    }

    public function testSortsByStockDeltaAndPaginatesSecondPage(): void
    {
        $movements = [
            $this->buildMovement(1, 'entry', 'Livro 1', 'MV-001', 2, '2026-04-20 10:00:00'),
            $this->buildMovement(2, 'entry', 'Livro 2', 'MV-002', 8, '2026-04-21 10:00:00'),
            $this->buildMovement(3, 'entry', 'Livro 3', 'MV-003', 3, '2026-04-22 10:00:00'),
            $this->buildMovement(4, 'entry', 'Livro 4', 'MV-004', 7, '2026-04-23 10:00:00'),
            $this->buildMovement(5, 'entry', 'Livro 5', 'MV-005', 5, '2026-04-24 10:00:00'),
            $this->buildMovement(6, 'entry', 'Livro 6', 'MV-006', 1, '2026-04-25 10:00:00'),
        ];

        $action = $this->createAction($movements);
        $request = $this->createRequest('GET', '/painel/livraria/movimentacoes')->withQueryParams([
            'sort' => 'stock_delta',
            'dir' => 'desc',
            'per_page' => '5',
            'page' => '2',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $action->capturedData['bookshop_stock_movements']);
        $this->assertSame(1, $action->capturedData['bookshop_stock_movements'][0]['stock_delta']);

        $pagination = $action->capturedData['bookshop_stock_movements_pagination'];
        $this->assertSame(2, $pagination['current_page']);
        $this->assertSame(2, $pagination['total_pages']);
        $this->assertSame(6, $pagination['total_items']);
        $this->assertSame(6, $pagination['start_item']);
        $this->assertSame(6, $pagination['end_item']);
        $this->assertSame('5', $pagination['page_size']);
    }

    public function testConsumesFlashFeedbackAndSupportsAllPageSize(): void
    {
        $_SESSION['_codex_flash'][AdminBookshopStockMovementListPageAction::FLASH_KEY] = [
            'status' => 'created',
            'movement_id' => 77,
            'book_title' => 'Livro de Teste',
            'stock_quantity' => 9,
        ];

        $movements = [
            $this->buildMovement(1, 'entry', 'Livro 1', 'MV-001', 2, '2026-04-20 10:00:00'),
            $this->buildMovement(2, 'donation', 'Livro 2', 'MV-002', 1, '2026-04-21 10:00:00'),
        ];

        $action = $this->createAction($movements);
        $request = $this->createRequest('GET', '/painel/livraria/movimentacoes')->withQueryParams([
            'per_page' => 'all',
        ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $action->capturedData['bookshop_stock_movements']);
        $this->assertSame('created', $action->capturedData['admin_status']);
        $this->assertSame(
            [
                'movement_id' => 77,
                'book_title' => 'Livro de Teste',
                'stock_quantity' => 9,
            ],
            $action->capturedData['admin_stock_movement_feedback']
        );
        $this->assertSame('all', $action->capturedData['bookshop_stock_movements_pagination']['page_size']);
    }

    /**
     * @param array<int, array<string, mixed>> $movements
     */
    private function createAction(array $movements): AdminBookshopStockMovementListPageAction
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllStockMovementsForAdmin()
            ->willReturn($movements)
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
        ) extends AdminBookshopStockMovementListPageAction {
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

    private function buildMovement(
        int $id,
        string $movementType,
        string $title,
        string $movementCode,
        int $stockDelta,
        string $occurredAt
    ): array {
        return [
            'id' => $id,
            'movement_type' => $movementType,
            'movement_type_label' => strtoupper($movementType),
            'movement_code' => $movementCode,
            'stock_lot_code_snapshot' => 'L-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'title_snapshot' => $title,
            'author_snapshot' => 'Autor',
            'sku_snapshot' => 'SKU-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'notes' => 'Observacao',
            'created_by_name' => 'Equipe',
            'stock_delta' => $stockDelta,
            'occurred_at' => $occurredAt,
            'occurred_at_label' => $occurredAt,
        ];
    }
}
