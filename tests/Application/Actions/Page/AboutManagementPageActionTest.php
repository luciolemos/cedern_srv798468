<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\AboutManagementPageAction;
use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class AboutManagementPageActionTest extends TestCase
{
    public function testOrdersManagementCardsByInstitutionalPriorityAndNormalizesVicePresidentRole(): void
    {
        $memberAuthRepositoryProphecy = $this->prophesize(MemberAuthRepository::class);
        $memberAuthRepositoryProphecy->findAllUsersForAdmin()->willReturn([
            [
                'full_name' => 'Diretora Financeira',
                'status' => 'active',
                'association_status' => 'member',
                'institutional_role' => 'Diretor de Finanças',
            ],
            [
                'full_name' => 'Vice Teste',
                'status' => 'active',
                'association_status' => 'member',
                'institutional_role' => 'Vice Presidente CEDE',
            ],
            [
                'full_name' => 'Presidente Teste',
                'status' => 'active',
                'association_status' => 'member',
                'institutional_role' => 'Presidente CEDE',
            ],
            [
                'full_name' => 'Ex-associado',
                'status' => 'active',
                'association_status' => 'former',
                'institutional_role' => 'Secretário',
            ],
        ]);

        $action = $this->createCapturingAction($memberAuthRepositoryProphecy->reveal());
        $request = $this->createRequest('GET', '/quem-somos/gestao-cede', ['HTTP_ACCEPT' => 'text/html']);

        $response = $action($request, new Response());
        $managementMembers = $action->capturedData['public_cede_management'] ?? [];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/about-management.twig', $action->capturedTemplate);
        $this->assertCount(3, $managementMembers);
        $this->assertSame('Presidente CEDE', $managementMembers[0]['institutional_role'] ?? '');
        $this->assertSame('Presidente Teste', $managementMembers[0]['full_name'] ?? '');
        $this->assertSame('Vice-presidente CEDE', $managementMembers[1]['institutional_role'] ?? '');
        $this->assertSame('Vice Teste', $managementMembers[1]['full_name'] ?? '');
        $this->assertSame(
            'Apoia a presidência na coordenação geral, acompanha frentes prioritárias e substitui a presidência quando necessário.',
            $managementMembers[1]['institutional_role_description'] ?? ''
        );
        $this->assertSame('Diretor de Finanças', $managementMembers[2]['institutional_role'] ?? '');
    }

    /**
     * @return AboutManagementPageAction&object{capturedTemplate: string, capturedData: array<string, mixed>}
     */
    private function createCapturingAction(MemberAuthRepository $memberAuthRepository): object
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new class ($logger, $twig, $memberAuthRepository) extends AboutManagementPageAction {
            public string $capturedTemplate = '';

            /** @var array<string, mixed> */
            public array $capturedData = [];

            protected function renderPage(
                ResponseInterface $response,
                string $template,
                array $data = []
            ): ResponseInterface {
                $this->capturedTemplate = $template;
                $this->capturedData = $data;

                return $response;
            }
        };
    }
}
