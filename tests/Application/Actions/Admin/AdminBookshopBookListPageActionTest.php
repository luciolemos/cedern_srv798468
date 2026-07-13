<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminBookshopBookListPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAdminBookshopBookListPageAction extends AdminBookshopBookListPageAction
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

class AdminBookshopBookListPageActionTest extends TestCase
{
    public function testAppliesShelfAndLevelFilters(): void
    {
        $books = [
            $this->buildBook(1, 'A', 'B-3', 4),
            $this->buildBook(2, 'B', 'B-2', 3),
            $this->buildBook(3, 'C', 'C-3', 2),
        ];

        $action = $this->createAction($books, [], []);
        $request = $this->createRequest('GET', '/painel/livraria/acervo')
            ->withQueryParams([
                'shelf_filter' => 'b',
                'level_filter' => '3',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-bookshop-books.twig', $action->capturedTemplate);
        $this->assertCount(1, $action->capturedData['bookshop_books']);
        $this->assertSame(1, $action->capturedData['bookshop_books'][0]['id']);
        $this->assertSame(
            'B',
            $action->capturedData['bookshop_books_filters']['shelf_filter']
        );
        $this->assertSame(
            '3',
            $action->capturedData['bookshop_books_filters']['level_filter']
        );
    }

    public function testSortsByStockQuantityAndPaginatesSecondPage(): void
    {
        $books = [
            $this->buildBook(1, 'Livro 1', 'A-1', 1),
            $this->buildBook(2, 'Livro 2', 'A-2', 9),
            $this->buildBook(3, 'Livro 3', 'A-3', 3),
            $this->buildBook(4, 'Livro 4', 'A-4', 6),
            $this->buildBook(5, 'Livro 5', 'A-5', 4),
            $this->buildBook(6, 'Livro 6', 'A-6', 2),
        ];

        $action = $this->createAction($books, [], []);
        $request = $this->createRequest('GET', '/painel/livraria/acervo')
            ->withQueryParams([
                'sort' => 'stock_quantity',
                'dir' => 'desc',
                'per_page' => '5',
                'page' => '2',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $action->capturedData['bookshop_books']);
        $this->assertSame(1, $action->capturedData['bookshop_books'][0]['stock_quantity']);

        $pagination = $action->capturedData['bookshop_books_pagination'];
        $this->assertSame(2, $pagination['current_page']);
        $this->assertSame(2, $pagination['total_pages']);
        $this->assertSame(6, $pagination['total_items']);
        $this->assertSame(6, $pagination['start_item']);
        $this->assertSame(6, $pagination['end_item']);
        $this->assertSame('5', $pagination['page_size']);
    }

    public function testBuildsExportUrlWithoutPerPageAndWithActiveFilters(): void
    {
        $books = [
            $this->buildBook(1, 'Livro A', 'A-1', 2, 'active', 'ok', 'Filosofia', 'Doutrina'),
            $this->buildBook(2, 'Livro B', 'A-2', 1, 'inactive', 'out', 'Romance', 'Outros'),
        ];
        $genres = [['name' => 'Filosofia'], ['name' => 'Romance']];
        $categories = [['name' => 'Doutrina'], ['name' => 'Outros']];

        $action = $this->createAction($books, $categories, $genres);
        $request = $this->createRequest('GET', '/painel/livraria/acervo')
            ->withQueryParams([
                'q' => 'Livro',
                'status_filter' => 'active',
                'stock_filter' => 'ok',
                'genre_filter' => 'Filosofia',
                'category_filter' => 'Doutrina',
                'per_page' => '5',
            ]);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $action->capturedData['bookshop_books']);

        $exportUrl = (string) $action->capturedData['bookshop_books_export_url'];
        $this->assertStringStartsWith('/painel/livraria/acervo/exportar?', $exportUrl);
        $this->assertStringNotContainsString('per_page=', $exportUrl);
        $this->assertStringContainsString('status_filter=active', $exportUrl);
        $this->assertStringContainsString('stock_filter=ok', $exportUrl);
        $this->assertStringContainsString('genre_filter=Filosofia', $exportUrl);
        $this->assertStringContainsString('category_filter=Doutrina', $exportUrl);
    }

    public function testRendersPdfActionLinkForEachBookRow(): void
    {
        $books = [
            $this->buildBook(1, 'Livro A', 'A-1', 2),
        ];

        $action = $this->createAction($books, [], []);
        $request = $this->createRequest('GET', '/painel/livraria/acervo');

        $response = $action($request, new Response());

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $html = $twig->fetch($action->capturedTemplate, array_merge($action->capturedData, [
            'base_url' => '',
            'current_path' => '/painel/livraria/acervo',
            'csrf_token' => 'test-token',
            'csrf_field_name' => '_csrf',
            'dashboard_user' => 'Administrador de Teste',
            'dashboard_user_photo_path' => '',
            'dashboard_is_authenticated' => true,
            'dashboard_is_admin_session' => true,
            'dashboard_env_label' => 'Homologação',
            'dashboard_env_tone' => 'test',
            'dashboard_admin_notifications' => [],
            'dashboard_admin_pending_users' => [],
            'dashboard_admin_notification_count' => 0,
            'member_is_authenticated' => true,
            'member_name' => 'Administrador de Teste',
            'member_role_key' => 'admin',
            'member_role_name' => 'Administrador',
            'member_profile_photo_path' => '',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/painel/livraria/acervo/1/pdf', $html);
        $this->assertStringContainsString('Abrir ficha para impressão', $html);
    }

    /**
     * @param array<int, array<string, mixed>> $books
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, mixed>> $genres
     */
    private function createAction(
        array $books,
        array $categories,
        array $genres
    ): TestableAdminBookshopBookListPageAction {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findAllCategoriesForAdmin()
            ->willReturn($categories)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findAllGenresForAdmin()
            ->willReturn($genres)
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new TestableAdminBookshopBookListPageAction(
            $logger,
            $twig,
            $bookshopRepositoryProphecy->reveal()
        );
    }

    private function buildBook(
        int $id,
        string $title,
        string $location,
        int $stockQuantity,
        string $status = 'active',
        string $stockState = 'ok',
        string $genreName = '',
        string $categoryName = ''
    ): array {
        return [
            'id' => $id,
            'sku' => 'SKU-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'title' => $title,
            'subtitle' => '',
            'author_name' => 'Autor',
            'publisher_name' => 'Editora',
            'isbn' => '978000000000' . $id,
            'barcode' => '789000000000' . $id,
            'category_name' => $categoryName,
            'genre_name' => $genreName,
            'collection_name' => '',
            'volume_number' => '',
            'volume_label' => '',
            'location_label' => $location,
            'stock_quantity' => $stockQuantity,
            'sale_price' => '10.00',
            'status' => $status,
            'stock_state' => $stockState,
        ];
    }
}
