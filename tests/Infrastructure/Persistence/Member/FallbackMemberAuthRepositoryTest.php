<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Member;

use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use PHPUnit\Framework\TestCase;

final class FallbackMemberAuthRepositoryTest extends TestCase
{
    public function testRejectsDuplicateCpfOnProfileUpdate(): void
    {
        $repository = new FallbackMemberAuthRepository();

        $firstUserId = $repository->createPendingUser([
            'full_name' => 'Primeiro Usuario',
            'email' => 'primeiro@example.com',
            'password_hash' => 'hash',
        ]);
        $repository->updateProfile($firstUserId, [
            'full_name' => 'Primeiro Usuario',
            'cpf' => '12345678909',
        ]);

        $secondUserId = $repository->createPendingUser([
            'full_name' => 'Segundo Usuario',
            'email' => 'segundo@example.com',
            'password_hash' => 'hash',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CPF já vinculado a outro usuário SISCEDE.');

        $repository->updateProfile($secondUserId, [
            'full_name' => 'Segundo Usuario',
            'cpf' => '123.456.789-09',
        ]);
    }
}
