<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopBookLotsPageAction extends AbstractAdminBookshopAction
{
    public function __invoke(Request $request, Response $response): Response
    {
        $id = (int) ($request->getAttribute('id') ?? 0);

        if ($id <= 0) {
            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
        }

        $book = $this->bookshopRepository->findBookByIdForAdmin($id);
        if ($book === null) {
            $this->storeSessionFlash(AdminBookshopBookListPageAction::FLASH_KEY, [
                'status' => 'not-found',
            ]);

            return $response->withHeader('Location', '/painel/livraria/acervo')->withStatus(303);
        }

        $lots = $this->bookshopRepository->findStockLotsByBookIdForAdmin($id);
        $activeLots = array_values(array_filter(
            $lots,
            static fn (array $lot): bool => (int) ($lot['quantity_available'] ?? 0) > 0
        ));

        $summary = [
            'total_lots' => count($lots),
            'active_lots' => count($activeLots),
            'total_received' => array_reduce(
                $lots,
                static fn (int $carry, array $lot): int => $carry + (int) ($lot['quantity_received'] ?? 0),
                0
            ),
            'total_available' => array_reduce(
                $lots,
                static fn (int $carry, array $lot): int => $carry + (int) ($lot['quantity_available'] ?? 0),
                0
            ),
            'total_sold' => array_reduce(
                $lots,
                static fn (int $carry, array $lot): int => $carry + (int) ($lot['quantity_sold'] ?? 0),
                0
            ),
            'available_cost_value' => array_reduce(
                $lots,
                static function (float $carry, array $lot): float {
                    $unitCost = isset($lot['unit_cost']) && $lot['unit_cost'] !== null ? (float) $lot['unit_cost'] : 0.0;

                    return $carry + ($unitCost * (int) ($lot['quantity_available'] ?? 0));
                },
                0.0
            ),
            'available_sale_value' => array_reduce(
                $lots,
                static fn (float $carry, array $lot): float => $carry
                    + ((float) ($lot['unit_sale_price'] ?? 0) * (int) ($lot['quantity_available'] ?? 0)),
                0.0
            ),
        ];

        return $this->renderPage($response, 'pages/admin-bookshop-book-lots.twig', [
            'bookshop_book' => $book,
            'bookshop_book_lots' => $lots,
            'bookshop_book_lot_summary' => [
                'total_lots' => $summary['total_lots'],
                'active_lots' => $summary['active_lots'],
                'total_received' => $summary['total_received'],
                'total_available' => $summary['total_available'],
                'total_sold' => $summary['total_sold'],
                'available_cost_value' => $summary['available_cost_value'],
                'available_sale_value' => $summary['available_sale_value'],
                'available_cost_value_label' => 'R$ ' . number_format($summary['available_cost_value'], 2, ',', '.'),
                'available_sale_value_label' => 'R$ ' . number_format($summary['available_sale_value'], 2, ',', '.'),
            ],
            'page_title' => ($book['title'] ?? 'Item') . ' | Lotes | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/acervo/' . $id . '/lotes',
            'page_description' => 'Visualização dos lotes de estoque do título da livraria.',
        ]);
    }
}
