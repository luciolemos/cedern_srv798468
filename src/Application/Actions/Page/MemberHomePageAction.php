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

        $queryParams = $request->getQueryParams();
        $status = trim((string) ($queryParams['status'] ?? ''));
        $roleKey = (string) ($member['role_key'] ?? 'member');

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
        try {
            $upcomingEvents = $this->agendaRepository->findUpcomingPublished(3);
        } catch (\Throwable $exception) {
            $this->logger->warning('Falha ao carregar próximos eventos na área do membro.', [
                'error' => $exception->getMessage(),
            ]);
        }

        if (empty($nextActions)) {
            $nextActions[] = [
                'title' => 'Acompanhar agenda da casa',
                'description' => 'Consulte as próximas atividades e marque sua participação.',
                'href' => '/agenda',
            ];
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

        return $this->renderPage($response, 'pages/member-home.twig', [
            'member_data' => $member,
            'member_home_status' => $status,
            'member_onboarding_checklist' => $onboardingChecklist,
            'member_onboarding_completed' => $onboardingCompleted,
            'member_onboarding_total' => $onboardingTotal,
            'member_onboarding_percent' => $onboardingPercent,
            'member_next_actions' => $nextActions,
            'member_upcoming_events' => $upcomingEvents,
            'member_permission_feedback' => $permissionFeedback,
            'page_title' => 'Área do Membro | CEDE',
            'page_url' => 'https://cedern.org/membro',
            'page_description' => 'Área do membro do CEDE.',
        ]);
    }
}
