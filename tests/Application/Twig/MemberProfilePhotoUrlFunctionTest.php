<?php

declare(strict_types=1);

namespace Tests\Application\Twig;

use Slim\Views\Twig;
use Tests\TestCase;

final class MemberProfilePhotoUrlFunctionTest extends TestCase
{
    public function testNormalizesLegacyMemberPhotoPathToManagedRoute(): void
    {
        $app = $this->getAppInstance();

        /** @var Twig $twig */
        $twig = $app->getContainer()->get(Twig::class);

        $template = $twig->getEnvironment()->createTemplate('{{ member_profile_photo_url(path, base_url) }}');
        $rendered = $template->render([
            'path' => 'assets/img/member-photos/member_legacy_test.jpg',
            'base_url' => '',
        ]);

        $this->assertSame('/media/membros/fotos/member_legacy_test.jpg', $rendered);
    }

    public function testPreservesManagedMemberPhotoPath(): void
    {
        $app = $this->getAppInstance();

        /** @var Twig $twig */
        $twig = $app->getContainer()->get(Twig::class);

        $template = $twig->getEnvironment()->createTemplate('{{ member_profile_photo_url(path, base_url) }}');
        $rendered = $template->render([
            'path' => 'media/membros/fotos/member_current_test.png',
            'base_url' => '/cedern',
        ]);

        $this->assertSame('/cedern/media/membros/fotos/member_current_test.png', $rendered);
    }
}
