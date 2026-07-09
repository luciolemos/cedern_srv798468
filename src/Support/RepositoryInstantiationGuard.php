<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class RepositoryInstantiationGuard
{
    /**
     * @param callable():object $primaryFactory
     * @param callable():object $fallbackFactory
     * @param array<string, mixed> $env
     */
    public static function resolve(
        string $repositoryName,
        callable $primaryFactory,
        callable $fallbackFactory,
        LoggerInterface $logger,
        array $env = []
    ): object {
        try {
            return $primaryFactory();
        } catch (Throwable $exception) {
            $fallbackAllowed = RuntimeSafety::repositoryFallbackAllowed($env);
            $context = [
                'repository' => $repositoryName,
                'app_env' => RuntimeSafety::readString('APP_ENV', $env, 'production'),
                'fallback_allowed' => $fallbackAllowed,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ];

            if (!$fallbackAllowed) {
                $logger->critical('Repositorio indisponivel em modo estrito.', $context);

                throw new RuntimeException(
                    'Repositorio indisponivel em modo estrito: ' . $repositoryName,
                    0,
                    $exception
                );
            }

            try {
                $fallbackRepository = $fallbackFactory();
            } catch (Throwable $fallbackException) {
                $logger->critical('Falha ao inicializar repositorio fallback.', $context + [
                    'fallback_exception_class' => $fallbackException::class,
                    'fallback_exception_message' => $fallbackException->getMessage(),
                ]);

                throw new RuntimeException(
                    'Falha ao inicializar fallback de repositorio: ' . $repositoryName,
                    0,
                    $fallbackException
                );
            }

            $logger->warning('Repositorio MySQL indisponivel; fallback ativado.', $context + [
                'fallback_class' => $fallbackRepository::class,
            ]);

            return $fallbackRepository;
        }
    }
}
