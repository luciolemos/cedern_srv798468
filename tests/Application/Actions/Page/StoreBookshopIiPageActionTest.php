<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\StoreBookshopIiPageAction;
use App\Domain\Bookshop\BookshopRepository;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Tests\TestCase;
use Twig\TwigFunction;

final class StoreBookshopIiPageActionTest extends TestCase
{
    public function testRendersPublicPrintLinkForEachCatalogBook(): void
    {
        $bookshopRepositoryProphecy = $this->prophesize(BookshopRepository::class);
        $bookshopRepositoryProphecy
            ->findCatalogBooks()
            ->willReturn([
                [
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
                    'description' => '<p>Descrição do livro.</p>',
                    'cover_image_url' => '',
                    'stock_quantity' => 12,
                    'stock_state' => 'ok',
                    'sale_price' => '39.90',
                    'sale_price_label' => 'R$ 39,90',
                ],
            ])
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findCatalogCategories()
            ->willReturn([
                ['slug' => 'doutrina', 'name' => 'Doutrina'],
            ])
            ->shouldBeCalledOnce();
        $bookshopRepositoryProphecy
            ->findCatalogGenres()
            ->willReturn([
                ['slug' => 'espiritualidade', 'name' => 'Espiritualidade'],
            ])
            ->shouldBeCalledOnce();

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);
        $twig->getEnvironment()->addFunction(new TwigFunction(
            'is_breadcrumb_linkable',
            static fn (string $path): bool => trim($path) !== ''
        ));

        $action = new StoreBookshopIiPageAction($logger, $twig, $bookshopRepositoryProphecy->reveal());
        $request = $this->createRequest('GET', '/loja/livraria');
        $response = $action($request, $app->getResponseFactory()->createResponse());
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/loja/livraria/semeador-de-estrelas/pdf', $html);
        $this->assertStringContainsString('Abrir ficha para impressão', $html);
    }
}
