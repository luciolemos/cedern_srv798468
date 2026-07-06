<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Admin;

use App\Application\Actions\Admin\AdminPatrimonyAssetViewPageAction;
use App\Domain\Patrimony\PatrimonyRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Tests\TestCase;

final class TestableAdminPatrimonyAssetViewPageAction extends AdminPatrimonyAssetViewPageAction
{
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
}

class AdminPatrimonyAssetViewPageActionTest extends TestCase
{
    public function testRendersAssetSummaryWithHistoryCollections(): void
    {
        $asset = [
            'id' => 7,
            'asset_code' => 'PAT-000007',
            'name' => 'Notebook Dell',
            'current_status' => 'em_uso',
            'current_status_label' => 'Em uso',
        ];
        $movements = [['id' => 11]];
        $maintenances = [['id' => 21]];
        $disposals = [];
        $attachments = [['id' => 31]];

        $action = $this->createAction($asset, $movements, $maintenances, $disposals, $attachments);
        $request = $this->createRequest('GET', '/painel/patrimonio/7')->withAttribute('id', 7);

        $response = $action($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pages/admin-patrimony-asset-view.twig', $action->capturedTemplate);
        $this->assertSame($asset, $action->capturedData['patrimony_asset']);
        $this->assertSame($movements, $action->capturedData['patrimony_asset_movements']);
        $this->assertSame($maintenances, $action->capturedData['patrimony_asset_maintenances']);
        $this->assertSame($disposals, $action->capturedData['patrimony_asset_disposals']);
        $this->assertSame($attachments, $action->capturedData['patrimony_asset_attachments']);
        $this->assertSame('/painel/patrimonio/7', $action->capturedData['page_url']);
    }

    public function testRedirectsToListWhenAssetDoesNotExist(): void
    {
        $action = $this->createAction(null, [], [], [], []);
        $request = $this->createRequest('GET', '/painel/patrimonio/999')->withAttribute('id', 999);

        $response = $action($request, new Response());

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/painel/patrimonio', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, mixed>|null $asset
     * @param array<int, array<string, mixed>> $movements
     * @param array<int, array<string, mixed>> $maintenances
     * @param array<int, array<string, mixed>> $disposals
     * @param array<int, array<string, mixed>> $attachments
     */
    private function createAction(
        ?array $asset,
        array $movements,
        array $maintenances,
        array $disposals,
        array $attachments
    ): TestableAdminPatrimonyAssetViewPageAction {
        $repositoryProphecy = $this->prophesize(PatrimonyRepository::class);
        $repositoryProphecy
            ->findAssetByIdForAdmin(7)
            ->willReturn($asset);
        $repositoryProphecy
            ->findAssetByIdForAdmin(999)
            ->willReturn($asset);

        if ($asset !== null) {
            $repositoryProphecy
                ->findMovementsByAssetId(7)
                ->willReturn($movements)
                ->shouldBeCalledOnce();
            $repositoryProphecy
                ->findMaintenancesByAssetId(7)
                ->willReturn($maintenances)
                ->shouldBeCalledOnce();
            $repositoryProphecy
                ->findDisposalsByAssetId(7)
                ->willReturn($disposals)
                ->shouldBeCalledOnce();
            $repositoryProphecy
                ->findAttachmentsByAssetId(7)
                ->willReturn($attachments)
                ->shouldBeCalledOnce();
        }

        $app = $this->getAppInstance();
        $container = $app->getContainer();

        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return new TestableAdminPatrimonyAssetViewPageAction(
            $logger,
            $twig,
            $repositoryProphecy->reveal()
        );
    }
}
