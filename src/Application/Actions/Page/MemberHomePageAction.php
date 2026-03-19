<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class MemberHomePageAction extends AbstractMemberGuardedPageAction
{
    public const FLASH_KEY = 'member_home';
    private const BIRTHDAY_TIMEZONE = 'America/Fortaleza';

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

        $flash = $this->consumeSessionFlash(self::FLASH_KEY);
        $status = trim((string) ($flash['status'] ?? ''));
        $roleKey = (string) ($member['role_key'] ?? 'member');
        $memberId = (int) ($member['id'] ?? 0);

        $onboardingChecklist = [
            [
                'label' => 'Nome completo preenchido',
                'done' => trim((string) ($member['full_name'] ?? '')) !== '',
            ],
            [
                'label' => 'Celular informado',
                'done' => trim((string) ($member['phone_mobile'] ?? '')) !== '',
            ],
            [
                'label' => 'Naturalidade informada',
                'done' => trim((string) ($member['birth_place'] ?? '')) !== '',
            ],
            [
                'label' => 'Foto de perfil definida',
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
}
