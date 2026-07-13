<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminBookshopBookPdfAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AdminBookshopBookPdfActionTest extends TestCase
{
    public function testReturnsPrintableHtmlForSelectedBook(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findBookByIdForAdmin(7)
            ->willReturn($this->buildBook())
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminBookshopBookPdfAction($logger, $twig, $bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('GET', '/painel/livraria/acervo/7/pdf', ['HTTP_ACCEPT' => 'application/pdf'])
            ->withAttribute('id', 7);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('', $response->getHeaderLine('X-Cede-Document-Fallback'));
        $this->assertStringContainsString('LIVRARIA AUTA DE SOUSA', $body);
        $this->assertStringContainsString('CENTRO DE ESTUDOS DA DOUTRINA ESPÍRITA', $body);
        $this->assertStringContainsString('Semeador de Estrelas', $body);
        $this->assertStringContainsString('Joana de Angelis', $body);
        $this->assertStringContainsString('Dados editoriais', $body);
        $this->assertStringContainsString('ISBN', $body);
        $this->assertStringContainsString('Conferência administrativa do acervo da livraria.', $body);
        $this->assertStringContainsString('/painel/livraria/acervo/7', $body);
        $this->assertStringNotContainsString('Quadro de identificação', $body);
        $this->assertStringNotContainsString('Registro administrativo', $body);
        $this->assertStringNotContainsString('Gerador de PDF indisponível neste servidor no momento.', $body);
    }

    public function testReturnsPrintableHtmlWithoutFallbackNoticeForOtherBooks(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findBookByIdForAdmin(7)
            ->willReturn($this->buildBook([
                'title' => 'Livro Fallback',
                'slug' => 'livro-fallback',
            ]))
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new AdminBookshopBookPdfAction($logger, $twig, $bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('GET', '/painel/livraria/acervo/7/pdf', ['HTTP_ACCEPT' => 'application/pdf'])
            ->withAttribute('id', 7);

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('LIVRARIA AUTA DE SOUSA', $body);
        $this->assertStringContainsString('CENTRO DE ESTUDOS DA DOUTRINA ESPÍRITA', $body);
        $this->assertStringContainsString('Dados editoriais', $body);
        $this->assertStringContainsString('Livro Fallback', $body);
        $this->assertStringContainsString('/painel/livraria/acervo/7', $body);
        $this->assertStringNotContainsString('Quadro de identificação', $body);
        $this->assertStringNotContainsString('Registro administrativo', $body);
        $this->assertStringNotContainsString('Gerador de PDF indisponível neste servidor no momento.', $body);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildBook(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'sku' => 'CEDE-LIV-0042',
            'slug' => 'semeador-de-estrelas',
            'title' => 'Semeador de Estrelas',
            'subtitle' => 'Reflexões para o cotidiano',
            'author_name' => 'Joana de Angelis',
            'collection_name' => 'Coleção Harmonia',
            'volume_number' => 2,
            'volume_label' => 'Edição revista',
            'genre_name' => 'Espiritualidade',
            'category_name' => 'Doutrina',
            'publisher_name' => 'Editora CEDE',
            'edition_label' => '3ª edição',
            'publication_year' => 2024,
            'page_count' => 184,
            'language' => 'Português',
            'isbn' => '9786580000001',
            'barcode' => '7891000000042',
            'location_label' => 'B-3',
            'stock_quantity' => 12,
            'stock_minimum' => 3,
            'stock_state' => 'ok',
            'stock_state_label' => 'Em estoque',
            'sale_price' => '39.90',
            'sale_price_label' => 'R$ 39,90',
            'cost_price' => '22.50',
            'cost_price_label' => 'R$ 22,50',
            'inventory_value' => 270.00,
            'inventory_value_label' => 'R$ 270,00',
            'potential_revenue_value' => 478.80,
            'potential_revenue_label' => 'R$ 478,80',
            'status' => 'active',
            'status_label' => 'Ativo',
            'description' => '<p>Obra voltada ao atendimento fraterno e ao estudo mediúnico.</p>',
            'created_by_name' => 'Operador da Livraria',
            'created_at' => '2026-07-10 15:30:00',
            'updated_at' => '2026-07-12 09:45:00',
            'cover_image_path' => '',
        ], $overrides);
    }
}
