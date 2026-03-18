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
        if (in_array($status, ['profile-updated', 'profile-updated-no-photo'], true)) {
            $recentTimeline[] = [
                'title' => 'Perfil atualizado',
                'detail' => 'Suas informações foram salvas nesta sessão.',
                'meta' => 'Agora',
            ];
        }

        $recentTimeline[] = [
            'title' => $onboardingCompleted === $onboardingTotal
                ? 'Onboarding concluído'
                : 'Onboarding em andamento',
            'detail' => $onboardingCompleted . '/' . $onboardingTotal . ' itens concluídos.',
            'meta' => 'Progresso atual',
        ];

        if (!empty($upcomingEvents[0])) {
            $firstEvent = $upcomingEvents[0];
            $recentTimeline[] = [
                'title' => 'Próximo compromisso',
                'detail' => (string) ($firstEvent['title'] ?? 'Atividade'),
                'meta' => (string) ($firstEvent['starts_at_label'] ?? 'Data a confirmar'),
            ];
        }

        $recentTimeline[] = [
            'title' => 'Perfil atual',
            'detail' => (string) ($member['role_name'] ?? 'Membro'),
            'meta' => 'Permissão ativa',
        ];

        $memberNotifications = [];

        if ($status === 'profile-updated') {
            $memberNotifications[] = [
                'type' => 'success',
                'title' => 'Perfil atualizado com sucesso',
                'description' => 'Seus dados mais recentes já estão ativos no sistema.',
                'href' => '/membro/perfil/completar',
                'cta' => 'Revisar perfil',
            ];
        }

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

        $weeklyNextSteps = [];
        if ($onboardingCompleted < $onboardingTotal) {
            $weeklyNextSteps[] = [
                'title' => 'Concluir cadastro do perfil',
                'href' => '/membro/perfil/completar',
            ];
        }

        if (!empty($upcomingEvents[0])) {
            $firstEvent = $upcomingEvents[0];
            $weeklyNextSteps[] = [
                'title' => 'Confirmar presença em "' . (string) ($firstEvent['title'] ?? 'atividade') . '"',
                'href' => '/agenda/' . (string) ($firstEvent['slug'] ?? ''),
            ];
        }

        if (!empty($lockedTracks[0])) {
            $weeklyNextSteps[] = [
                'title' => 'Solicitar acesso para ' . (string) $lockedTracks[0]['title'],
                'href' => '/contato',
            ];
        }

        if (empty($weeklyNextSteps)) {
            $weeklyNextSteps[] = [
                'title' => 'Acompanhar agenda e novidades da casa',
                'href' => '/agenda',
            ];
        }

        return $this->renderPage($response, 'pages/member-home.twig', [
            'member_data' => $member,
            'member_home_status' => $status,
            'member_primary_action' => $primaryAction,
            'member_notifications' => array_slice($memberNotifications, 0, 3),
            'member_weekly_highlights' => $weeklyHighlights,
            'member_weekly_next_steps' => array_slice($weeklyNextSteps, 0, 3),
            'member_recent_timeline' => $recentTimeline,
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
}
