<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminBookshopSaleFormPageAction extends AbstractAdminBookshopAction
{
    private const FLASH_KEY = 'admin_bookshop_sale_form';

    private const MAX_ITEMS = 8;

    private const SALE_TIMEZONE = 'America/Fortaleza';

    public function __invoke(Request $request, Response $response): Response
    {
        $books = [];

        try {
            $books = $this->bookshopRepository->findAllBooksForAdmin();
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar livros para o PDV.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $saleableBooks = array_values(array_filter(
            $books,
            static fn (array $book): bool => (string) ($book['status'] ?? '') === 'active'
                && (int) ($book['stock_quantity'] ?? 0) > 0
        ));

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $submittedPayload = (array) ($flash['payload'] ?? []);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));

            if (!array_key_exists('items', $submittedPayload)) {
                $submittedPayload['items'] = $this->resolvePrefilledItems($request, $saleableBooks);
            }

            return $this->renderForm($response, $saleableBooks, $submittedPayload, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload, $books);

        if ($errors !== []) {
            $this->storeSessionFlash(self::FLASH_KEY, [
                'payload' => $payload,
                'errors' => $errors,
            ]);

            return $response->withHeader('Location', '/painel/livraria/vendas/nova')->withStatus(303);
        }

        $actor = $this->resolveAdminActor();

        try {
            $saleId = $this->bookshopRepository->createSale([
                'sold_at' => $this->convertLocalDateTimeToUtc((string) $payload['sold_at']),
                'customer_name' => (string) ($payload['customer_name'] ?? ''),
                'customer_phone' => (string) ($payload['customer_phone'] ?? ''),
                'customer_email' => (string) ($payload['customer_email'] ?? ''),
                'customer_cpf' => (string) ($payload['customer_cpf'] ?? ''),
                'payment_method' => (string) $payload['payment_method'],
                'discount_amount' => (string) $payload['discount_amount'],
                'received_amount' => (string) ($payload['received_amount'] ?? ''),
                'notes' => (string) ($payload['notes'] ?? ''),
                'created_by_member_id' => $actor['member_id'],
                'created_by_name' => $actor['member_name'],
            ], (array) $payload['items']);

            if ($saleId <= 0) {
                throw new \RuntimeException('Não foi possível registrar a venda.');
            }

            $this->storeSessionFlash($this->resolveViewFlashKey($saleId), [
                'status' => 'created',
            ]);

            return $response->withHeader('Location', '/painel/livraria/vendas/' . $saleId)->withStatus(303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao registrar venda no PDV da livraria.', [
                'error' => $exception->getMessage(),
            ]);

            $this->storeSessionFlash(self::FLASH_KEY, [
                'payload' => $payload,
                'errors' => [$exception->getMessage()],
            ]);

            return $response->withHeader('Location', '/painel/livraria/vendas/nova')->withStatus(303);
        }
    }

    public static function viewFlashKey(int $saleId): string
    {
        return 'admin_bookshop_sale_view_' . $saleId;
    }

    private function resolveViewFlashKey(int $saleId): string
    {
        return self::viewFlashKey($saleId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input): array
    {
        $soldAtRaw = trim((string) ($input['sold_at'] ?? ''));
        $soldAt = $soldAtRaw !== '' ? str_replace('T', ' ', $soldAtRaw) : $this->currentLocalDateTime();

        if (strlen($soldAt) === 16) {
            $soldAt .= ':00';
        }

        $paymentMethod = trim((string) ($input['payment_method'] ?? 'pix'));
        if (!in_array($paymentMethod, ['cash', 'pix', 'debit', 'credit', 'transfer', 'other'], true)) {
            $paymentMethod = 'other';
        }
        $receivedAmountRaw = trim((string) ($input['received_amount'] ?? ''));
        if ($paymentMethod !== 'cash') {
            $receivedAmountRaw = '';
        }
        $customerPhone = $this->normalizePhoneInput($input['customer_phone'] ?? '');
        $customerEmail = strtolower(trim((string) ($input['customer_email'] ?? '')));
        $customerCpf = $this->normalizeCpfInput($input['customer_cpf'] ?? '');

        $rawItems = array_slice((array) ($input['items'] ?? []), 0, self::MAX_ITEMS);
        $items = [];

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $bookId = $this->normalizeIntegerInput($rawItem['book_id'] ?? 0, 0);
            $lotId = $this->normalizeIntegerInput($rawItem['lot_id'] ?? 0, 0);
            $quantity = max(0, $this->normalizeIntegerInput($rawItem['quantity'] ?? 0, 0));
            $unitPrice = $this->normalizeMoneyInput($rawItem['unit_price'] ?? '0');

            if ($bookId <= 0 && $lotId <= 0 && $quantity === 0 && (float) $unitPrice === 0.0) {
                continue;
            }

            $items[] = [
                'book_id' => $bookId,
                'lot_id' => $lotId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return [
            'sold_at' => $soldAt,
            'customer_name' => trim((string) ($input['customer_name'] ?? '')),
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'customer_cpf' => $customerCpf,
            'payment_method' => $paymentMethod,
            'discount_amount' => $this->normalizeMoneyInput($input['discount_amount'] ?? '0'),
            'received_amount' => $receivedAmountRaw !== ''
                ? $this->normalizeMoneyInput($receivedAmountRaw)
                : '',
            'notes' => trim((string) ($input['notes'] ?? '')),
            'items' => $items,
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
        $requestedQuantityByBookId = [];

        foreach ($books as $book) {
            $booksById[(int) ($book['id'] ?? 0)] = $book;
        }

        try {
            new \DateTimeImmutable((string) ($payload['sold_at'] ?? ''), new \DateTimeZone(self::SALE_TIMEZONE));
        } catch (\Throwable $exception) {
            $errors[] = 'Informe uma data e hora válidas para a venda.';
        }

        $customerEmail = trim((string) ($payload['customer_email'] ?? ''));
        if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Informe um e-mail válido para o cliente ou deixe em branco.';
        }

        $customerPhone = trim((string) ($payload['customer_phone'] ?? ''));
        if ($customerPhone !== '' && !$this->isValidCustomerPhone($customerPhone)) {
            $errors[] = 'Informe um telefone válido com DDD para o cliente ou deixe em branco.';
        }

        $customerCpf = trim((string) ($payload['customer_cpf'] ?? ''));
        if ($customerCpf !== '' && !$this->isValidCpf($customerCpf)) {
            $errors[] = 'Informe um CPF válido para o cliente ou deixe em branco.';
        }

        if (
            !in_array(
                (string) ($payload['payment_method'] ?? ''),
                ['cash', 'pix', 'debit', 'credit', 'transfer', 'other'],
                true
            )
        ) {
            $errors[] = 'Selecione uma forma de pagamento válida.';
        }

        $items = (array) ($payload['items'] ?? []);
        if ($items === []) {
            $errors[] = 'Inclua ao menos um item na venda.';
        }

        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $row = $index + 1;
            $bookId = (int) ($item['book_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            if ($bookId <= 0) {
                $errors[] = 'Selecione um livro válido na linha ' . $row . '.';
                continue;
            }

            if ($quantity <= 0) {
                $errors[] = 'Informe uma quantidade válida na linha ' . $row . '.';
            }

            if ($unitPrice < 0) {
                $errors[] = 'Preço unitário inválido na linha ' . $row . '.';
            }

            $book = $booksById[$bookId] ?? null;
            if ($book === null) {
                $errors[] = 'O item da linha ' . $row . ' não foi encontrado no acervo.';
                continue;
            }

            if ((string) ($book['status'] ?? '') !== 'active') {
                $errors[] = 'O item "' . (string) ($book['title'] ?? 'Livro') . '" está inativo.';
            }

            $lots = array_values(array_filter(
                (array) ($book['stock_lots'] ?? []),
                static fn (mixed $lot): bool => is_array($lot) && (int) ($lot['quantity_available'] ?? 0) > 0
            ));
            if ($lots === []) {
                $errors[] = 'O item "' . (string) ($book['title'] ?? 'Livro') . '" não possui lote disponível para baixa automática.';
            }

            $requestedQuantityByBookId[$bookId] = ($requestedQuantityByBookId[$bookId] ?? 0) + max(0, $quantity);
            $subtotal += max(0, $unitPrice) * max(0, $quantity);
        }

        foreach ($requestedQuantityByBookId as $bookId => $requestedQuantity) {
            $book = $booksById[$bookId] ?? null;
            if ($book === null) {
                continue;
            }

            $availableLotsQuantity = array_reduce(
                (array) ($book['stock_lots'] ?? []),
                static fn (int $carry, mixed $lot): int => $carry
                    + (is_array($lot) ? max(0, (int) ($lot['quantity_available'] ?? 0)) : 0),
                0
            );

            if ((int) ($book['stock_quantity'] ?? 0) < $requestedQuantity) {
                $errors[] = 'Estoque insuficiente para "' . (string) ($book['title'] ?? 'Livro') . '".';
                continue;
            }

            if ($availableLotsQuantity < $requestedQuantity) {
                $errors[] = 'Os lotes disponíveis de "' . (string) ($book['title'] ?? 'Livro') . '" não cobrem a quantidade informada na venda.';
            }
        }

        if ((float) ($payload['discount_amount'] ?? 0) > $subtotal) {
            $errors[] = 'O desconto não pode ser maior do que o subtotal.';
        }

        if ((string) ($payload['payment_method'] ?? '') === 'cash') {
            $receivedAmountRaw = trim((string) ($payload['received_amount'] ?? ''));
            $receivedAmount = $receivedAmountRaw !== '' ? (float) $receivedAmountRaw : 0.0;
            $totalAfterDiscount = max(0, $subtotal - (float) ($payload['discount_amount'] ?? 0));

            if ($receivedAmountRaw === '') {
                $errors[] = 'Campo "Valor recebido": informe o valor recebido para pagamento em dinheiro.';
            } elseif ($receivedAmount < $totalAfterDiscount) {
                $errors[] = 'Campo "Valor recebido": o valor recebido em dinheiro não pode ser menor do que o total da venda.';
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $saleableBooks
     * @param array<string, mixed> $submittedPayload
     * @param array<int, string> $errors
     */
    private function renderForm(
        Response $response,
        array $saleableBooks,
        array $submittedPayload,
        array $errors
    ): Response {
        $defaultItems = [
            ['book_id' => '', 'quantity' => '1', 'unit_price' => '0.00'],
        ];

        $formItems = array_values(array_map(static function (array $item): array {
            return [
                'book_id' => (string) ($item['book_id'] ?? ''),
                'quantity' => (string) ($item['quantity'] ?? '1'),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
            ];
        }, (array) ($submittedPayload['items'] ?? $defaultItems)));

        if ($formItems === []) {
            $formItems = $defaultItems;
        }

        $bookOptions = array_map(static function (array $book): array {
            $title = (string) ($book['title'] ?? '');
            $authorName = (string) ($book['author_name'] ?? '');
            $sku = (string) ($book['sku'] ?? '');

            return [
                'value' => (string) ($book['id'] ?? ''),
                'label' => sprintf(
                    '%s | %s | %s | estoque %d',
                    $title,
                    $authorName,
                    $sku,
                    (int) ($book['stock_quantity'] ?? 0)
                ),
                'title' => $title,
                'author_name' => $authorName,
                'sku' => $sku,
                'publisher_name' => (string) ($book['publisher_name'] ?? ''),
                'isbn' => (string) ($book['isbn'] ?? ''),
                'barcode' => (string) ($book['barcode'] ?? ''),
                'collection_name' => (string) ($book['collection_name'] ?? ''),
                'volume_number' => $book['volume_number'] ?? null,
                'volume_label' => (string) ($book['volume_label'] ?? ''),
                'sale_price' => (string) number_format((float) ($book['sale_price'] ?? 0), 2, '.', ''),
                'stock_quantity' => (int) ($book['stock_quantity'] ?? 0),
            ];
        }, $saleableBooks);

        return $this->renderPage($response, 'pages/admin-bookshop-sale-form.twig', [
            'bookshop_sale_form' => [
                'sold_at' => (string) ($submittedPayload['sold_at'] ?? $this->currentLocalDateTime()),
                'customer_name' => (string) ($submittedPayload['customer_name'] ?? ''),
                'customer_phone' => $this->formatPhoneForDisplay((string) ($submittedPayload['customer_phone'] ?? '')),
                'customer_email' => (string) ($submittedPayload['customer_email'] ?? ''),
                'customer_cpf' => $this->formatCpfForDisplay((string) ($submittedPayload['customer_cpf'] ?? '')),
                'payment_method' => (string) ($submittedPayload['payment_method'] ?? 'pix'),
                'discount_amount' => $this->formatMoneyForDisplay((string) ($submittedPayload['discount_amount'] ?? '0.00')),
                'received_amount' => $this->formatMoneyForDisplay((string) ($submittedPayload['received_amount'] ?? '')),
                'notes' => (string) ($submittedPayload['notes'] ?? ''),
                'items' => $formItems,
            ],
            'bookshop_sale_form_errors' => $errors,
            'bookshop_sale_form_field_errors' => $this->buildFieldErrors($errors),
            'bookshop_sale_book_options' => $bookOptions,
            'page_title' => 'Nova venda | Dashboard',
            'page_url' => 'https://cedern.org/painel/livraria/vendas/nova',
            'page_description' => 'PDV administrativo da livraria do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $saleableBooks
     * @return array<int, array<string, string>>
     */
    private function resolvePrefilledItems(Request $request, array $saleableBooks): array
    {
        $queryParams = $request->getQueryParams();
        $bookId = $this->normalizeIntegerInput($queryParams['book_id'] ?? 0, 0);

        if ($bookId <= 0) {
            return [];
        }

        foreach ($saleableBooks as $book) {
            if ((int) ($book['id'] ?? 0) !== $bookId) {
                continue;
            }

            return [[
                'book_id' => (string) $bookId,
                'quantity' => '1',
                'unit_price' => (string) number_format((float) ($book['sale_price'] ?? 0), 2, '.', ''),
            ]];
        }

        return [];
    }

    private function currentLocalDateTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::SALE_TIMEZONE)))
            ->format('Y-m-d H:i:s');
    }

    private function convertLocalDateTimeToUtc(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }

        try {
            $date = new \DateTimeImmutable($normalized, new \DateTimeZone(self::SALE_TIMEZONE));

            return $date
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    /**
     * @param array<int, string> $errors
     * @return array<string, string>
     */
    private function buildFieldErrors(array $errors): array
    {
        $fieldErrors = [];

        foreach ($errors as $error) {
            $normalized = mb_strtolower(trim($error));

            if ($normalized === '') {
                continue;
            }

            if (str_contains($normalized, 'campo "valor recebido"') || str_contains($normalized, 'valor recebido em dinheiro')) {
                $fieldErrors['received_amount'] = $error;
                continue;
            }

            if (str_contains($normalized, 'e-mail válido')) {
                $fieldErrors['customer_email'] = $error;
                continue;
            }

            if (str_contains($normalized, 'telefone válido')) {
                $fieldErrors['customer_phone'] = $error;
                continue;
            }

            if (str_contains($normalized, 'cpf válido')) {
                $fieldErrors['customer_cpf'] = $error;
                continue;
            }
        }

        return $fieldErrors;
    }

    private function normalizePhoneInput(mixed $value): string
    {
        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    private function normalizeCpfInput(mixed $value): string
    {
        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    private function isValidCustomerPhone(string $digits): bool
    {
        $length = strlen($digits);

        return $length === 10 || $length === 11;
    }

    private function isValidCpf(string $digits): bool
    {
        if (!preg_match('/^\d{11}$/', $digits)) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $digits[$index]) * (10 - $index);
        }

        $remainder = $sum % 11;
        $digitOne = $remainder < 2 ? 0 : 11 - $remainder;

        if ($digitOne !== (int) $digits[9]) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $sum += ((int) $digits[$index]) * (11 - $index);
        }

        $remainder = $sum % 11;
        $digitTwo = $remainder < 2 ? 0 : 11 - $remainder;

        return $digitTwo === (int) $digits[10];
    }

    private function formatPhoneForDisplay(string $value): string
    {
        $digits = $this->normalizePhoneInput($value);

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $value;
    }

    private function formatCpfForDisplay(string $value): string
    {
        $digits = $this->normalizeCpfInput($value);

        if (strlen($digits) === 11) {
            return sprintf(
                '%s.%s.%s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
                substr($digits, 9, 2)
            );
        }

        return $value;
    }

    private function formatMoneyForDisplay(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        return number_format((float) $normalized, 2, ',', '');
    }
}
