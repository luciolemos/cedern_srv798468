<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Throwable;

class AboutManagementPageAction extends AbstractPageAction
{
    private const ROLE_RESPONSIBILITIES = [
        'Presidente CEDE' =>
            'Representa institucionalmente o CEDE, coordena decisões estratégicas '
            . 'e acompanha o cumprimento do plano anual da casa.',
        'Vice-presidente CEDE' =>
            'Apoia a presidência na coordenação geral, acompanha frentes prioritárias '
            . 'e substitui a presidência quando necessário.',
        'Secretário' =>
            'Organiza registros administrativos, atas e comunicações internas '
            . 'para dar suporte à governança institucional.',
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
                static fn (array $user): bool =>
                    (string) ($user['status'] ?? '') === 'active'
                    && trim((string) ($user['institutional_role'] ?? '')) !== ''
            ));

            usort($managementMembers, static function (array $first, array $second): int {
                return strnatcasecmp(
                    (string) ($first['institutional_role'] ?? '') . ' ' . (string) ($first['full_name'] ?? ''),
                    (string) ($second['institutional_role'] ?? '') . ' ' . (string) ($second['full_name'] ?? '')
                );
            });

            $managementMembers = array_map(function (array $member): array {
                $role = trim((string) ($member['institutional_role'] ?? ''));
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
            'page_title' => 'Gestão CEDE | Quem Somos | CEDE',
            'page_url' => 'https://cedern.org/quem-somos/gestao-cede',
            'page_description' =>
                'Conheça a composição da gestão atual do CEDE '
                . 'e as atribuições institucionais de cada função.',
        ]);
    }
}
