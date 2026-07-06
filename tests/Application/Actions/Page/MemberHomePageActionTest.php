<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Page;

use App\Application\Actions\Page\MemberHomePageAction;
use App\Domain\Agenda\AgendaRepository;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class MemberHomePageActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testRendersContributionHistoryInsideMemberHome(): void
    {
        [$memberAuthRepository, $userId] = $this->createMemberRepositoryWithCharges([
            '2026-06',
            '2026-07',
            '2026-08',
        ]);

        $juneChargeId = (int) (($memberAuthRepository->findContributionChargesByMember($userId, 12)[2]['id'] ?? 0));
        $julyChargeId = (int) (($memberAuthRepository->findContributionChargesByMember($userId, 12)[1]['id'] ?? 0));

        $memberAuthRepository->markContributionChargeAsPaid($juneChargeId, 'boleto', 7);
        $memberAuthRepository->markContributionChargeAsPaid($julyChargeId, 'pix', 7);

        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $userId;

        $action = $this->createCapturingAction($memberAuthRepository, $userId);
        $request = $this->createRequest('GET', '/membro', ['HTTP_ACCEPT' => 'text/html']);

        $response = $action($request, new Response());
        $history = $action->capturedData['member_contribution_history'] ?? [];
        $filters = $action->capturedData['member_contribution_history_filters'] ?? [];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/member-home.twig', $action->capturedTemplate);
        $this->assertCount(3, $history);
        $this->assertSame('Agosto de 2026', $history[0]['competence_label'] ?? '');
        $this->assertSame('Em aberto', $history[0]['status_label'] ?? '');
        $this->assertSame('05/08/2026', $history[0]['due_date_label'] ?? '');
        $this->assertSame('Julho de 2026', $history[1]['competence_label'] ?? '');
        $this->assertSame('Recebida via Pix.', $history[1]['status_summary'] ?? '');
        $this->assertSame('Pix', $history[1]['payment_method_label'] ?? '');
        $this->assertSame('Junho de 2026', $history[2]['competence_label'] ?? '');
        $this->assertSame('Boleto', $history[2]['payment_method_label'] ?? '');
        $this->assertSame(3, $filters['total_count'] ?? 0);
        $this->assertSame(3, $filters['result_count'] ?? 0);
        $this->assertCount(1, $filters['year_options'] ?? []);
        $this->assertCount(3, $filters['competence_options'] ?? []);
    }

    public function testAppliesContributionHistoryYearCompetenceAndSortFilters(): void
    {
        [$memberAuthRepository, $userId] = $this->createMemberRepositoryWithCharges([
            '2025-12',
            '2026-06',
            '2026-07',
            '2026-08',
        ]);

        $_SESSION['member_authenticated'] = true;
        $_SESSION['member_user_id'] = $userId;

        $action = $this->createCapturingAction($memberAuthRepository, $userId);

        $sortedRequest = $this->createRequest('GET', '/membro', ['HTTP_ACCEPT' => 'text/html'])
            ->withQueryParams([
                'history_year' => '2026',
                'history_sort' => 'competence_asc',
            ]);

        $sortedResponse = $action($sortedRequest, new Response());
        $sortedHistory = $action->capturedData['member_contribution_history'] ?? [];
        $sortedFilters = $action->capturedData['member_contribution_history_filters'] ?? [];

        $this->assertSame(200, $sortedResponse->getStatusCode());
        $this->assertCount(3, $sortedHistory);
        $this->assertSame('Junho de 2026', $sortedHistory[0]['competence_label'] ?? '');
        $this->assertSame('Julho de 2026', $sortedHistory[1]['competence_label'] ?? '');
        $this->assertSame('Agosto de 2026', $sortedHistory[2]['competence_label'] ?? '');
        $this->assertSame('2026', $sortedFilters['year'] ?? '');
        $this->assertSame('competence_asc', $sortedFilters['sort'] ?? '');
        $this->assertSame(3, $sortedFilters['result_count'] ?? 0);
        $this->assertSame(4, $sortedFilters['total_count'] ?? 0);

        $filteredRequest = $this->createRequest('GET', '/membro', ['HTTP_ACCEPT' => 'text/html'])
            ->withQueryParams([
                'history_year' => '2026',
                'history_competence' => '2026-07',
                'history_sort' => 'competence_desc',
            ]);

        $filteredResponse = $action($filteredRequest, new Response());
        $filteredHistory = $action->capturedData['member_contribution_history'] ?? [];
        $filteredFilters = $action->capturedData['member_contribution_history_filters'] ?? [];

        $this->assertSame(200, $filteredResponse->getStatusCode());
        $this->assertCount(1, $filteredHistory);
        $this->assertSame('Julho de 2026', $filteredHistory[0]['competence_label'] ?? '');
        $this->assertSame('2026', $filteredFilters['year'] ?? '');
        $this->assertSame('2026-07', $filteredFilters['competence'] ?? '');
        $this->assertSame('competence_desc', $filteredFilters['sort'] ?? '');
        $this->assertSame(1, $filteredFilters['result_count'] ?? 0);
    }

    /**
     * @param list<string> $competences
     * @return array{0: FallbackMemberAuthRepository, 1: int}
     */
    private function createMemberRepositoryWithCharges(array $competences): array
    {
        $memberAuthRepository = new FallbackMemberAuthRepository();
        $userId = $memberAuthRepository->createPendingUser([
            'full_name' => 'Marina Silva',
            'email' => 'marina@example.com',
            'password_hash' => 'hash',
        ]);

        $memberAuthRepository->updateProfile($userId, [
            'full_name' => 'Marina Silva',
            'phone_mobile' => '84999998888',
            'birth_date' => '1990-08-12',
            'birth_place' => 'Natal/RN',
            'preferred_due_day' => 5,
            'contribution_amount' => '50.00',
            'preferred_payment_method' => 'boleto',
            'profile_completed' => 1,
        ]);
        $memberAuthRepository->approveAndAssignRole($userId, 1, 'Atendimento fraterno', 'efetivo', 'member', true, 'active');

        foreach ($competences as $competence) {
            $memberAuthRepository->generateContributionCharges($competence, 7);
        }

        return [$memberAuthRepository, $userId];
    }

    /**
     * @return MemberHomePageAction&object{capturedTemplate: string, capturedData: array<string, mixed>}
     */
    private function createCapturingAction(FallbackMemberAuthRepository $memberAuthRepository, int $userId): object
    {
        $agendaRepositoryProphecy = $this->prophesize(AgendaRepository::class);
        $agendaRepositoryProphecy->findUpcomingPublished(3)->willReturn([]);
        $agendaRepositoryProphecy->listInterestedEventIdsByMember($userId)->willReturn([]);
        $agendaRepositoryProphecy->findInterestedUpcomingByMember($userId, 5)->willReturn([]);

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new class (
            $logger,
            $twig,
            $memberAuthRepository,
            $agendaRepositoryProphecy->reveal()
        ) extends MemberHomePageAction {
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
