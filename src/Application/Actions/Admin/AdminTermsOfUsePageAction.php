<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Actions\Page\AbstractPageAction;
use App\Domain\Institutional\InstitutionalContentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AdminTermsOfUsePageAction extends AbstractPageAction
{
    private const DOCUMENT_SLUG = 'termos-de-uso';
    private const FORM_ACTION_PATH = '/painel/institucional/termos-de-uso';
    private const PUBLIC_PAGE_PATH = '/termos-de-uso';
    private const DEFAULT_CONTENT_PATH = '/app/content/terms-of-use.php';
    private const FLASH_KEY = 'admin_document_terms_of_use';

    private InstitutionalContentRepository $institutionalContentRepository;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        InstitutionalContentRepository $institutionalContentRepository
    ) {
        parent::__construct($logger, $twig);
        $this->institutionalContentRepository = $institutionalContentRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $method = strtoupper($request->getMethod());
        $status = '';
        $errors = [];

        $record = $this->loadDocumentRecord();

        if ($method !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $status = trim((string) ($flash['status'] ?? ''));
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));
            $flashForm = (array) ($flash['form'] ?? []);
            if ($flashForm !== []) {
                $record['title'] = trim((string) ($flashForm['title'] ?? $record['title']));
                $record['body'] = str_replace(
                    ["\r\n", "\r"],
                    "\n",
                    (string) ($flashForm['body'] ?? $record['body'])
                );
            }

            return $this->renderForm($response, $status, $record, $errors);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $payload = $this->normalizePayload($body);
        $errors = $this->validatePayload($payload);

        if (empty($errors)) {
            try {
                $saved = $this->institutionalContentRepository->upsertBySlug(
                    self::DOCUMENT_SLUG,
                    (string) $payload['title'],
                    (string) $payload['body'],
                    $this->resolveEditorMemberId()
                );

                if ($saved) {
                    $this->storeSessionFlash(self::FLASH_KEY, [
                        'status' => 'saved',
                        'errors' => [],
                        'form' => [],
                    ]);

                    return $response->withHeader('Location', self::FORM_ACTION_PATH)->withStatus(303);
                }

                $errors[] = 'Não foi possível salvar os Termos de Uso. Tente novamente.';
            } catch (\Throwable $exception) {
                $this->logger->warning('Falha ao salvar termos de uso no painel.', [
                    'error' => $exception->getMessage(),
                ]);

                $errors[] = 'Erro ao salvar os Termos de Uso. Verifique a conexão e tente novamente.';
            }
        }

        $this->storeSessionFlash(self::FLASH_KEY, [
            'status' => 'save-error',
            'errors' => $errors,
            'form' => [
                'title' => (string) $payload['title'],
                'body' => (string) $payload['body'],
            ],
        ]);

        return $response->withHeader('Location', self::FORM_ACTION_PATH)->withStatus(303);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function normalizePayload(array $input): array
    {
        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'body' => trim(str_replace(["\r\n", "\r"], "\n", (string) ($input['body'] ?? ''))),
        ];
    }

    /**
     * @param array<string, string> $payload
     * @return array<int, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ($payload['title'] === '') {
            $errors[] = 'Informe o título da página de Termos de Uso.';
        }

        if ($payload['body'] === '') {
            $errors[] = 'Informe o conteúdo dos Termos de Uso.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDocumentRecord(): array
    {
        $defaults = $this->loadDefaultDocument();
        $record = [
            'title' => $defaults['title'],
            'body' => $defaults['body'],
            'updated_at_label' => '',
            'updated_by_member_id' => null,
        ];

        try {
            $storedContent = $this->institutionalContentRepository->findBySlug(self::DOCUMENT_SLUG);

            if ($storedContent !== null && trim((string) ($storedContent['body'] ?? '')) !== '') {
                $record['title'] = trim((string) ($storedContent['title'] ?? $record['title']));
                $record['body'] = str_replace(
                    ["\r\n", "\r"],
                    "\n",
                    (string) ($storedContent['body'] ?? $record['body'])
                );

                $record['updated_by_member_id'] = $storedContent['updated_by_member_id'] ?? null;
                $record['updated_at_label'] = $this->formatDateTimeLabel((string) ($storedContent['updated_at'] ?? ''));
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar termos de uso no painel.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $record;
    }

    /**
     * @return array<string, string>
     */
    private function loadDefaultDocument(): array
    {
        $defaultsPath = dirname(__DIR__, 4) . self::DEFAULT_CONTENT_PATH;
        $defaults = [];

        if (is_file($defaultsPath) && is_readable($defaultsPath)) {
            $loaded = require $defaultsPath;

            if (is_array($loaded)) {
                $defaults = $loaded;
            }
        }

        return [
            'title' => trim((string) ($defaults['title'] ?? 'Termos de Uso')),
            'body' => str_replace(["\r\n", "\r"], "\n", (string) ($defaults['body'] ?? '')),
        ];
    }

    private function resolveEditorMemberId(): ?int
    {
        $memberId = (int) ($_SESSION['member_user_id'] ?? 0);

        return $memberId > 0 ? $memberId : null;
    }

    private function formatDateTimeLabel(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $dateTime = new \DateTimeImmutable($value);

            return $dateTime->format('d/m/Y H:i');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string> $errors
     */
    private function renderForm(Response $response, string $status, array $record, array $errors): Response
    {
        return $this->renderPage($response, 'pages/admin-institutional-document.twig', [
            'admin_status' => $status,
            'institution_document_form' => [
                'title' => (string) ($record['title'] ?? ''),
                'body' => (string) ($record['body'] ?? ''),
            ],
            'institution_document_record' => [
                'updated_at_label' => (string) ($record['updated_at_label'] ?? ''),
                'updated_by_member_id' => $record['updated_by_member_id'] ?? null,
            ],
            'institution_document_form_errors' => $errors,
            'admin_document_label' => 'Termos de Uso',
            'admin_document_form_action' => self::FORM_ACTION_PATH,
            'admin_document_public_url' => self::PUBLIC_PAGE_PATH,
            'admin_document_dashboard_title' => 'Termos de Uso',
            'admin_document_dashboard_lead' => 'Edite os Termos de Uso exibidos para visitantes em /termos-de-uso.',
            'admin_document_saved_message' => 'Termos de Uso atualizados com sucesso.',
            'admin_document_error_message' => 'Não foi possível salvar os Termos de Uso.',
            'page_title' => 'Editar Termos de Uso | Painel CEDE',
            'page_url' => 'https://cedern.org' . self::FORM_ACTION_PATH,
            'page_description' => 'Edição dos Termos de Uso exibidos no site público.',
        ]);
    }
}
