<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class MemberCompleteProfilePageAction extends AbstractMemberGuardedPageAction
{
    private const FLASH_KEY = 'member_complete_profile';
    private const PRIVACY_NOTICE_VERSION = 'member-profile-privacy-v1';

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig, $memberAuthRepository);
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $member = $this->resolveAuthenticatedMember($response, false);

        if ($member instanceof Response) {
            return $member;
        }

        $memberId = (int) ($member['id'] ?? 0);
        $queryParams = $request->getQueryParams();
        $redirectTo = $this->sanitizeRedirectTarget((string) ($queryParams['redirect_to'] ?? ''));

        $errors = [];
        $warnings = [];
        $privacyNoticeAcceptedAt = trim((string) ($member['privacy_notice_accepted_at'] ?? ''));
        $privacyNoticeVersion = trim((string) ($member['privacy_notice_version'] ?? ''));
        $privacyNoticeAlreadyAccepted = $privacyNoticeAcceptedAt !== '';

        $existingBirthPlace = trim((string) ($member['birth_place'] ?? ''));
        $existingBirthState = '';
        $existingBirthCity = '';
        if ($existingBirthPlace !== '' && str_contains($existingBirthPlace, '/')) {
            [$parsedCity, $parsedState] = array_pad(explode('/', $existingBirthPlace, 2), 2, '');
            $existingBirthCity = trim($parsedCity);
            $existingBirthState = strtoupper(trim($parsedState));
        }

        $form = [
            'full_name' => (string) ($member['full_name'] ?? ''),
            'email' => (string) ($member['email'] ?? ''),
            'phone_mobile' => (string) ($member['phone_mobile'] ?? ''),
            'phone_landline' => (string) ($member['phone_landline'] ?? ''),
            'birth_date' => (string) ($member['birth_date'] ?? ''),
            'birth_state' => $existingBirthState,
            'birth_city' => $existingBirthCity,
            'birth_place' => (string) ($member['birth_place'] ?? ''),
            'profile_photo_path' => (string) ($member['profile_photo_path'] ?? ''),
            'privacy_notice_acknowledged' => $privacyNoticeAlreadyAccepted ? '1' : '',
        ];

        if (strtoupper($request->getMethod()) !== 'POST') {
            $flash = $this->consumeSessionFlash(self::FLASH_KEY);
            $errors = array_values(array_filter(
                (array) ($flash['errors'] ?? []),
                static fn (mixed $error): bool => is_string($error) && trim($error) !== ''
            ));
            $warnings = array_values(array_filter(
                (array) ($flash['warnings'] ?? []),
                static fn (mixed $warning): bool => is_string($warning) && trim($warning) !== ''
            ));
            $flashForm = (array) ($flash['form'] ?? []);
            if ($flashForm !== []) {
                $form = array_merge($form, [
                    'full_name' => trim((string) ($flashForm['full_name'] ?? $form['full_name'])),
                    'email' => (string) ($flashForm['email'] ?? $form['email']),
                    'phone_mobile' => trim((string) ($flashForm['phone_mobile'] ?? $form['phone_mobile'])),
                    'phone_landline' => trim((string) ($flashForm['phone_landline'] ?? $form['phone_landline'])),
                    'birth_date' => trim((string) ($flashForm['birth_date'] ?? $form['birth_date'])),
                    'birth_state' => strtoupper(trim((string) ($flashForm['birth_state'] ?? $form['birth_state']))),
                    'birth_city' => trim((string) ($flashForm['birth_city'] ?? $form['birth_city'])),
                    'birth_place' => trim((string) ($flashForm['birth_place'] ?? $form['birth_place'])),
                    'profile_photo_path' => (string) ($flashForm['profile_photo_path'] ?? $form['profile_photo_path']),
                    'privacy_notice_acknowledged' => (string) ($flashForm['privacy_notice_acknowledged'] ?? $form['privacy_notice_acknowledged']),
                ]);
            }
            $redirectTo = $this->sanitizeRedirectTarget((string) ($flash['redirect_to'] ?? $redirectTo));
        }

        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $redirectTo = $this->sanitizeRedirectTarget((string) ($body['redirect_to'] ?? $redirectTo));
            $form['full_name'] = trim((string) ($body['full_name'] ?? ''));
            $form['phone_mobile'] = trim((string) ($body['phone_mobile'] ?? ''));
            $form['phone_landline'] = trim((string) ($body['phone_landline'] ?? ''));
            $form['birth_date'] = trim((string) ($body['birth_date'] ?? ''));
            $form['birth_state'] = strtoupper(trim((string) ($body['birth_state'] ?? '')));
            $form['birth_city'] = trim((string) ($body['birth_city'] ?? ''));
            $form['birth_place'] = trim((string) ($body['birth_place'] ?? ''));
            $form['privacy_notice_acknowledged'] = (($body['privacy_notice_acknowledged'] ?? '') === '1') ? '1' : '';

            if ($form['full_name'] === '') {
                $errors[] = 'Informe seu nome completo.';
            }

            $mobileDigits = preg_replace('/\D+/', '', $form['phone_mobile']);
            if ($mobileDigits === null || strlen($mobileDigits) < 10 || strlen($mobileDigits) > 11) {
                $errors[] = 'Informe um celular válido com DDD.';
            }

            if ($form['phone_landline'] !== '') {
                $landlineDigits = preg_replace('/\D+/', '', $form['phone_landline']);
                if ($landlineDigits === null || strlen($landlineDigits) !== 10) {
                    $errors[] = 'Informe um telefone fixo válido no formato (84) 3210-1234 ou deixe em branco.';
                }
            }

            if ($form['birth_date'] === '') {
                $errors[] = 'Informe sua data de nascimento.';
            } else {
                $birthDate = \DateTimeImmutable::createFromFormat('Y-m-d', $form['birth_date']);
                $dateIsValid = $birthDate instanceof \DateTimeImmutable
                    && $birthDate->format('Y-m-d') === $form['birth_date'];

                if (!$dateIsValid) {
                    $errors[] = 'Informe uma data de nascimento válida.';
                } else {
                    $now = new \DateTimeImmutable('today');
                    if ($birthDate > $now || (int) $birthDate->format('Y') < 1900) {
                        $errors[] = 'Informe uma data de nascimento realista.';
                    }
                }
            }

            if ($form['birth_state'] === '') {
                $errors[] = 'Selecione a UF de nascimento.';
            } elseif (!preg_match('/^[A-Z]{2}$/', $form['birth_state'])) {
                $errors[] = 'UF de nascimento inválida.';
            }

            if ($form['birth_city'] === '') {
                $errors[] = 'Selecione a cidade de nascimento.';
            } elseif (mb_strlen($form['birth_city']) > 120) {
                $errors[] = 'A cidade de nascimento deve ter no máximo 120 caracteres.';
            }

            $composedBirthPlace = trim($form['birth_city']) !== '' && trim($form['birth_state']) !== ''
                ? sprintf('%s/%s', trim($form['birth_city']), strtoupper(trim($form['birth_state'])))
                : trim($form['birth_place']);

            if ($composedBirthPlace === '') {
                $errors[] = 'Não foi possível definir a naturalidade.';
            } elseif (mb_strlen($composedBirthPlace) > 140) {
                $errors[] = 'A naturalidade deve ter no máximo 140 caracteres.';
            }

            $uploadedFiles = $request->getUploadedFiles();
            $photoUpload = $uploadedFiles['profile_photo'] ?? null;
            $photoPath = $form['profile_photo_path'];

            if ($photoUpload instanceof UploadedFileInterface && $photoUpload->getError() !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $this->storeProfilePhoto($photoUpload);

                if (!empty($uploadResult['error'])) {
                    $errors[] = (string) $uploadResult['error'];
                } elseif (!empty($uploadResult['warning'])) {
                    if (trim($photoPath) === '') {
                        $errors[] = 'Não foi possível salvar sua foto agora. Tente novamente em instantes.';
                    } else {
                        $warnings[] = (string) $uploadResult['warning'];
                    }
                } elseif (!empty($uploadResult['path'])) {
                    $photoPath = (string) $uploadResult['path'];
                }
            }

            if (trim($photoPath) === '') {
                $errors[] = 'Envie uma foto de perfil para concluir seu cadastro.';
            }

            if (!$privacyNoticeAlreadyAccepted && $form['privacy_notice_acknowledged'] !== '1') {
                $errors[] = 'Confirme a ciência do aviso de privacidade para concluir seu cadastro.';
            }

            if (empty($errors)) {
                $acceptedNoticeVersion = $privacyNoticeAlreadyAccepted
                    ? ($privacyNoticeVersion !== '' ? $privacyNoticeVersion : self::PRIVACY_NOTICE_VERSION)
                    : self::PRIVACY_NOTICE_VERSION;
                $acceptedNoticeAt = $privacyNoticeAlreadyAccepted
                    ? $privacyNoticeAcceptedAt
                    : date('Y-m-d H:i:s');

                try {
                    $this->memberAuthRepository->updateProfile($memberId, [
                        'full_name' => $form['full_name'],
                        'phone_mobile' => $form['phone_mobile'],
                        'phone_landline' => $form['phone_landline'],
                        'birth_date' => $form['birth_date'],
                        'birth_place' => $composedBirthPlace,
                        'profile_photo_path' => $photoPath,
                        'privacy_notice_version' => $acceptedNoticeVersion,
                        'privacy_notice_accepted_at' => $acceptedNoticeAt,
                        'profile_completed' => 1,
                    ]);
                } catch (Throwable $exception) {
                    $this->logger->error('Falha ao atualizar perfil de membro.', [
                        'member_id' => $memberId,
                        'exception' => $exception,
                    ]);
                    $errors[] = 'Não foi possível salvar o perfil no momento. Tente novamente em instantes.';
                }
            }

            if (empty($errors)) {
                $form['profile_photo_path'] = $photoPath;

                $_SESSION['member_name'] = $form['full_name'];
                $_SESSION['member_profile_photo_path'] = $photoPath;

                if (!empty($warnings)) {
                    $this->storeSessionFlash($this->resolvePostSaveFlashKey($redirectTo), [
                        'status' => 'profile-updated-no-photo',
                    ]);

                    return $response->withHeader('Location', $redirectTo)->withStatus(303);
                }

                $this->storeSessionFlash($this->resolvePostSaveFlashKey($redirectTo), [
                    'status' => 'profile-updated',
                ]);

                return $response->withHeader('Location', $redirectTo)->withStatus(303);
            }

            $this->storeSessionFlash(self::FLASH_KEY, [
                'errors' => $errors,
                'warnings' => $warnings,
                'form' => [
                    'full_name' => $form['full_name'],
                    'email' => $form['email'],
                    'phone_mobile' => $form['phone_mobile'],
                    'phone_landline' => $form['phone_landline'],
                    'birth_date' => $form['birth_date'],
                    'birth_state' => $form['birth_state'],
                    'birth_city' => $form['birth_city'],
                    'birth_place' => $form['birth_place'],
                    'profile_photo_path' => $form['profile_photo_path'],
                    'privacy_notice_acknowledged' => $form['privacy_notice_acknowledged'],
                ],
                'redirect_to' => $redirectTo,
            ]);

            $profileRedirect = '/membro/perfil/completar';
            if ($redirectTo !== '/membro') {
                $profileRedirect .= '?redirect_to=' . rawurlencode($redirectTo);
            }

            return $response->withHeader('Location', $profileRedirect)->withStatus(303);
        }

        return $this->renderPage($response, 'pages/member-complete-profile.twig', [
            'member_profile_errors' => $errors,
            'member_profile_warnings' => $warnings,
            'member_profile_form' => $form,
            'member_profile_redirect_to' => $redirectTo,
            'member_profile_privacy_notice_required' => !$privacyNoticeAlreadyAccepted,
            'member_profile_privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
            'member_profile_privacy_notice_acknowledged_at' => $privacyNoticeAcceptedAt,
            'page_title' => 'Completar Perfil | CEDE',
            'page_url' => 'https://cedern.org/membro/perfil/completar',
            'page_description' => 'Complete seus dados de contato para liberar a área de membro.',
        ]);
    }

    private function sanitizeRedirectTarget(string $redirectTo): string
    {
        $redirectTo = trim($redirectTo);

        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            return '/membro';
        }

        return $redirectTo;
    }

    private function resolvePostSaveFlashKey(string $redirectTo): string
    {
        return str_starts_with($redirectTo, '/agenda/')
            ? AgendaDetailPageAction::FLASH_KEY
            : MemberHomePageAction::FLASH_KEY;
    }

    /**
     * @return array{path?: string, error?: string, warning?: string}
     */
    private function storeProfilePhoto(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['error' => 'Não foi possível enviar a foto. Tente novamente.'];
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (2 * 1024 * 1024)) {
            return ['error' => 'A foto deve ter no máximo 2MB.'];
        }

        $mimeType = strtolower((string) $file->getClientMediaType());
        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensionByMime[$mimeType])) {
            return ['error' => 'Formato de foto inválido. Use JPG, PNG ou WEBP.'];
        }

        $projectRoot = dirname(__DIR__, 4);
        $candidateDirectories = [
            $projectRoot . '/public/assets/img/member-photos',
            $projectRoot . '/public/assets/img/avatar',
        ];

        $targetDirectory = null;
        $directoryDiagnostics = [];
        foreach ($candidateDirectories as $directory) {
            $beforeExists = is_dir($directory);

            if (!is_dir($directory)) {
                if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                    $directoryDiagnostics[] = [
                        'path' => $directory,
                        'exists' => $beforeExists,
                        'exists_after_mkdir' => is_dir($directory),
                        'writable' => is_writable($directory),
                        'permissions' => is_dir($directory)
                            ? substr(sprintf('%o', (int) @fileperms($directory)), -4)
                            : null,
                    ];
                    continue;
                }
            }

            $directoryDiagnostics[] = [
                'path' => $directory,
                'exists' => $beforeExists,
                'exists_after_mkdir' => is_dir($directory),
                'writable' => is_writable($directory),
                'permissions' => is_dir($directory) ? substr(sprintf('%o', (int) @fileperms($directory)), -4) : null,
            ];

            if (is_writable($directory)) {
                $targetDirectory = $directory;
                break;
            }
        }

        if ($targetDirectory === null) {
            $uploadTmpDir = (string) ini_get('upload_tmp_dir');
            $effectiveTmpDir = $uploadTmpDir !== '' ? $uploadTmpDir : sys_get_temp_dir();

            $this->logger->warning('Diretório de upload de foto indisponível.', [
                'candidate_directories' => $directoryDiagnostics,
                'upload_tmp_dir' => $uploadTmpDir,
                'effective_tmp_dir' => $effectiveTmpDir,
                'effective_tmp_dir_writable' => is_dir($effectiveTmpDir)
                    && is_writable($effectiveTmpDir),
            ]);

            return [
                'warning' => 'Não foi possível salvar a foto agora por permissão do servidor. '
                    . 'Seus outros dados foram atualizados.',
            ];
        }

        try {
            $timestamp = date('YmdHis');
            $randomSuffix = bin2hex(random_bytes(4));
            $extension = $extensionByMime[$mimeType];
            $fileName = sprintf('member_%s_%s.%s', $timestamp, $randomSuffix, $extension);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gerar nome para foto de perfil.', [
                'exception' => $exception,
            ]);

            return ['error' => 'Falha ao processar a foto enviada.'];
        }
        $targetPath = $targetDirectory . '/' . $fileName;

        try {
            $file->moveTo($targetPath);
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao gravar foto de perfil do membro.', [
                'exception' => $exception,
                'target_directory' => $targetDirectory,
                'target_directory_writable' => is_writable($targetDirectory),
                'target_directory_permissions' => substr(sprintf('%o', (int) @fileperms($targetDirectory)), -4),
                'target_path' => $targetPath,
            ]);

            return ['warning' => 'Não foi possível salvar a foto agora. Seus outros dados foram atualizados.'];
        }

        $relativePath = str_starts_with($targetDirectory, $projectRoot . '/public/')
            ? substr($targetDirectory, strlen($projectRoot . '/public/')) . '/' . $fileName
            : 'assets/img/member-photos/' . $fileName;

        return ['path' => ltrim($relativePath, '/')];
    }
}
