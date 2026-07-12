<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Member\MemberAuthRepository;
use App\Support\ContributionParticipation;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class MemberHomePageAction extends AbstractMemberGuardedPageAction
{
    public const FLASH_KEY = 'member_home';
    private const BIRTHDAY_TIMEZONE = 'America/Fortaleza';
    private const CONTRIBUTION_HISTORY_FETCH_LIMIT = 240;
    private const DEFAULT_CONTRIBUTION_HISTORY_SORT = 'competence_desc';
    private const PAYMENT_METHOD_LABELS = [
        'boleto' => 'Boleto',
        'pix' => 'Pix',
        'pix_automatico' => 'Pix Automático',
        'manual' => 'Pagamento manual',
    ];
    private const CONTRIBUTION_HISTORY_SORT_OPTIONS = [
        'competence_desc' => 'Competência mais recente',
        'competence_asc' => 'Competência mais antiga',
    ];

    private AgendaRepository $agendaRepository;

    public function __construct(
        LoggerInterface $logger,
        Twig $twig,
        MemberAuthRepository $memberAuthRepository,
        AgendaRepository $agendaRepository
    ) {
        parent::__construct($logger, $twig, $memberAuthRepository);
        $this->agendaRepository = $agendaRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $member = $this->resolveAuthenticatedMember($response, true);

        if ($member instanceof Response) {
            return $member;
        }

        $member = $this->withAssociationLabels($member);
        $member = $this->withFinancialSummary($member);
        $member = $this->withDisplayFields($member);

        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $roleKey = (string) ($member['role_key'] ?? 'member');
        $memberId = (int) ($member['id'] ?? 0);
        $queryParams = $request->getQueryParams();

        $onboardingChecklist = [
            [
                'label' => 'Nome completo preenchido',
                'description' => 'Esse dado identifica você no painel, no menu do membro e nos fluxos internos do CEDE.',
                'done' => trim((string) ($member['full_name'] ?? '')) !== '',
            ],
            [
                'label' => 'Celular informado',
                'description' => 'Seu celular facilita contato rápido, avisos institucionais e lembretes importantes.',
                'done' => trim((string) ($member['phone_mobile'] ?? '')) !== '',
            ],
            [
                'label' => 'Naturalidade informada',
                'description' => 'A naturalidade mantém seu cadastro institucional mais completo e consistente.',
                'done' => trim((string) ($member['birth_place'] ?? '')) !== '',
            ],
            [
                'label' => 'Foto de perfil definida',
                'description' => 'A foto melhora sua identificação visual nas áreas internas e na navegação da conta.',
                'done' => trim((string) ($member['profile_photo_path'] ?? '')) !== '',
            ],
        ];

        $onboardingCompleted = 0;
        foreach ($onboardingChecklist as $item) {
            if (!empty($item['done'])) {
                $onboardingCompleted++;
            }
        }
        $onboardingTotal = count($onboardingChecklist);
        $onboardingPercent = (int) round(($onboardingCompleted / $onboardingTotal) * 100);
        $onboardingPendingCount = max($onboardingTotal - $onboardingCompleted, 0);
        $nextPendingOnboardingItem = null;
        foreach ($onboardingChecklist as $item) {
            if (empty($item['done'])) {
                $nextPendingOnboardingItem = $item;
                break;
            }
        }

        $onboardingStatusTone = 'is-progress';
        $onboardingStatusLabel = 'Em andamento';
        $onboardingHeadline = 'Sua experiência na área do membro ainda pode evoluir.';
        $onboardingDescription = 'Conclua as etapas pendentes para ativar um cadastro mais completo e deixar sua presença no painel mais consistente.';
        $onboardingSecondaryAction = [
            'label' => 'Ver agenda',
            'href' => '/agenda',
        ];
        $onboardingBenefits = [
            'Mais clareza de identificação no menu e nas áreas internas.',
            'Contato institucional mais ágil quando a equipe precisar falar com você.',
            'Cadastro mais completo para acompanhar sua jornada como associado.',
        ];

        if ($onboardingCompleted === 0) {
            $onboardingStatusLabel = 'Começando agora';
            $onboardingHeadline = 'Seu cadastro ainda não avançou nas etapas principais.';
            $onboardingDescription = 'Vale começar pelo perfil para registrar seus dados essenciais e evitar lacunas logo no primeiro acesso.';
        } elseif ($onboardingPendingCount === 1 && is_array($nextPendingOnboardingItem)) {
            $onboardingStatusTone = 'is-almost-done';
            $onboardingStatusLabel = 'Quase concluído';
            $onboardingHeadline = 'Falta só um ajuste para fechar seu onboarding.';
            $onboardingDescription = 'Concluir "' . (string) $nextPendingOnboardingItem['label'] . '" deixa sua conta muito mais completa.';
        } elseif ($onboardingPendingCount === 0) {
            $onboardingStatusTone = 'is-complete';
            $onboardingStatusLabel = 'Concluído';
            $onboardingHeadline = 'Seu onboarding está completo.';
            $onboardingDescription = 'Seu cadastro essencial já está em dia. Agora você pode concentrar sua atenção nos próximos eventos e nas trilhas liberadas para seu perfil.';
            $onboardingSecondaryAction = [
                'label' => 'Atualizar painel',
                'href' => '/membro',
            ];
            $onboardingBenefits = [
                'Seu perfil já oferece identificação visual e dados básicos consistentes.',
                'A equipe consegue localizar suas informações principais com rapidez.',
                'Você pode focar a área do membro em agenda, contribuições e próximos passos.',
            ];
        }

        $onboardingPrimaryAction = [
            'label' => 'Completar perfil',
            'href' => '/membro/perfil/completar',
        ];
        $onboardingRecommendedStepTitle = is_array($nextPendingOnboardingItem)
            ? (string) $nextPendingOnboardingItem['label']
            : 'Revisar seu painel';
        $onboardingRecommendedStepDescription = is_array($nextPendingOnboardingItem)
            ? (string) $nextPendingOnboardingItem['description']
            : 'Revise suas informações para manter o cadastro consistente.';

        if ($onboardingPendingCount === 0) {
            $onboardingPrimaryAction = [
                'label' => 'Abrir agenda',
                'href' => '/agenda',
            ];
            $onboardingRecommendedStepTitle = 'Aproveitar a área do membro';
            $onboardingRecommendedStepDescription = 'Seu cadastro essencial já foi concluído. O próximo melhor uso do painel é acompanhar eventos, contribuições e trilhas liberadas.';
        }

        $roleWeights = [
            'member' => 10,
            'operator' => 20,
            'manager' => 30,
            'admin' => 40,
        ];

        $permissionTracks = [
            [
                'title' => 'Área de Operação',
                'href' => '/membro/operacao',
                'required_role' => 'operator',
                'required_label' => 'Operador',
            ],
            [
                'title' => 'Área de Gestão',
                'href' => '/membro/gestao',
                'required_role' => 'manager',
                'required_label' => 'Gerente',
            ],
            [
                'title' => 'Área Administrativa',
                'href' => '/membro/administracao',
                'required_role' => 'admin',
                'required_label' => 'Administrador',
            ],
        ];

        $memberWeight = (int) ($roleWeights[$roleKey] ?? 0);
        $permissionFeedback = array_map(
            static function (array $track) use ($memberWeight, $roleWeights): array {
                $requiredWeight = (int) $roleWeights[(string) $track['required_role']];
                $unlocked = $memberWeight >= $requiredWeight;

                return array_merge($track, [
                    'unlocked' => $unlocked,
                    'reason' => $unlocked
                        ? 'Acesso liberado para seu perfil atual.'
                        : 'Disponível a partir do perfil ' . (string) $track['required_label'] . '.',
                ]);
            },
            $permissionTracks
        );

        $nextActions = [];
        if (trim((string) ($member['profile_photo_path'] ?? '')) === '') {
            $nextActions[] = [
                'title' => 'Adicionar foto de perfil',
                'description' => 'Sua foto melhora identificação em menus e área interna.',
                'href' => '/membro/perfil/completar',
            ];
        }

        if ($roleKey === 'member') {
            $nextActions[] = [
                'title' => 'Solicitar ampliação de acesso',
                'description' => 'Fale com a administração para receber permissão de operação ou gestão.',
                'href' => '/contato',
            ];
        }

        $upcomingEvents = [];
        $myUpcomingEvents = [];
        try {
            $upcomingEvents = $this->agendaRepository->findUpcomingPublished(3);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar próximos eventos na área do membro.', [
                'error' => $exception->getMessage(),
            ]);
        }

        if ($memberId > 0) {
            try {
                $interestedEventIds = $this->agendaRepository->listInterestedEventIdsByMember($memberId);
                $interestedLookup = array_fill_keys($interestedEventIds, true);

                $upcomingEvents = array_map(
                    static function (array $event) use ($interestedLookup): array {
                        $eventId = (int) ($event['id'] ?? 0);
                        $event['member_interested'] = !empty($interestedLookup[$eventId]);

                        return $event;
                    },
                    $upcomingEvents
                );

                $myUpcomingEvents = $this->agendaRepository->findInterestedUpcomingByMember($memberId, 5);
            } catch (\Throwable $exception) {
                $this->logger->warning('Falha ao carregar calendário pessoal do membro.', [
                    'member_id' => $memberId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $primaryAction = [
            'title' => 'Revise sua jornada na área do membro',
            'description' => 'Seu painel está ativo. Use os atalhos abaixo para continuar.',
            'href' => '/membro',
            'button_label' => 'Atualizar painel',
        ];

        if ($onboardingCompleted < $onboardingTotal) {
            $primaryAction = [
                'title' => 'Complete seu onboarding',
                'description' => 'Finalize seus dados de cadastro para liberar uma experiência mais completa.',
                'href' => '/membro/perfil/completar',
                'button_label' => 'Completar perfil',
            ];
        } elseif (!empty($upcomingEvents[0])) {
            $firstEvent = $upcomingEvents[0];
            $primaryAction = [
                'title' => 'Participe do próximo evento',
                'description' => 'Sua próxima atividade é "' . (string) ($firstEvent['title'] ?? 'Atividade') . '".',
                'href' => '/agenda/' . (string) ($firstEvent['slug'] ?? ''),
                'button_label' => 'Ver detalhes',
            ];
        } elseif ($roleKey === 'member') {
            $primaryAction = [
                'title' => 'Solicite ampliação de acesso',
                'description' => 'Se você atua em outras frentes, peça atualização de perfil para novos recursos.',
                'href' => '/contato',
                'button_label' => 'Falar com administração',
            ];
        } else {
            foreach ($permissionFeedback as $permission) {
                if (!empty($permission['unlocked'])) {
                    $primaryAction = [
                        'title' => 'Acesse sua trilha principal',
                        'description' => 'Seu perfil já permite entrar na '
                            . (string) $permission['title']
                            . '.',
                        'href' => (string) $permission['href'],
                        'button_label' => 'Acessar agora',
                    ];
                    break;
                }
            }
        }

        $recentTimeline = [];
        $contributionHistory = [];
        $contributionHistoryFilters = $this->buildContributionHistoryFilters([], $queryParams);
        if ($status === 'profile-updated') {
            $recentTimeline[] = [
                'title' => 'Perfil atualizado',
                'detail' => 'Seus dados cadastrais foram salvos com sucesso.',
                'meta' => 'Nesta sessão',
            ];
        }

        if ($status === 'profile-updated-no-photo') {
            $recentTimeline[] = [
                'title' => 'Perfil atualizado parcialmente',
                'detail' => 'Os dados foram salvos, mas a foto ainda precisa ser enviada novamente.',
                'meta' => 'Nesta sessão',
            ];
        }

        if ($status === 'interest-added') {
            $recentTimeline[] = [
                'title' => 'Evento salvo no calendário pessoal',
                'detail' => 'Uma atividade foi adicionada aos seus próximos compromissos.',
                'meta' => 'Nesta sessão',
            ];
        }

        if ($status === 'interest-removed') {
            $recentTimeline[] = [
                'title' => 'Evento removido do calendário pessoal',
                'detail' => 'A atividade deixou de aparecer na sua agenda pessoal.',
                'meta' => 'Nesta sessão',
            ];
        }

        if ($memberId > 0) {
            try {
                $allContributionCharges = $this->memberAuthRepository->findContributionChargesByMember(
                    $memberId,
                    self::CONTRIBUTION_HISTORY_FETCH_LIMIT
                );
                $contributionHistoryFilters = $this->buildContributionHistoryFilters($allContributionCharges, $queryParams);
                $contributionHistory = $this->normalizeContributionHistory(
                    $this->applyContributionHistoryFilters($allContributionCharges, $contributionHistoryFilters)
                );
                $contributionHistoryFilters['result_count'] = count($contributionHistory);
            } catch (\Throwable $exception) {
                $this->logger->warning('Falha ao carregar histórico de contribuições na área do membro.', [
                    'member_id' => $memberId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $memberNotifications = [];
        $birthdayMembers = [];

        if ($status === 'profile-updated-no-photo') {
            $memberNotifications[] = [
                'type' => 'warning',
                'title' => 'Perfil salvo, foto pendente',
                'description' => 'Seu cadastro foi atualizado, mas a foto não foi salva. Tente novamente.',
                'href' => '/membro/perfil/completar',
                'cta' => 'Enviar foto novamente',
            ];
        }

        if ($onboardingCompleted < $onboardingTotal) {
            $memberNotifications[] = [
                'type' => 'warning',
                'title' => 'Onboarding incompleto',
                'description' => 'Você concluiu ' . $onboardingCompleted . ' de ' . $onboardingTotal . ' etapas.',
                'href' => '/membro/perfil/completar',
                'cta' => 'Concluir agora',
            ];
        }

        if (!empty($upcomingEvents[0])) {
            $firstEvent = $upcomingEvents[0];
            $memberNotifications[] = [
                'type' => 'info',
                'title' => 'Próximo evento disponível',
                'description' => (string) ($firstEvent['title'] ?? 'Atividade')
                    . ' · '
                    . (string) ($firstEvent['starts_at_label'] ?? 'Data a confirmar')
                    . '.',
                'href' => '/agenda/' . (string) ($firstEvent['slug'] ?? ''),
                'cta' => 'Ver evento',
            ];
        } else {
            $memberNotifications[] = [
                'type' => 'info',
                'title' => 'Sem eventos publicados no momento',
                'description' => 'Acompanhe a agenda para participar das próximas atividades da casa.',
                'href' => '/agenda',
                'cta' => 'Abrir agenda',
            ];
        }

        $unlockedTracks = array_values(array_filter(
            $permissionFeedback,
            static fn (array $permission): bool => !empty($permission['unlocked'])
        ));
        $lockedTracks = array_values(array_filter(
            $permissionFeedback,
            static fn (array $permission): bool => empty($permission['unlocked'])
        ));

        $weeklyHighlights = [
            [
                'label' => 'Progresso do onboarding',
                'value' => $onboardingPercent . '% concluído',
            ],
            [
                'label' => 'Trilhas já liberadas',
                'value' => count($unlockedTracks) . ' de ' . count($permissionFeedback),
            ],
            [
                'label' => 'Eventos no radar',
                'value' => count($upcomingEvents) > 0
                    ? count($upcomingEvents) . ' próximos eventos'
                    : 'Sem eventos publicados',
            ],
        ];

        $weeklyFocus = [
            'title' => 'Leitura do momento',
            'description' => 'Seu painel está em dia. Continue acompanhando a agenda e as novidades da casa.',
        ];

        if ($onboardingCompleted < $onboardingTotal) {
            $weeklyFocus = [
                'title' => 'Foco principal',
                'description' => 'Seu próximo avanço continua sendo concluir o onboarding para liberar a experiência completa na área do membro.',
            ];
        } elseif (!empty($upcomingEvents[0])) {
            $firstEvent = $upcomingEvents[0];
            $weeklyFocus = [
                'title' => 'Evento em destaque',
                'description' => 'Sua agenda já tem movimentação. Vale revisar "'
                    . (string) ($firstEvent['title'] ?? 'atividade')
                    . '" e confirmar sua participação.',
            ];
        } elseif (!empty($lockedTracks[0])) {
            $weeklyFocus = [
                'title' => 'Próximo degrau de acesso',
                'description' => 'Seu perfil atual está ativo, mas ainda existem trilhas liberáveis se você assumir novas frentes na casa.',
            ];
        }

        try {
            $birthdayMembers = $this->buildBirthdayMembers(
                $this->memberAuthRepository->findAllUsersForAdmin(),
                $memberId
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar aniversariantes do dia na área do membro.', [
                'member_id' => $memberId,
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->renderPage($response, 'pages/member-home.twig', [
            'member_data' => $member,
            'member_home_status' => $status,
            'member_contribution_history' => $contributionHistory,
            'member_contribution_history_filters' => $contributionHistoryFilters,
            'member_primary_action' => $primaryAction,
            'member_notifications' => array_slice($memberNotifications, 0, 3),
            'member_birthday_members' => $birthdayMembers,
            'member_weekly_highlights' => $weeklyHighlights,
            'member_weekly_focus' => $weeklyFocus,
            'member_recent_timeline' => array_slice($recentTimeline, 0, 3),
            'member_onboarding_checklist' => $onboardingChecklist,
            'member_onboarding_completed' => $onboardingCompleted,
            'member_onboarding_total' => $onboardingTotal,
            'member_onboarding_percent' => $onboardingPercent,
            'member_onboarding_pending_count' => $onboardingPendingCount,
            'member_onboarding_status_tone' => $onboardingStatusTone,
            'member_onboarding_status_label' => $onboardingStatusLabel,
            'member_onboarding_headline' => $onboardingHeadline,
            'member_onboarding_description' => $onboardingDescription,
            'member_onboarding_primary_action' => $onboardingPrimaryAction,
            'member_onboarding_secondary_action' => $onboardingSecondaryAction,
            'member_onboarding_recommended_step_title' => $onboardingRecommendedStepTitle,
            'member_onboarding_recommended_step_description' => $onboardingRecommendedStepDescription,
            'member_onboarding_benefits' => $onboardingBenefits,
            'member_next_actions' => $nextActions,
            'member_upcoming_events' => $upcomingEvents,
            'member_my_upcoming_events' => $myUpcomingEvents,
            'member_permission_feedback' => $permissionFeedback,
            'page_title' => 'Área do Membro | CEDE',
            'page_url' => 'https://cedern.org/membro',
            'page_description' => 'Área do membro do CEDE.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function buildBirthdayMembers(array $users, int $currentMemberId): array
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone(self::BIRTHDAY_TIMEZONE));
        $todayMonthDay = $today->format('m-d');
        $birthdayMembers = [];

        foreach ($users as $user) {
            $status = strtolower(trim((string) ($user['status'] ?? '')));
            $birthDateValue = trim((string) ($user['birth_date'] ?? ''));

            if ($status !== 'active' || $birthDateValue === '') {
                continue;
            }

            $birthDate = \DateTimeImmutable::createFromFormat('Y-m-d', $birthDateValue);
            if (!$birthDate instanceof \DateTimeImmutable || $birthDate->format('Y-m-d') !== $birthDateValue) {
                continue;
            }

            if ($birthDate->format('m-d') !== $todayMonthDay) {
                continue;
            }

            $fullName = trim((string) ($user['full_name'] ?? 'Membro'));
            $institutionalRole = trim((string) ($user['institutional_role'] ?? ''));
            $isCurrentMember = (int) ($user['id'] ?? 0) === $currentMemberId;

            $birthdayMembers[] = [
                'id' => (int) ($user['id'] ?? 0),
                'full_name' => $fullName,
                'display_name' => $this->extractFirstName($fullName),
                'profile_photo_path' => trim((string) ($user['profile_photo_path'] ?? '')),
                'initial' => mb_strtoupper(mb_substr($fullName, 0, 1, 'UTF-8'), 'UTF-8'),
                'institutional_role' => $institutionalRole,
                'is_current_member' => $isCurrentMember,
                'caption' => $isCurrentMember
                    ? 'Hoje e o seu aniversario.'
                    : ($institutionalRole !== '' ? $institutionalRole : 'Aniversaria hoje.'),
            ];
        }

        usort($birthdayMembers, static function (array $first, array $second): int {
            if (!empty($first['is_current_member']) && empty($second['is_current_member'])) {
                return -1;
            }

            if (empty($first['is_current_member']) && !empty($second['is_current_member'])) {
                return 1;
            }

            return strnatcasecmp((string) $first['full_name'], (string) $second['full_name']);
        });

        return $birthdayMembers;
    }

    private function extractFirstName(string $fullName): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $fullName) ?? $fullName);
        if ($normalized === '') {
            return 'Membro';
        }

        $parts = explode(' ', $normalized);

        return $parts[0] !== '' ? $parts[0] : $normalized;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function withAssociationLabels(array $member): array
    {
        $associationStatus = strtolower(trim((string) ($member['association_status'] ?? '')));
        if (!in_array($associationStatus, ['applicant', 'member', 'former'], true)) {
            $associationStatus = strtolower(trim((string) ($member['status'] ?? ''))) === 'pending'
                ? 'applicant'
                : 'member';
        }

        $member['association_status'] = $associationStatus;
        $member['association_status_label'] = match ($associationStatus) {
            'member' => 'Associado',
            'former' => 'Desligado',
            default => 'Solicitante',
        };
        $member['is_contributor'] = ContributionParticipation::normalize($member['is_contributor'] ?? null);
        $member['contributor_label'] = ContributionParticipation::label($member['is_contributor']);

        return $member;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function withFinancialSummary(array $member): array
    {
        $member['preferred_due_day_display'] = $this->formatDueDay($member['preferred_due_day'] ?? null);
        $member['contribution_amount_display'] = $this->formatCurrency((string) ($member['contribution_amount'] ?? ''));

        $preferredPaymentMethod = strtolower(trim((string) ($member['preferred_payment_method'] ?? '')));
        $member['preferred_payment_method_display'] = self::PAYMENT_METHOD_LABELS[$preferredPaymentMethod] ?? 'Não definido';

        return $member;
    }

    /**
     * @param array<string, mixed> $member
     * @return array<string, mixed>
     */
    private function withDisplayFields(array $member): array
    {
        $member['birth_date_display'] = $this->formatDate((string) ($member['birth_date'] ?? ''));

        return $member;
    }

    /**
     * @param array<int, array<string, mixed>> $charges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeContributionHistory(array $charges): array
    {
        return array_map(function (array $charge): array {
            $statusKey = strtolower(trim((string) ($charge['status'] ?? 'pending')));
            $preferredPaymentMethod = strtolower(trim((string) ($charge['preferred_payment_method'] ?? '')));
            $recordedPaymentMethod = strtolower(trim((string) ($charge['payment_recorded_method'] ?? '')));
            $gatewayBillingType = strtoupper(trim((string) ($charge['gateway_billing_type'] ?? '')));

            $paymentMethodLabel = self::PAYMENT_METHOD_LABELS[$recordedPaymentMethod]
                ?? self::PAYMENT_METHOD_LABELS[$preferredPaymentMethod]
                ?? ($gatewayBillingType === 'PIX'
                    ? 'Pix'
                    : ($gatewayBillingType === 'BOLETO' ? 'Boleto' : 'Não definido'));

            $charge['competence_label'] = $this->formatCompetenceLabel((string) ($charge['competence'] ?? ''));
            $charge['status_key'] = $statusKey;
            $charge['status_label'] = match ($statusKey) {
                'paid' => 'Recebida',
                'exempt' => 'Isenta',
                default => 'Em aberto',
            };
            $charge['status_tone'] = match ($statusKey) {
                'paid' => 'is-on',
                'exempt' => 'is-info',
                default => 'is-warning',
            };
            $charge['status_summary'] = match ($statusKey) {
                'paid' => $recordedPaymentMethod !== ''
                    ? 'Recebida via ' . $paymentMethodLabel . '.'
                    : 'Recebimento confirmado.',
                'exempt' => trim((string) ($charge['exemption_reason'] ?? '')) !== ''
                    ? (string) $charge['exemption_reason']
                    : 'Cobrança isentada.',
                default => trim((string) ($charge['gateway_status'] ?? '')) !== ''
                    ? 'Status atual: ' . $this->formatGatewayStatus((string) ($charge['gateway_status'] ?? '')) . '.'
                    : 'Aguardando pagamento.',
            };
            $charge['due_date_label'] = $this->formatDate((string) ($charge['due_date'] ?? ''));
            $charge['paid_at_label'] = $statusKey === 'paid'
                ? $this->formatDateTime((string) ($charge['paid_at'] ?? ''))
                : ($statusKey === 'exempt' ? 'Isenta' : 'Ainda não registrada');
            $charge['amount_due_label'] = $this->formatCurrency((string) ($charge['amount_due'] ?? ''));
            $charge['payment_method_label'] = $paymentMethodLabel;
            $charge['payment_method_context_label'] = $statusKey === 'paid' ? 'Forma recebida' : 'Forma prevista';

            return $charge;
        }, $charges);
    }

    /**
     * @param array<int, array<string, mixed>> $charges
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    private function buildContributionHistoryFilters(array $charges, array $queryParams): array
    {
        $selectedSort = trim((string) ($queryParams['history_sort'] ?? self::DEFAULT_CONTRIBUTION_HISTORY_SORT));
        if (!array_key_exists($selectedSort, self::CONTRIBUTION_HISTORY_SORT_OPTIONS)) {
            $selectedSort = self::DEFAULT_CONTRIBUTION_HISTORY_SORT;
        }

        $availableYears = [];
        foreach ($charges as $charge) {
            $competenceYear = $this->extractCompetenceYear((string) ($charge['competence'] ?? ''));
            if ($competenceYear === '') {
                continue;
            }

            $availableYears[$competenceYear] = true;
        }

        $yearOptions = array_map('strval', array_keys($availableYears));
        rsort($yearOptions, SORT_STRING);

        $selectedYear = trim((string) ($queryParams['history_year'] ?? ''));
        if ($selectedYear !== '' && !in_array($selectedYear, $yearOptions, true)) {
            $selectedYear = '';
        }

        $availableCompetences = [];
        foreach ($charges as $charge) {
            $competence = trim((string) ($charge['competence'] ?? ''));
            if ($competence === '') {
                continue;
            }

            if ($selectedYear !== '' && $this->extractCompetenceYear($competence) !== $selectedYear) {
                continue;
            }

            $availableCompetences[$competence] = true;
        }

        $competenceOptions = array_keys($availableCompetences);
        rsort($competenceOptions, SORT_STRING);

        $selectedCompetence = trim((string) ($queryParams['history_competence'] ?? ''));
        if ($selectedCompetence !== '' && !in_array($selectedCompetence, $competenceOptions, true)) {
            $selectedCompetence = '';
        }

        return [
            'year' => $selectedYear,
            'competence' => $selectedCompetence,
            'sort' => $selectedSort,
            'has_active' => $selectedYear !== ''
                || $selectedCompetence !== ''
                || $selectedSort !== self::DEFAULT_CONTRIBUTION_HISTORY_SORT,
            'total_count' => count($charges),
            'result_count' => count($charges),
            'year_options' => array_map(
                static fn (string $value): array => [
                    'value' => $value,
                    'label' => $value,
                    'selected' => $value === $selectedYear,
                ],
                $yearOptions
            ),
            'competence_options' => array_map(
                fn (string $value): array => [
                    'value' => $value,
                    'label' => $this->formatCompetenceLabel($value),
                    'selected' => $value === $selectedCompetence,
                ],
                $competenceOptions
            ),
            'sort_options' => array_map(
                static fn (string $value, string $label): array => [
                    'value' => $value,
                    'label' => $label,
                    'selected' => $value === $selectedSort,
                ],
                array_keys(self::CONTRIBUTION_HISTORY_SORT_OPTIONS),
                self::CONTRIBUTION_HISTORY_SORT_OPTIONS
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $charges
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyContributionHistoryFilters(array $charges, array $filters): array
    {
        $selectedYear = trim((string) ($filters['year'] ?? ''));
        $selectedCompetence = trim((string) ($filters['competence'] ?? ''));
        $selectedSort = trim((string) ($filters['sort'] ?? self::DEFAULT_CONTRIBUTION_HISTORY_SORT));

        $filteredCharges = array_values(array_filter(
            $charges,
            function (array $charge) use ($selectedYear, $selectedCompetence): bool {
                $competence = trim((string) ($charge['competence'] ?? ''));
                if ($selectedYear !== '' && $this->extractCompetenceYear($competence) !== $selectedYear) {
                    return false;
                }

                if ($selectedCompetence !== '' && $competence !== $selectedCompetence) {
                    return false;
                }

                return true;
            }
        ));

        $directionMultiplier = $selectedSort === 'competence_asc' ? 1 : -1;
        usort($filteredCharges, static function (array $first, array $second) use ($directionMultiplier): int {
            $competenceComparison = strcmp(
                (string) ($first['competence'] ?? ''),
                (string) ($second['competence'] ?? '')
            );

            if ($competenceComparison !== 0) {
                return $competenceComparison * $directionMultiplier;
            }

            $idComparison = ((int) ($first['id'] ?? 0)) <=> ((int) ($second['id'] ?? 0));

            return $idComparison * $directionMultiplier;
        });

        return $filteredCharges;
    }

    private function extractCompetenceYear(string $competence): string
    {
        $normalized = trim($competence);
        if (preg_match('/^(\d{4})-\d{2}$/', $normalized, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    private function formatDueDay(mixed $value): string
    {
        $day = (int) $value;
        if ($day < 1 || $day > 28) {
            return 'Não definido';
        }

        return 'Dia ' . sprintf('%02d', $day);
    }

    private function formatCompetenceLabel(string $competence): string
    {
        $normalized = trim($competence);

        if (preg_match('/^\d{4}-\d{2}$/', $normalized) !== 1) {
            return $normalized !== '' ? $normalized : 'Competência não definida';
        }

        [$year, $month] = array_map('intval', explode('-', $normalized));
        $months = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        return ($months[$month] ?? $normalized) . ' de ' . $year;
    }

    private function formatCurrency(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return 'Não definido';
        }

        $amount = (float) $normalized;
        if ($amount <= 0) {
            return 'Não definido';
        }

        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    private function formatDate(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatDateTime(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '-';
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatGatewayStatus(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'PENDING' => 'Pendente',
            'RECEIVED' => 'Recebida',
            'CONFIRMED' => 'Confirmada',
            'OVERDUE' => 'Vencida',
            'RECEIVED_IN_CASH' => 'Recebida em dinheiro',
            default => trim($value) !== '' ? trim($value) : 'Sem status',
        };
    }
}
