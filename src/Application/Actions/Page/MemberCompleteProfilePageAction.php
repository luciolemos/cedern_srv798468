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

        $errors = [];
        $warnings = [];

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
                ]);
            }
        }

        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $form['full_name'] = trim((string) ($body['full_name'] ?? ''));
            $form['phone_mobile'] = trim((string) ($body['phone_mobile'] ?? ''));
            $form['phone_landline'] = trim((string) ($body['phone_landline'] ?? ''));
            $form['birth_date'] = trim((string) ($body['birth_date'] ?? ''));
            $form['birth_state'] = strtoupper(trim((string) ($body['birth_state'] ?? '')));
            $form['birth_city'] = trim((string) ($body['birth_city'] ?? ''));
            $form['birth_place'] = trim((string) ($body['birth_place'] ?? ''));

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
                    $warnings[] = (string) $uploadResult['warning'];
                } elseif (!empty($uploadResult['path'])) {
                    $photoPath = (string) $uploadResult['path'];
                }
            }

            if (empty($errors)) {
                try {
                    $this->memberAuthRepository->updateProfile($memberId, [
                        'full_name' => $form['full_name'],
                        'phone_mobile' => $form['phone_mobile'],
                        'phone_landline' => $form['phone_landline'],
                        'birth_date' => $form['birth_date'],
                        'birth_place' => $composedBirthPlace,
                        'profile_photo_path' => $photoPath,
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
                    $this->storeSessionFlash(MemberHomePageAction::FLASH_KEY, [
                        'status' => 'profile-updated-no-photo',
                    ]);

                    return $response
                        ->withHeader('Location', '/membro')
                        ->withStatus(303);
                }

                $this->storeSessionFlash(MemberHomePageAction::FLASH_KEY, [
                    'status' => 'profile-updated',
                ]);

                return $response->withHeader('Location', '/membro')->withStatus(303);
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
                ],
            ]);

            return $response->withHeader('Location', '/membro/perfil/completar')->withStatus(303);
        }

        return $this->renderPage($response, 'pages/member-complete-profile.twig', [
            'member_profile_errors' => $errors,
            'member_profile_warnings' => $warnings,
            'member_profile_form' => $form,
            'page_title' => 'Completar Perfil | CEDE',
            'page_url' => 'https://cedern.org/membro/perfil/completar',
            'page_description' => 'Complete seus dados de contato para liberar a área de membro.',
        ]);
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
