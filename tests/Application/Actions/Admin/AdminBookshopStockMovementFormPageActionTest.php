<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminBookshopStockMovementFormPageAction;
use App\Application\Actions\Admin\AdminBookshopStockMovementListPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminBookshopStockMovementFormPageActionTest extends TestCase
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

    public function testPostCreatesStockMovementAndStoresSuccessFlash(): void
    {
        $_SESSION['member_user_id'] = 42;
        $_SESSION['member_name'] = 'Equipe CEDE';

        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'stock_quantity' => 5,
                'stock_lots' => [],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createStockMovement(Argument::that(static function (array $payload): bool {
                return $payload['book_id'] === 10
                    && $payload['movement_type'] === 'entry'
                    && $payload['quantity'] === 3
                    && $payload['stock_lot_id'] === 0
                    && $payload['unit_cost'] === '12.50'
                    && $payload['sale_price'] === '25.90'
                    && $payload['notes'] === 'Entrada de reposição'
                    && $payload['occurred_at'] === '2026-04-29 13:30:00'
                    && $payload['created_by_member_id'] === 42
                    && $payload['created_by_name'] === 'Equipe CEDE';
            }))
            ->willReturn(77)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findBookByIdForAdmin(10)
            ->willReturn([
                'title' => 'Livro de Teste',
                'stock_quantity' => 8,
            ])
            ->shouldBeCalledOnce();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/movimentacoes/nova?mode=entry')
            ->withParsedBody([
                'mode' => 'entry',
                'occurred_at' => '2026-04-29T10:30',
                'book_id' => '10',
                'movement_type' => 'entry',
                'quantity' => '3',
                'unit_cost' => '12,50',
                'sale_price' => '25,90',
                'notes' => 'Entrada de reposição',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/livraria/movimentacoes', $response->getHeaderLine('Location'));
        $this->assertSame(
            [
                'status' => 'created',
                'movement_id' => 77,
                'book_title' => 'Livro de Teste',
                'stock_quantity' => 8,
            ],
            $_SESSION['_codex_flash'][AdminBookshopStockMovementListPageAction::FLASH_KEY] ?? null
        );
    }

    public function testPostWithInvalidPayloadRedirectsBackWithErrorsInFlash(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn([])
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createStockMovement(Argument::any())
            ->shouldNotBeCalled();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/movimentacoes/nova?mode=entry')
            ->withParsedBody([
                'mode' => 'entry',
                'occurred_at' => 'data-invalida',
                'book_id' => '0',
                'movement_type' => 'entry',
                'quantity' => '0',
                'unit_cost' => '0',
                'sale_price' => '0',
                'notes' => 'Tentativa inválida',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame(
            '/painel/livraria/movimentacoes/nova?mode=entry',
            $response->getHeaderLine('Location')
        );

        $flash = $_SESSION['_codex_flash']['admin_bookshop_stock_movement_form'] ?? null;

        $this->assertIsArray($flash);
        $this->assertSame('entry', $flash['payload']['mode'] ?? null);
        $this->assertSame('entry', $flash['payload']['movement_type'] ?? null);
        $this->assertContains('Informe uma data e hora válidas para a movimentação.', $flash['errors'] ?? []);
        $this->assertContains('Selecione um livro do acervo.', $flash['errors'] ?? []);
        $this->assertContains('Informe uma quantidade maior do que zero.', $flash['errors'] ?? []);
    }

    public function testPostWhenCreateStockMovementFailsRedirectsBackWithExceptionMessage(): void
    {
        $_SESSION['member_user_id'] = 42;
        $_SESSION['member_name'] = 'Equipe CEDE';

        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'stock_quantity' => 5,
                'stock_lots' => [],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createStockMovement(Argument::type('array'))
            ->willThrow(new \RuntimeException('Falha ao persistir movimentacao.'))
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findBookByIdForAdmin(Argument::cetera())
            ->shouldNotBeCalled();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/movimentacoes/nova?mode=entry')
            ->withParsedBody([
                'mode' => 'entry',
                'occurred_at' => '2026-04-29T10:30',
                'book_id' => '10',
                'movement_type' => 'entry',
                'quantity' => '3',
                'unit_cost' => '12,50',
                'sale_price' => '25,90',
                'notes' => 'Entrada de reposição',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame(
            '/painel/livraria/movimentacoes/nova?mode=entry',
            $response->getHeaderLine('Location')
        );

        $flash = $_SESSION['_codex_flash']['admin_bookshop_stock_movement_form'] ?? null;

        $this->assertIsArray($flash);
        $this->assertSame('entry', $flash['payload']['mode'] ?? null);
        $this->assertContains('Falha ao persistir movimentacao.', $flash['errors'] ?? []);
    }

    private function createAction(BookshopRepository $bookshopRepository): AdminBookshopStockMovementFormPageAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new AdminBookshopStockMovementFormPageAction($logger, $twig, $bookshopRepository);
    }
}
