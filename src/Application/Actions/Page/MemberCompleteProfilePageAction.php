<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use App\Support\ContributionParticipation;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class MemberCompleteProfilePageAction extends AbstractMemberGuardedPageAction
{
    use MemberProfilePhotoStorageTrait;

    private const FLASH_KEY = 'member_complete_profile';
    private const PRIVACY_NOTICE_VERSION = 'member-profile-privacy-v1';
    private const BRAZIL_STATE_OPTIONS = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];
    private const PAYMENT_METHOD_OPTIONS = [
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'pix_automatico' => 'Pix Automático',
        'manual' => 'Pagamento manual',
    ];

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
        $associationStatus = strtolower(trim((string) ($member['association_status'] ?? '')));
        if (!in_array($associationStatus, ['applicant', 'member', 'former'], true)) {
            $associationStatus = strtolower(trim((string) ($member['status'] ?? ''))) === 'pending'
                ? 'applicant'
                : 'member';
        }
        $isContributor = ContributionParticipation::isParticipating($member['is_contributor'] ?? null);
        $contributorLabel = ContributionParticipation::label($member['is_contributor'] ?? null);
        $requiresContributionConfiguration = $associationStatus === 'member' && $isContributor;
        $existingPreferredDueDay = ($member['preferred_due_day'] ?? null) !== null
            ? (int) $member['preferred_due_day']
            : null;
        $existingContributionAmount = ($member['contribution_amount'] ?? null) !== null
            ? (string) $member['contribution_amount']
            : null;
        $existingContributionPlanLabel = trim((string) ($member['contribution_plan_label'] ?? ''));
        $existingPreferredPaymentMethod = trim((string) ($member['preferred_payment_method'] ?? ''));
        $existingBillingEmailOptIn = (int) ($member['billing_email_opt_in'] ?? 0) === 1 ? '1' : '';
        $existingBillingWhatsappOptIn = (int) ($member['billing_whatsapp_opt_in'] ?? 0) === 1 ? '1' : '';

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
            'cpf' => (string) ($member['cpf'] ?? ''),
            'postal_code' => (string) ($member['postal_code'] ?? ''),
            'street_address' => (string) ($member['street_address'] ?? ''),
            'address_number' => (string) ($member['address_number'] ?? ''),
            'address_complement' => (string) ($member['address_complement'] ?? ''),
            'neighborhood' => (string) ($member['neighborhood'] ?? ''),
            'address_city' => (string) ($member['address_city'] ?? ''),
            'address_state' => (string) ($member['address_state'] ?? ''),
            'preferred_due_day' => $existingPreferredDueDay !== null ? (string) $existingPreferredDueDay : '',
            'contribution_amount' => $this->formatCurrencyInput((string) ($existingContributionAmount ?? '')),
            'contribution_plan_label' => $existingContributionPlanLabel,
            'preferred_payment_method' => $existingPreferredPaymentMethod,
            'billing_email_opt_in' => $existingBillingEmailOptIn,
            'billing_whatsapp_opt_in' => $existingBillingWhatsappOptIn,
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
                    'cpf' => trim((string) ($flashForm['cpf'] ?? $form['cpf'])),
                    'postal_code' => trim((string) ($flashForm['postal_code'] ?? $form['postal_code'])),
                    'street_address' => trim((string) ($flashForm['street_address'] ?? $form['street_address'])),
                    'address_number' => trim((string) ($flashForm['address_number'] ?? $form['address_number'])),
                    'address_complement' => trim((string) ($flashForm['address_complement'] ?? $form['address_complement'])),
                    'neighborhood' => trim((string) ($flashForm['neighborhood'] ?? $form['neighborhood'])),
                    'address_city' => trim((string) ($flashForm['address_city'] ?? $form['address_city'])),
                    'address_state' => strtoupper(trim((string) ($flashForm['address_state'] ?? $form['address_state']))),
                    'preferred_due_day' => trim((string) ($flashForm['preferred_due_day'] ?? $form['preferred_due_day'])),
                    'contribution_amount' => trim((string) ($flashForm['contribution_amount'] ?? $form['contribution_amount'])),
                    'contribution_plan_label' => trim((string) ($flashForm['contribution_plan_label'] ?? $form['contribution_plan_label'])),
                    'preferred_payment_method' => trim((string) ($flashForm['preferred_payment_method'] ?? $form['preferred_payment_method'])),
                    'billing_email_opt_in' => (string) ($flashForm['billing_email_opt_in'] ?? $form['billing_email_opt_in']),
                    'billing_whatsapp_opt_in' => (string) ($flashForm['billing_whatsapp_opt_in'] ?? $form['billing_whatsapp_opt_in']),
                    'privacy_notice_acknowledged' => (string) ($flashForm['privacy_notice_acknowledged'] ?? $form['privacy_notice_acknowledged']),
                ]);
            }
            $redirectTo = $this->sanitizeRedirectTarget((string) ($flash['redirect_to'] ?? $redirectTo));
        }

        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array) ($request->getParsedBody() ?? []);
            $redirectTo = $this->sanitizeRedirectTarget((string) ($body['redirect_to'] ?? $redirectTo));
            $existingPhotoPath = trim((string) ($member['profile_photo_path'] ?? ''));
            $newPhotoPath = '';
            $form['full_name'] = trim((string) ($body['full_name'] ?? ''));
            $form['phone_mobile'] = trim((string) ($body['phone_mobile'] ?? ''));
            $form['phone_landline'] = trim((string) ($body['phone_landline'] ?? ''));
            $form['birth_date'] = trim((string) ($body['birth_date'] ?? ''));
            $form['birth_state'] = strtoupper(trim((string) ($body['birth_state'] ?? '')));
            $form['birth_city'] = trim((string) ($body['birth_city'] ?? ''));
            $form['birth_place'] = trim((string) ($body['birth_place'] ?? ''));
            $form['cpf'] = trim((string) ($body['cpf'] ?? ''));
            $form['postal_code'] = trim((string) ($body['postal_code'] ?? ''));
            $form['street_address'] = trim((string) ($body['street_address'] ?? ''));
            $form['address_number'] = trim((string) ($body['address_number'] ?? ''));
            $form['address_complement'] = trim((string) ($body['address_complement'] ?? ''));
            $form['neighborhood'] = trim((string) ($body['neighborhood'] ?? ''));
            $form['address_city'] = trim((string) ($body['address_city'] ?? ''));
            $form['address_state'] = strtoupper(trim((string) ($body['address_state'] ?? '')));
            if ($requiresContributionConfiguration) {
                $form['preferred_due_day'] = trim((string) ($body['preferred_due_day'] ?? ''));
                $form['preferred_payment_method'] = trim((string) ($body['preferred_payment_method'] ?? ''));
            } else {
                $form['preferred_due_day'] = $existingPreferredDueDay !== null ? (string) $existingPreferredDueDay : '';
                $form['preferred_payment_method'] = $existingPreferredPaymentMethod;
            }
            if ($requiresContributionConfiguration) {
                $form['billing_email_opt_in'] = (($body['billing_email_opt_in'] ?? '') === '1') ? '1' : '';
                $form['billing_whatsapp_opt_in'] = (($body['billing_whatsapp_opt_in'] ?? '') === '1') ? '1' : '';
            } else {
                $form['billing_email_opt_in'] = $existingBillingEmailOptIn;
                $form['billing_whatsapp_opt_in'] = $existingBillingWhatsappOptIn;
            }
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

            $cpfDigits = preg_replace('/\D+/', '', $form['cpf']) ?? '';
            if (!$this->isValidCpf($cpfDigits)) {
                $errors[] = 'Informe um CPF válido.';
            } else {
                $form['cpf'] = $this->formatCpf($cpfDigits);

                $existingCpfOwner = $this->memberAuthRepository->findByCpf($cpfDigits, $memberId);
                if ($existingCpfOwner !== null) {
                    $errors[] = 'Este CPF já está vinculado a outro usuário SISCEDE.';
                }
            }

            $postalCodeDigits = preg_replace('/\D+/', '', $form['postal_code']) ?? '';
            if (strlen($postalCodeDigits) !== 8) {
                $errors[] = 'Informe um CEP válido.';
            } else {
                $form['postal_code'] = $this->formatPostalCode($postalCodeDigits);
            }

            if ($form['street_address'] === '') {
                $errors[] = 'Informe o logradouro.';
            } elseif (mb_strlen($form['street_address']) > 160) {
                $errors[] = 'O logradouro deve ter no máximo 160 caracteres.';
            }

            if ($form['address_number'] === '') {
                $errors[] = 'Informe o número do endereço.';
            } elseif (mb_strlen($form['address_number']) > 20) {
                $errors[] = 'O número do endereço deve ter no máximo 20 caracteres.';
            }

            if (mb_strlen($form['address_complement']) > 120) {
                $errors[] = 'O complemento deve ter no máximo 120 caracteres.';
            }

            if ($form['neighborhood'] === '') {
                $errors[] = 'Informe o bairro.';
            } elseif (mb_strlen($form['neighborhood']) > 120) {
                $errors[] = 'O bairro deve ter no máximo 120 caracteres.';
            }

            if ($form['address_city'] === '') {
                $errors[] = 'Informe a cidade.';
            } elseif (mb_strlen($form['address_city']) > 120) {
                $errors[] = 'A cidade deve ter no máximo 120 caracteres.';
            }

            if ($form['address_state'] === '') {
                $errors[] = 'Selecione a UF do endereço.';
            } elseif (!array_key_exists($form['address_state'], self::BRAZIL_STATE_OPTIONS)) {
                $errors[] = 'UF do endereço inválida.';
            }

            $preferredDueDayRaw = trim($form['preferred_due_day']);
            $preferredDueDay = $preferredDueDayRaw === '' ? null : (int) $preferredDueDayRaw;

            if ($requiresContributionConfiguration) {
                if ($preferredDueDay === null || $preferredDueDay < 1 || $preferredDueDay > 28) {
                    $errors[] = 'Selecione um dia de vencimento preferido entre 1 e 28.';
                }
            } elseif ($preferredDueDay !== null && ($preferredDueDay < 1 || $preferredDueDay > 28)) {
                $errors[] = 'Selecione um dia de vencimento preferido entre 1 e 28 ou deixe em branco.';
            }

            $preferredPaymentMethod = trim($form['preferred_payment_method']) !== ''
                ? $form['preferred_payment_method']
                : null;

            if (
                $preferredPaymentMethod !== null
                && !array_key_exists($preferredPaymentMethod, self::PAYMENT_METHOD_OPTIONS)
            ) {
                $errors[] = 'Selecione uma forma preferida de pagamento válida.';
            } elseif ($requiresContributionConfiguration && $preferredPaymentMethod === null) {
                $errors[] = 'Selecione a forma preferida de pagamento.';
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
                    $newPhotoPath = $photoPath;
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
                    $updated = $this->memberAuthRepository->updateProfile($memberId, [
                        'full_name' => $form['full_name'],
                        'phone_mobile' => $form['phone_mobile'],
                        'phone_landline' => $form['phone_landline'],
                        'birth_date' => $form['birth_date'],
                        'birth_place' => $composedBirthPlace,
                        'profile_photo_path' => $photoPath,
                        'cpf' => $cpfDigits,
                        'postal_code' => $postalCodeDigits,
                        'street_address' => $form['street_address'],
                        'address_number' => $form['address_number'],
                        'address_complement' => $form['address_complement'],
                        'neighborhood' => $form['neighborhood'],
                        'address_city' => $form['address_city'],
                        'address_state' => $form['address_state'],
                        'preferred_due_day' => $preferredDueDay,
                        'contribution_amount' => $existingContributionAmount,
                        'contribution_plan_label' => $existingContributionPlanLabel,
                        'preferred_payment_method' => $preferredPaymentMethod,
                        'billing_email_opt_in' => $form['billing_email_opt_in'] === '1' ? 1 : 0,
                        'billing_whatsapp_opt_in' => $form['billing_whatsapp_opt_in'] === '1' ? 1 : 0,
                        'privacy_notice_version' => $acceptedNoticeVersion,
                        'privacy_notice_accepted_at' => $acceptedNoticeAt,
                        'profile_completed' => 1,
                    ]);

                    if ($updated !== true) {
                        throw new \RuntimeException('Falha ao persistir atualização do perfil.');
                    }
                } catch (Throwable $exception) {
                    if ($newPhotoPath !== '') {
                        $this->deleteStoredMemberProfilePhotoIfManaged($newPhotoPath);
                    }

                    $this->logger->error('Falha ao atualizar perfil de membro.', [
                        'member_id' => $memberId,
                        'exception' => $exception,
                    ]);
                    $errors[] = str_contains($exception->getMessage(), 'CPF já vinculado')
                        ? 'Este CPF já está vinculado a outro usuário SISCEDE.'
                        : 'Não foi possível salvar o perfil no momento. Tente novamente em instantes.';
                }
            }

            if (empty($errors)) {
                if ($newPhotoPath !== '' && $existingPhotoPath !== '' && $existingPhotoPath !== $newPhotoPath) {
                    $this->deleteStoredMemberProfilePhotoIfManaged($existingPhotoPath);
                }

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

            if ($newPhotoPath !== '') {
                $this->deleteStoredMemberProfilePhotoIfManaged($newPhotoPath);
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
                    'cpf' => $form['cpf'],
                    'postal_code' => $form['postal_code'],
                    'street_address' => $form['street_address'],
                    'address_number' => $form['address_number'],
                    'address_complement' => $form['address_complement'],
                    'neighborhood' => $form['neighborhood'],
                    'address_city' => $form['address_city'],
                    'address_state' => $form['address_state'],
                    'preferred_due_day' => $form['preferred_due_day'],
                    'contribution_amount' => $form['contribution_amount'],
                    'contribution_plan_label' => $form['contribution_plan_label'],
                    'preferred_payment_method' => $form['preferred_payment_method'],
                    'billing_email_opt_in' => $form['billing_email_opt_in'],
                    'billing_whatsapp_opt_in' => $form['billing_whatsapp_opt_in'],
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
            'member_profile_state_options' => self::BRAZIL_STATE_OPTIONS,
            'member_profile_payment_method_options' => self::PAYMENT_METHOD_OPTIONS,
            'member_profile_association_status' => $associationStatus,
            'member_profile_association_status_label' => $this->resolveAssociationStatusLabel($associationStatus),
            'member_profile_is_contributor' => $isContributor,
            'member_profile_contributor_label' => $contributorLabel,
            'member_profile_requires_contribution' => $requiresContributionConfiguration,
            'member_profile_contribution_amount_display' => $form['contribution_amount'] !== '' ? $form['contribution_amount'] : 'Não definido',
            'member_profile_contribution_plan_label_display' => $form['contribution_plan_label'] !== '' ? $form['contribution_plan_label'] : 'Não definido',
            'member_profile_privacy_notice_required' => !$privacyNoticeAlreadyAccepted,
            'member_profile_privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
            'member_profile_privacy_notice_acknowledged_at' => $privacyNoticeAcceptedAt,
            'page_title' => 'Completar Perfil | CEDE',
            'page_url' => 'https://cedern.org/membro/perfil/completar',
            'page_description' => 'Complete seus dados cadastrais e, quando aplicável, financeiros para liberar a área de membro.',
        ]);
    }

    private function resolveAssociationStatusLabel(string $associationStatus): string
    {
        return match ($associationStatus) {
            'member' => 'Associado',
            'former' => 'Desligado',
            default => 'Solicitante',
        };
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

    private function formatCurrencyInput(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return '';
        }

        return number_format((float) $normalized, 2, ',', '.');
    }

    private function formatCpf(string $digits): string
    {
        if (strlen($digits) !== 11) {
            return $digits;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    private function formatPostalCode(string $digits): string
    {
        if (strlen($digits) !== 8) {
            return $digits;
        }

        return sprintf('%s-%s', substr($digits, 0, 5), substr($digits, 5, 3));
    }

    private function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += ((int) $digits[$index]) * (($position + 1) - $index);
            }

            $remainder = ($sum * 10) % 11;
            $digit = $remainder === 10 ? 0 : $remainder;
            if ($digit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
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

        $storage = $this->resolveWritableMemberProfilePhotoStorage();
        if ($storage === null) {
            $uploadTmpDir = (string) ini_get('upload_tmp_dir');
            $effectiveTmpDir = $uploadTmpDir !== '' ? $uploadTmpDir : sys_get_temp_dir();

            $this->logger->warning('Diretório de upload de foto indisponível.', [
                'candidate_directories' => $this->resolveMemberProfilePhotoStorageDiagnostics(),
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

        $targetDirectory = $storage['directory'];
        $publicPrefix = $storage['public_prefix'];

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

        return ['path' => $this->buildManagedMemberProfilePhotoRelativePath($fileName, $publicPrefix)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveMemberProfilePhotoStorageDiagnostics(): array
    {
        $diagnostics = [];

        foreach ($this->resolveMemberProfilePhotoStorageDefinitions() as $definition) {
            $directory = $definition['directory'];
            $diagnostics[] = [
                'path' => $directory,
                'public_prefix' => $definition['public_prefix'],
                'exists' => is_dir($directory),
                'writable' => is_dir($directory) && is_writable($directory),
                'permissions' => is_dir($directory) ? substr(sprintf('%o', (int) @fileperms($directory)), -4) : null,
            ];
        }

        return $diagnostics;
    }
}
