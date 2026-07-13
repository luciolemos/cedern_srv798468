<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use App\Support\InstitutionalRole;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AboutManagementPageAction extends AbstractPageAction
{
    private const ROLE_DISPLAY_ORDER = [
        'Presidente CEDE',
        'Vice-presidente CEDE',
        '1º Secretário',
        '2º Secretário',
        'Diretor de Finanças',
        'Diretor de Eventos',
        'Diretor de Patrimônio',
        'Diretor de Estudos',
        'Diretor de Atendimento Fraterno',
        'Diretor de Comunicação',
        'Coordenador',
        'Coordenador(a) do Curso de Mediunidade',
        'Conselheiro',
    ];

    private const ROLE_ALIASES = [
        'Vice presidente CEDE' => 'Vice-presidente CEDE',
        'Vice Presidente CEDE' => 'Vice-presidente CEDE',
        'Vice-Presidente CEDE' => 'Vice-presidente CEDE',
        'Secretário' => '1º Secretário',
    ];

    private const ROLE_RESPONSIBILITIES = [
        'Presidente CEDE' =>
            'Representa institucionalmente o CEDE, coordena decisões estratégicas '
            . 'e acompanha o cumprimento do plano anual da casa.',
        'Vice-presidente CEDE' =>
            'Apoia a presidência na coordenação geral, acompanha frentes prioritárias '
            . 'e substitui a presidência quando necessário.',
        '1º Secretário' =>
            'Organiza registros administrativos, atas e comunicações internas '
            . 'para dar suporte à governança institucional.',
        '2º Secretário' =>
            'Apoia o 1º Secretário na organização administrativa, no acompanhamento de registros '
            . 'e na continuidade das comunicações internas da instituição.',
        'Diretor de Finanças' =>
            'Planeja e acompanha orçamento, receitas e despesas, promovendo '
            . 'uso responsável dos recursos da instituição.',
        'Diretor de Eventos' =>
            'Coordena planejamento e execução de eventos e atividades, '
            . 'alinhando logística, equipes e calendário institucional.',
        'Diretor de Patrimônio' =>
            'Zela pelos espaços e bens do CEDE, organizando manutenção, '
            . 'conservação e uso adequado da infraestrutura.',
        'Diretor de Estudos' =>
            'Orienta frentes formativas e estudos doutrinários, estruturando '
            . 'conteúdos e acompanhando ciclos de aprendizagem.',
        'Diretor de Atendimento Fraterno' =>
            'Coordena o acolhimento fraterno e o encaminhamento das demandas '
            . 'de atendimento espiritual e humano.',
        'Diretor de Comunicação' =>
            'Conduz a comunicação institucional e os canais oficiais, garantindo '
            . 'clareza, unidade e responsabilidade na divulgação.',
        'Coordenador' =>
            'Acompanha a operação de uma frente específica, organiza equipe '
            . 'e garante execução das atividades previstas.',
        'Coordenador(a) do Curso de Mediunidade' =>
            'Coordena o curso de mediunidade, acompanha turmas, equipe de apoio '
            . 'e o andamento pedagógico das atividades formativas.',
        'Conselheiro' =>
            'Contribui com orientação e acompanhamento institucional, '
            . 'apoiando decisões e o fortalecimento da missão do CEDE.',
    ];

    private MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $managementMembers = [];

        try {
            $users = $this->memberAuthRepository->findAllUsersForAdmin();

            $managementMembers = array_values(array_filter(
                $users,
                static function (array $user): bool {
                    $associationStatus = strtolower(trim((string) ($user['association_status'] ?? '')));
                    if (!in_array($associationStatus, ['applicant', 'member', 'former'], true)) {
                        $associationStatus = strtolower(trim((string) ($user['status'] ?? ''))) === 'pending'
                            ? 'applicant'
                            : 'member';
                    }

                    return (string) ($user['status'] ?? '') === 'active'
                        && $associationStatus === 'member'
                        && trim((string) ($user['institutional_role'] ?? '')) !== '';
                }
            ));

            usort($managementMembers, static function (array $first, array $second): int {
                $firstRole = self::normalizeInstitutionalRole((string) ($first['institutional_role'] ?? ''));
                $secondRole = self::normalizeInstitutionalRole((string) ($second['institutional_role'] ?? ''));
                $firstRolePosition = self::resolveRoleDisplayPosition($firstRole);
                $secondRolePosition = self::resolveRoleDisplayPosition($secondRole);

                if ($firstRolePosition !== $secondRolePosition) {
                    return $firstRolePosition <=> $secondRolePosition;
                }

                return strnatcasecmp(
                    $firstRole . ' ' . (string) ($first['full_name'] ?? ''),
                    $secondRole . ' ' . (string) ($second['full_name'] ?? '')
                );
            });

            $managementMembers = array_map(function (array $member): array {
                $role = self::normalizeInstitutionalRole((string) ($member['institutional_role'] ?? ''));
                $member['institutional_role'] = $role;
                $member['institutional_role_description'] = self::ROLE_RESPONSIBILITIES[$role]
                    ?? 'Atua na organização e no fortalecimento das atividades institucionais do CEDE.';

                return $member;
            }, $managementMembers);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao carregar gestão CEDE para página dedicada pública.', [
                'exception' => $exception,
            ]);
        }

        return $this->renderPage($response, 'pages/about-management.twig', [
            'public_cede_management' => $managementMembers,
            'page_title' => 'Diretoria CEDE | Quem Somos | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/gestao-cede',
            'page_description' =>
                'Conheça a composição da diretoria atual do CEDE '
                . 'e as atribuições institucionais de cada função.',
        ]);
    }

    private static function normalizeInstitutionalRole(string $role): string
    {
        $normalizedRole = trim((string) preg_replace('/\s+/', ' ', trim($role)));

        return self::ROLE_ALIASES[$normalizedRole] ?? InstitutionalRole::normalize($normalizedRole) ?? $normalizedRole;
    }

    private static function resolveRoleDisplayPosition(string $role): int
    {
        $position = array_search($role, self::ROLE_DISPLAY_ORDER, true);

        return is_int($position) ? $position : count(self::ROLE_DISPLAY_ORDER);
    }
}
