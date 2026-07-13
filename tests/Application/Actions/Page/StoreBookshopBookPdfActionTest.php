<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\StoreBookshopBookPdfAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class StoreBookshopBookPdfActionTest extends TestCase
{
    public function testReturnsPrintableHtmlForSelectedPublicBook(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findCatalogBookBySlug('semeador-de-estrelas')
            ->willReturn($this->buildBook())
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new StoreBookshopBookPdfAction($logger, $twig, $bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('GET', '/loja/livraria/semeador-de-estrelas/pdf', ['HTTP_ACCEPT' => 'application/pdf'])
            ->withAttribute('slug', 'semeador-de-estrelas');

        $response = $action($request, new Response());
        $body = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('LIVRARIA AUTA DE SOUSA', $body);
        $this->assertStringContainsString('Semeador de Estrelas', $body);
        $this->assertStringContainsString('Origem da consulta', $body);
        $this->assertStringContainsString('Consulta e impressão da ficha do título da livraria.', $body);
        $this->assertStringContainsString('https://cedern.org/loja/livraria#livraria-semeador-de-estrelas', $body);
        $this->assertStringNotContainsString('Conferência administrativa do acervo da livraria.', $body);
    }

    public function testReturnsNotFoundWhenSlugDoesNotExist(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findCatalogBookBySlug('inexistente')
            ->willReturn(null)
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $action = new StoreBookshopBookPdfAction($logger, $twig, $bookshopRepositoryProphecy->reveal());

        $request = $this->createRequest('GET', '/loja/livraria/inexistente/pdf')
            ->withAttribute('slug', 'inexistente');

        $response = $action($request, new Response());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Livro não encontrado.', (string) $response->getBody());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBook(): array
    {
        return [
            'id' => 7,
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
            'description' => '<p>Obra voltada ao atendimento fraterno e ao estudo mediúnico.</p>',
            'cover_image_path' => '',
        ];
    }
}
