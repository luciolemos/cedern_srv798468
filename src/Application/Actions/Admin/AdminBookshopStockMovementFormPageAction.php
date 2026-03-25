<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopStockMovementFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY = 'admin_bookshop_stock_movement_form';

    private const LOCAL_TIMEZONE = 'America/Fortaleza';

    /**
     * @var array<string, string>
     */
    private const ENTRY_TYPE_OPTIONS = [
        'entry' => 'Compra / reposição',
        'donation' => 'Doação recebida',
    ];

    /**
     * @var array<string, string>
     */
    private const ADJUSTMENT_TYPE_OPTIONS = [
        'adjustment_add' => 'Ajuste positivo',
        'adjustment_remove' => 'Ajuste negativo',
        'loss' => 'Perda ou avaria',
    ];

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar livros para movimentação de estoque.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $mode = $this->resolveMode($request);

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            if (!array_key_exists('book_id', $submittedPayload)) {
                $prefilledBookId = $this->resolvePrefilledBookId($request, $books);
                if ($prefilledBookId > 0) {
                    $submittedPayload['book_id'] = $prefilledBookId;
                }
            }

            if (array_key_exists('mode', $submittedPayload)) {
                $mode = $this->normalizeMode((string) $submittedPayload['mode']);
            }

            return $this->renderForm($response, $books, $mode, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->hydrateStockLotSelection($this->normalizePayload($body, $mode), $books);
        $errors = $this->validatePayload($payload, $books);

        if ($errors !== []) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response
                ->withHeader('Location', '/painel/livraria/movimentacoes/nova?mode=' . rawurlencode((string) $payload['mode']))
                ->withStatus(303);
        }

        $actor = $this->resolveAdminActor();

        try {
            $movementId = $this->bookshopRepository->createStockMovement([
                'book_id' => (int) $payload['book_id'],
                'movement_type' => (string) $payload['movement_type'],
                'quantity' => (int) $payload['quantity'],
                'unit_cost' => (string) ($payload['unit_cost'] ?? ''),
                'sale_price' => (string) ($payload['sale_price'] ?? ''),
                'notes' => (string) ($payload['notes'] ?? ''),
                'occurred_at' => $this->convertLocalDateTimeToUtc((string) $payload['occurred_at']),
                'created_by_member_id' => $actor['member_id'],
                'created_by_name' => $actor['member_name'],
            ]);

            if ($movementId <= 0) {
                throw new \RuntimeException('Não foi possível registrar a movimentação.');
            }

            $updatedBook = $this->bookshopRepository->findBookByIdForAdmin((int) $payload['book_id']);

            $this->storeSessionFlash(AdminBookshopStockMovementListPageAction::FLASH_KEY, [
                'status' => 'created',
                'movement_id' => $movementId,
                'book_title' => (string) ($updatedBook['title'] ?? ''),
                'stock_quantity' => (int) ($updatedBook['stock_quantity'] ?? 0),
            ]);

            return $response->withHeader('Location', '/painel/livraria/movimentacoes')->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao registrar movimentação de estoque da livraria.', [
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'payload' => $payload,
                'errors' => [$exception->getMessage()],
            ]);

            return $response
                ->withHeader('Location', '/painel/livraria/movimentacoes/nova?mode=' . rawurlencode((string) $payload['mode']))
                ->withStatus(303);
        }
    }

    private function resolveMode(Request $request): string
    {
        $queryParams = $request->getQueryParams();

        return $this->normalizeMode((string) ($queryParams['mode'] ?? 'entry'));
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, ['entry', 'adjustment'], true) ? $mode : 'entry';
    }

    /**
     * @param array<int, array<string, mixed>> $books
     */
    private function resolvePrefilledBookId(Request $request, array $books): int
    {
        $queryParams = $request->getQueryParams();
        $bookId = $this->normalizeIntegerInput($queryParams['book_id'] ?? 0, 0);

        if ($bookId <= 0) {
            return 0;
        }

        return $this->resolveBookById($books, $bookId) !== null ? $bookId : 0;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input, string $fallbackMode): array
    {
        $mode = $this->normalizeMode((string) ($input['mode'] ?? $fallbackMode));
        $occurredAtRaw = trim((string) ($input['occurred_at'] ?? ''));
        $occurredAt = $occurredAtRaw !== '' ? str_replace('T', ' ', $occurredAtRaw) : $this->currentLocalDateTime();

        if (strlen($occurredAt) === 16) {
            $occurredAt .= ':00';
        }

        $movementType = trim((string) ($input['movement_type'] ?? ''));
        if ($movementType === '' || !array_key_exists($movementType, $this->resolveMovementTypeOptions($mode))) {
            $movementType = $mode === 'adjustment' ? 'adjustment_add' : 'entry';
        }

        $unitCostRaw = trim((string) ($input['unit_cost'] ?? ''));
        if ($mode === 'entry' && $movementType === 'donation') {
            $unitCostRaw = '';
        }

        return [
            'mode' => $mode,
            'occurred_at' => $occurredAt,
            'book_id' => $this->normalizeIntegerInput($input['book_id'] ?? 0, 0),
            'stock_lot_id' => $this->normalizeIntegerInput($input['stock_lot_id'] ?? 0, 0),
            'movement_type' => $movementType,
            'quantity' => max(0, $this->normalizeIntegerInput($input['quantity'] ?? 0, 0)),
            'unit_cost' => $unitCostRaw !== '' ? $this->normalizeMoneyInput($unitCostRaw) : '',
            'sale_price' => trim((string) ($input['sale_price'] ?? '')) !== ''
                ? $this->normalizeMoneyInput($input['sale_price'] ?? '')
                : '',
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $books
     * @return array<int, string>
     */
    private function validatePayload(array $payload, array $books): array
    {
        $errors = [];
        $booksById = [];

        foreach ($books as $book) {
            $booksById[(int) ($book['id'] ?? 0)] = $book;
        }

        try {
            new \DateTimeImmutable((string) ($payload['occurred_at'] ?? ''), new \DateTimeZone(self::LOCAL_TIMEZONE));
        } catch (\Throwable $exception) {
            $errors[] = 'Informe uma data e hora válidas para a movimentação.';
        }

        $bookId = (int) ($payload['book_id'] ?? 0);
        if ($bookId <= 0) {
            $errors[] = 'Selecione um livro do acervo.';
        }

        $book = $booksById[$bookId] ?? null;
        if ($bookId > 0 && $book === null) {
            $errors[] = 'O livro selecionado não foi encontrado no acervo.';
        }

        $movementType = (string) ($payload['movement_type'] ?? '');
        $stockLotId = (int) ($payload['stock_lot_id'] ?? 0);
        if (!array_key_exists($movementType, $this->resolveMovementTypeOptions((string) ($payload['mode'] ?? 'entry')))) {
            $errors[] = 'Selecione um ' . $this->resolveMovementTypeFieldLabel((string) ($payload['mode'] ?? 'entry')) . ' válido.';
        }

        $quantity = (int) ($payload['quantity'] ?? 0);
        if ($quantity <= 0) {
            $errors[] = 'Informe uma quantidade maior do que zero.';
        }

        if ((string) ($payload['mode'] ?? 'entry') === 'adjustment' && $book !== null) {
            $availableLots = $this->resolveSelectableAdjustmentLots($book);
            $selectedLot = $this->resolveSelectedAdjustmentLot($availableLots, $stockLotId);

            if ($selectedLot === null && $stockLotId <= 0 && count($availableLots) === 1) {
                $selectedLot = $availableLots[0];
            }

            if ($stockLotId > 0 && $selectedLot === null) {
                $errors[] = 'Selecione um lote válido para o ajuste.';
            }

            if ($movementType === 'adjustment_add') {
                if ($selectedLot === null && count($availableLots) > 1) {
                    $errors[] = 'Selecione o lote do ajuste para "' . (string) ($book['title'] ?? 'Livro') . '".';
                }
            } elseif (in_array($movementType, ['adjustment_remove', 'loss'], true)) {
                if ($selectedLot === null) {
                    $errors[] = $availableLots === []
                        ? 'O item "' . (string) ($book['title'] ?? 'Livro') . '" não possui lote disponível para este ajuste.'
                        : 'Selecione o lote do ajuste para "' . (string) ($book['title'] ?? 'Livro') . '".';
                } elseif ((int) ($selectedLot['quantity_available'] ?? 0) < $quantity) {
                    $errors[] = 'Quantidade acima do disponível no lote '
                        . (string) ($selectedLot['lot_code'] ?? '')
                        . ' de "'
                        . (string) ($book['title'] ?? 'Livro')
                        . '".';
                }
            }
        }

        if ($book !== null && $this->isUnitCostRequired((string) ($payload['mode'] ?? 'entry'), $movementType)) {
            if ((float) ($payload['unit_cost'] ?? 0) <= 0) {
                $errors[] = 'Informe o custo unitário da entrada.';
            }
        }

        if (
            $book !== null
            && in_array($movementType, ['entry', 'donation', 'adjustment_add'], true)
            && (float) ($payload['sale_price'] ?? 0) <= 0
        ) {
            $errors[] = 'Informe o preço de venda do lote para esta entrada.';
        }

        if ($book !== null && in_array($movementType, ['adjustment_remove', 'loss'], true)) {
            if ((int) ($book['stock_quantity'] ?? 0) < $quantity) {
                $errors[] = 'O saldo atual do livro não comporta essa saída.';
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $books
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        array $books,
        string $mode,
        array $submittedPayload,
        array $errors
    ): Response {
        $bookOptions = array_map(static function (array $book): array {
            $fragments = [
                (string) ($book['title'] ?? 'Livro'),
                (string) ($book['author_name'] ?? ''),
                'estoque ' . (int) ($book['stock_quantity'] ?? 0),
            ];

            return [
                'id' => (int) ($book['id'] ?? 0),
                'label' => implode(' · ', array_values(array_filter($fragments, static fn (mixed $fragment): bool => is_string($fragment) && trim($fragment) !== ''))),
                'title' => (string) ($book['title'] ?? 'Livro'),
                'author_name' => (string) ($book['author_name'] ?? ''),
                'sku' => (string) ($book['sku'] ?? ''),
                'stock_quantity' => (int) ($book['stock_quantity'] ?? 0),
                'cost_price_label' => (string) ($book['cost_price_label'] ?? 'R$ 0,00'),
                'sale_price_label' => (string) ($book['sale_price_label'] ?? 'R$ 0,00'),
                'location_label' => (string) ($book['location_label'] ?? ''),
                'status_label' => (string) ($book['status_label'] ?? ''),
                'stock_lots' => array_values(array_map(static function (array $lot): array {
                    return [
                        'id' => (string) ($lot['id'] ?? ''),
                        'lot_code' => (string) ($lot['lot_code'] ?? ''),
                        'label' => (string) ($lot['label'] ?? ''),
                        'quantity_available' => (int) ($lot['quantity_available'] ?? 0),
                        'unit_cost' => isset($lot['unit_cost']) && $lot['unit_cost'] !== null
                            ? (string) number_format((float) $lot['unit_cost'], 2, '.', '')
                            : '',
                        'unit_cost_label' => (string) ($lot['unit_cost_label'] ?? '-'),
                        'unit_sale_price' => (string) number_format((float) ($lot['unit_sale_price'] ?? 0), 2, '.', ''),
                        'unit_sale_price_label' => (string) ($lot['unit_sale_price_label'] ?? 'R$ 0,00'),
                        'occurred_at_label' => (string) ($lot['occurred_at_label'] ?? ''),
                    ];
                }, (array) ($book['stock_lots'] ?? []))),
            ];
        }, $books);

        $form = [
            'mode' => $submittedPayload['mode'] ?? $mode,
            'occurred_at' => $submittedPayload['occurred_at'] ?? $this->currentLocalDateTime(),
            'book_id' => $submittedPayload['book_id'] ?? 0,
            'stock_lot_id' => $submittedPayload['stock_lot_id'] ?? 0,
            'movement_type' => $submittedPayload['movement_type'] ?? ($mode === 'adjustment' ? 'adjustment_add' : 'entry'),
            'quantity' => $submittedPayload['quantity'] ?? 1,
            'unit_cost' => $submittedPayload['unit_cost'] ?? '',
            'sale_price' => $submittedPayload['sale_price'] ?? '',
            'notes' => $submittedPayload['notes'] ?? '',
        ];
        $form = $this->hydrateStockLotSelection($form, $books);

        $modeLabel = $mode === 'adjustment' ? 'ajuste' : 'entrada';
        $selectedMovementType = (string) ($form['movement_type'] ?? '');
        $unitCostRequired = $this->isUnitCostRequired($mode, $selectedMovementType);
        $unitCostHidden = $this->isUnitCostHidden($mode, $selectedMovementType);
        $selectedBook = $this->resolveBookById($books, (int) ($form['book_id'] ?? 0));
        $selectedLots = $selectedBook !== null ? $this->resolveSelectableAdjustmentLots($selectedBook) : [];

        return $this->renderPage($response, 'pages/admin-bookshop-stock-movement-form.twig', [
            'bookshop_stock_movement_form' => $form,
            'bookshop_stock_movement_form_errors' => $errors,
            'bookshop_stock_movement_mode' => $mode,
            'bookshop_stock_movement_mode_label' => $modeLabel,
            'bookshop_stock_movement_type_label' => $this->resolveMovementTypeFieldLabel($mode),
            'bookshop_stock_movement_type_options' => $this->resolveMovementTypeOptions($mode),
            'bookshop_stock_movement_cost_required' => $unitCostRequired,
            'bookshop_stock_movement_cost_hidden' => $unitCostHidden,
            'bookshop_stock_movement_lot_options' => $selectedLots,
            'bookshop_stock_movement_lot_required' => $this->isStockLotRequired($mode, $selectedMovementType, $selectedLots),
            'bookshop_stock_movement_book_options' => $bookOptions,
            'page_title' => ($mode === 'adjustment' ? 'Novo ajuste de estoque' : 'Nova entrada de estoque') . ' | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/movimentacoes/nova?mode=' . rawurlencode($mode),
            'page_description' => 'Formulário do dashboard para registrar entradas e ajustes de estoque da livraria.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function resolveMovementTypeOptions(string $mode): array
    {
        if ($mode === 'adjustment') {
            return self::ADJUSTMENT_TYPE_OPTIONS;
        }

        return self::ENTRY_TYPE_OPTIONS;
    }

    private function resolveMovementTypeFieldLabel(string $mode): string
    {
        return $mode === 'adjustment' ? 'tipo de ajuste' : 'tipo de entrada';
    }

    private function isUnitCostRequired(string $mode, string $movementType): bool
    {
        return $mode === 'entry' && $movementType === 'entry';
    }

    private function isUnitCostHidden(string $mode, string $movementType): bool
    {
        return $mode === 'entry' && $movementType === 'donation';
    }

    /**
     * @param array<int, array<string, mixed>> $books
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function hydrateStockLotSelection(array $payload, array $books): array
    {
        if ((string) ($payload['mode'] ?? 'entry') !== 'adjustment') {
            return $payload;
        }

        if ((int) ($payload['stock_lot_id'] ?? 0) > 0) {
            return $payload;
        }

        $book = $this->resolveBookById($books, (int) ($payload['book_id'] ?? 0));
        if ($book === null) {
            return $payload;
        }

        $lots = $this->resolveSelectableAdjustmentLots($book);
        if (count($lots) === 1) {
            $payload['stock_lot_id'] = (int) ($lots[0]['id'] ?? 0);
        }

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $books
     * @return array<string, mixed>|null
     */
    private function resolveBookById(array $books, int $bookId): ?array
    {
        foreach ($books as $book) {
            if ((int) ($book['id'] ?? 0) === $bookId) {
                return $book;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $book
     * @return array<int, array<string, mixed>>
     */
    private function resolveSelectableAdjustmentLots(array $book): array
    {
        return array_values(array_filter(
            (array) ($book['stock_lots'] ?? []),
            static fn (mixed $lot): bool => is_array($lot) && (int) ($lot['id'] ?? 0) > 0
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $lots
     * @return array<string, mixed>|null
     */
    private function resolveSelectedAdjustmentLot(array $lots, int $stockLotId): ?array
    {
        foreach ($lots as $lot) {
            if ((int) ($lot['id'] ?? 0) === $stockLotId) {
                return $lot;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $lots
     */
    private function isStockLotRequired(string $mode, string $movementType, array $lots): bool
    {
        if ($mode !== 'adjustment' || $lots === []) {
            return false;
        }

        if (in_array($movementType, ['adjustment_remove', 'loss'], true)) {
            return true;
        }

        return $movementType === 'adjustment_add' && count($lots) > 1;
    }

    private function currentLocalDateTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::LOCAL_TIMEZONE)))
            ->format('Y-m-d H:i:s');
    }

    private function convertLocalDateTimeToUtc(string $value): string
    {
        $localDate = new \DateTimeImmutable($value, new \DateTimeZone(self::LOCAL_TIMEZONE));

        return $localDate
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
