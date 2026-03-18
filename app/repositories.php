<?php

declare(strict_types=1);

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Analytics\SiteVisitRepository;
use App\Domain\Institutional\InstitutionalContentRepository;
use App\Domain\Member\MemberAuthRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\Agenda\FallbackAgendaRepository;
use App\Infrastructure\Persistence\Agenda\MySqlAgendaRepository;
use App\Infrastructure\Persistence\Analytics\FallbackSiteVisitRepository;
use App\Infrastructure\Persistence\Analytics\MySqlSiteVisitRepository;
use App\Infrastructure\Persistence\Institutional\FallbackInstitutionalContentRepository;
use App\Infrastructure\Persistence\Institutional\MySqlInstitutionalContentRepository;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use App\Infrastructure\Persistence\Member\MySqlMemberAuthRepository;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return function (ContainerBuilder $containerBuilder) {
    // Here we map our UserRepository interface to its in memory implementation
    $containerBuilder->addDefinitions([
        AgendaRepository::class => function (ContainerInterface $c): AgendaRepository {
            try {
                return new MySqlAgendaRepository($c->get(\PDO::class));
            } catch (\Throwable $exception) {
                return new FallbackAgendaRepository();
            }
        },
        MemberAuthRepository::class => function (ContainerInterface $c): MemberAuthRepository {
            try {
                return new MySqlMemberAuthRepository($c->get(\PDO::class));
            } catch (\Throwable $exception) {
                return new FallbackMemberAuthRepository();
            }
        },
        InstitutionalContentRepository::class => function (ContainerInterface $c): InstitutionalContentRepository {
            try {
                return new MySqlInstitutionalContentRepository($c->get(\PDO::class));
            } catch (\Throwable $exception) {
                return new FallbackInstitutionalContentRepository();
            }
        },
        SiteVisitRepository::class => function (ContainerInterface $c): SiteVisitRepository {
            try {
                return new MySqlSiteVisitRepository($c->get(\PDO::class));
            } catch (\Throwable $exception) {
                return new FallbackSiteVisitRepository();
            }
        },
        UserRepository::class => \DI\autowire(InMemoryUserRepository::class),
    ]);
};
