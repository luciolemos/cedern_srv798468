<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ManagedPublicMediaPath;
use PHPUnit\Framework\TestCase;

final class ManagedPublicMediaPathTest extends TestCase
{
    public function testNormalizesLegacyPathToManagedPrefix(): void
    {
        $this->assertSame(
            'media/biblioteca/capas/capa_demo.webp',
            ManagedPublicMediaPath::normalize(
                'assets/img/library-covers/capa_demo.webp',
                'media/biblioteca/capas'
            )
        );
    }

    public function testPreservesManagedPath(): void
    {
        $this->assertSame(
            'media/patrimonio/docs/nota.pdf',
            ManagedPublicMediaPath::normalize(
                'media/patrimonio/docs/nota.pdf',
                'media/patrimonio/docs'
            )
        );
    }

    public function testBuildsUrlWithBasePath(): void
    {
        $this->assertSame(
            '/cedern/media/livraria/capas/livro.jpg',
            ManagedPublicMediaPath::toUrl('livro.jpg', 'media/livraria/capas', '/cedern')
        );
    }

    public function testRejectsInvalidFileName(): void
    {
        $this->assertSame(
            '',
            ManagedPublicMediaPath::normalize('../segredo', 'media/membros/fotos')
        );
    }
}
