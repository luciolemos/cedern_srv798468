<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminBookshopSaleFormPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

class AdminBookshopSaleFormPageActionTest extends TestCase
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

    public function testPostCreatesSaleWithUppercaseCustomerName(): void
    {
        $_SESSION['member_user_id'] = 42;
        $_SESSION['member_name'] = 'Equipe CEDE';

        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'status' => 'active',
                'stock_quantity' => 5,
                'stock_lots' => [
                    [
                        'id' => 501,
                        'quantity_available' => 5,
                    ],
                ],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createSale(
                Argument::that(static function (array $payload): bool {
                    return $payload['sold_at'] === '2026-06-28 13:30:00'
                        && $payload['customer_name'] === 'JOSÉ DA SILVA'
                        && $payload['customer_phone'] === ''
                        && $payload['customer_email'] === 'cliente@exemplo.com'
                        && $payload['customer_cpf'] === '52998224725'
                        && $payload['payment_method'] === 'pix'
                        && $payload['discount_amount'] === '0.00'
                        && $payload['received_amount'] === ''
                        && $payload['notes'] === 'Primeira compra'
                        && $payload['created_by_member_id'] === 42
                        && $payload['created_by_name'] === 'Equipe CEDE';
                }),
                Argument::that(static function (array $items): bool {
                    return $items === [
                        [
                            'book_id' => 10,
                            'lot_id' => 0,
                            'quantity' => 2,
                            'unit_price' => '49.90',
                        ],
                    ];
                })
            )
            ->willReturn(88)
            ->shouldBeCalledOnce();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/vendas/nova')
            ->withParsedBody([
                'sold_at' => '2026-06-28T10:30',
                'customer_name' => 'josé da silva',
                'customer_email' => 'Cliente@Exemplo.com',
                'customer_cpf' => '529.982.247-25',
                'payment_method' => 'pix',
                'discount_amount' => '0',
                'notes' => 'Primeira compra',
                'items' => [
                    [
                        'book_id' => '10',
                        'quantity' => '2',
                        'unit_price' => '49,90',
                    ],
                ],
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/livraria/vendas/88', $response->getHeaderLine('Location'));
        $this->assertSame(
            [
                'status' => 'created',
            ],
            $_SESSION['_codex_flash'][AdminBookshopSaleFormPageAction::viewFlashKey(88)] ?? null
        );
    }

    public function testPostWithoutCpfCreatesSale(): void
    {
        $_SESSION['member_user_id'] = 42;
        $_SESSION['member_name'] = 'Equipe CEDE';

        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'status' => 'active',
                'stock_quantity' => 5,
                'stock_lots' => [
                    [
                        'id' => 501,
                        'quantity_available' => 5,
                    ],
                ],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createSale(
                Argument::that(static function (array $payload): bool {
                    return $payload['customer_name'] === 'CLIENTE MIRIM'
                        && $payload['customer_cpf'] === ''
                        && $payload['created_by_member_id'] === 42
                        && $payload['created_by_name'] === 'Equipe CEDE';
                }),
                Argument::that(static function (array $items): bool {
                    return $items === [
                        [
                            'book_id' => 10,
                            'lot_id' => 0,
                            'quantity' => 1,
                            'unit_price' => '19.90',
                        ],
                    ];
                })
            )
            ->willReturn(91)
            ->shouldBeCalledOnce();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/vendas/nova')
            ->withParsedBody([
                'sold_at' => '2026-06-28T10:30',
                'customer_name' => 'cliente mirim',
                'customer_cpf' => '',
                'payment_method' => 'pix',
                'discount_amount' => '0',
                'items' => [
                    [
                        'book_id' => '10',
                        'quantity' => '1',
                        'unit_price' => '19,90',
                    ],
                ],
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/livraria/vendas/91', $response->getHeaderLine('Location'));
        $this->assertSame(
            [
                'status' => 'created',
            ],
            $_SESSION['_codex_flash'][AdminBookshopSaleFormPageAction::viewFlashKey(91)] ?? null
        );
    }

    public function testPostWithoutCustomerNameRedirectsBackWithErrors(): void
    {
        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'status' => 'active',
                'stock_quantity' => 5,
                'stock_lots' => [
                    [
                        'id' => 501,
                        'quantity_available' => 5,
                    ],
                ],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createSale(Argument::cetera())
            ->shouldNotBeCalled();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/vendas/nova')
            ->withParsedBody([
                'sold_at' => '2026-06-28T10:30',
                'customer_name' => '',
                'customer_cpf' => '',
                'payment_method' => 'pix',
                'discount_amount' => '0',
                'items' => [
                    [
                        'book_id' => '10',
                        'quantity' => '1',
                        'unit_price' => '49,90',
                    ],
                ],
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/livraria/vendas/nova', $response->getHeaderLine('Location'));

        $flash = $_SESSION['_codex_flash']['admin_bookshop_sale_form'] ?? null;

        $this->assertIsArray($flash);
        $this->assertContains('Informe o nome do cliente.', $flash['errors'] ?? []);
        $this->assertNotContains('Informe o CPF do cliente.', $flash['errors'] ?? []);
    }

    public function testPostWithInvalidCpfRedirectsBackWithErrors(): void
    {
        $books = [
            [
                'id' => 10,
                'title' => 'Livro de Teste',
                'status' => 'active',
                'stock_quantity' => 5,
                'stock_lots' => [
                    [
                        'id' => 501,
                        'quantity_available' => 5,
                    ],
                ],
            ],
        ];

        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findAllBooksForAdmin()
            ->willReturn($books)
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->createSale(Argument::cetera())
            ->shouldNotBeCalled();

        $action = $this->createAction($bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/painel/livraria/vendas/nova')
            ->withParsedBody([
                'sold_at' => '2026-06-28T10:30',
                'customer_name' => 'Cliente Teste',
                'customer_cpf' => '111.111.111-11',
                'payment_method' => 'pix',
                'discount_amount' => '0',
                'items' => [
                    [
                        'book_id' => '10',
                        'quantity' => '1',
                        'unit_price' => '49,90',
                    ],
                ],
            ]);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/livraria/vendas/nova', $response->getHeaderLine('Location'));

        $flash = $_SESSION['_codex_flash']['admin_bookshop_sale_form'] ?? null;

        $this->assertIsArray($flash);
        $this->assertContains('Informe um CPF válido para o cliente.', $flash['errors'] ?? []);
    }

    private function createAction(BookshopRepository $bookshopRepository): AdminBookshopSaleFormPageAction
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new AdminBookshopSaleFormPageAction($logger, $twig, $bookshopRepository);
    }
}
