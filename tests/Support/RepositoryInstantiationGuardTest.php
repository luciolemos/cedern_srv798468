<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\RepositoryInstantiationGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

final class RepositoryInstantiationGuardTest extends TestCase
{
    public function testGuardUsesFallbackInDevelopmentAndLogsWarning(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param mixed $level
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $repository = RepositoryInstantiationGuard::resolve(
            'member-auth',
            static function (): object {
                throw new RuntimeException('db offline');
            },
            static fn (): object => new \stdClass(),
            $logger,
            ['APP_ENV' => 'development']
        );

        $this->assertInstanceOf(\stdClass::class, $repository);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame('Repositorio MySQL indisponivel; fallback ativado.', $logger->records[0]['message']);
    }

    public function testGuardFailsFastInProduction(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param mixed $level
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Repositorio indisponivel em modo estrito: member-auth');

        try {
            RepositoryInstantiationGuard::resolve(
                'member-auth',
                static function (): object {
                    throw new RuntimeException('db offline');
                },
                static fn (): object => new \stdClass(),
                $logger,
                ['APP_ENV' => 'production']
            );
        } finally {
            $this->assertCount(1, $logger->records);
            $this->assertSame('critical', $logger->records[0]['level']);
            $this->assertSame('Repositorio indisponivel em modo estrito.', $logger->records[0]['message']);
        }
    }
}
