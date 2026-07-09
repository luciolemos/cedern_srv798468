<?php

declare(strict_types=1);

use App\Domain\Agenda\AgendaRepository;
use App\Domain\Analytics\SiteVisitRepository;
use App\Domain\Bookshop\BookshopRepository;
use App\Domain\Institutional\InstitutionalContentRepository;
use App\Domain\Library\LibraryRepository;
use App\Domain\Member\MemberAuthRepository;
use App\Domain\Patrimony\PatrimonyRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\Agenda\FallbackAgendaRepository;
use App\Infrastructure\Persistence\Agenda\MySqlAgendaRepository;
use App\Infrastructure\Persistence\Analytics\FallbackSiteVisitRepository;
use App\Infrastructure\Persistence\Analytics\MySqlSiteVisitRepository;
use App\Infrastructure\Persistence\Bookshop\FallbackBookshopRepository;
use App\Infrastructure\Persistence\Bookshop\MySqlBookshopRepository;
use App\Infrastructure\Persistence\Institutional\FallbackInstitutionalContentRepository;
use App\Infrastructure\Persistence\Institutional\MySqlInstitutionalContentRepository;
use App\Infrastructure\Persistence\Library\FallbackLibraryRepository;
use App\Infrastructure\Persistence\Library\MySqlLibraryRepository;
use App\Infrastructure\Persistence\Member\FallbackMemberAuthRepository;
use App\Infrastructure\Persistence\Member\MySqlMemberAuthRepository;
use App\Infrastructure\Persistence\Patrimony\FallbackPatrimonyRepository;
use App\Infrastructure\Persistence\Patrimony\MySqlPatrimonyRepository;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use App\Support\RepositoryInstantiationGuard;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        AgendaRepository::class => function (ContainerInterface $c): AgendaRepository {
            /** @var AgendaRepository */
            return RepositoryInstantiationGuard::resolve(
                AgendaRepository::class,
                static fn (): AgendaRepository => new MySqlAgendaRepository($c->get(\PDO::class)),
                static fn (): AgendaRepository => new FallbackAgendaRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        MemberAuthRepository::class => function (ContainerInterface $c): MemberAuthRepository {
            /** @var MemberAuthRepository */
            return RepositoryInstantiationGuard::resolve(
                MemberAuthRepository::class,
                static fn (): MemberAuthRepository => new MySqlMemberAuthRepository(
                    $c->get(\PDO::class),
                    $c->get(LoggerInterface::class)
                ),
                static fn (): MemberAuthRepository => new FallbackMemberAuthRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        InstitutionalContentRepository::class => function (ContainerInterface $c): InstitutionalContentRepository {
            /** @var InstitutionalContentRepository */
            return RepositoryInstantiationGuard::resolve(
                InstitutionalContentRepository::class,
                static fn (): InstitutionalContentRepository => new MySqlInstitutionalContentRepository($c->get(\PDO::class)),
                static fn (): InstitutionalContentRepository => new FallbackInstitutionalContentRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        LibraryRepository::class => function (ContainerInterface $c): LibraryRepository {
            /** @var LibraryRepository */
            return RepositoryInstantiationGuard::resolve(
                LibraryRepository::class,
                static fn (): LibraryRepository => new MySqlLibraryRepository($c->get(\PDO::class)),
                static fn (): LibraryRepository => new FallbackLibraryRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        BookshopRepository::class => function (ContainerInterface $c): BookshopRepository {
            /** @var BookshopRepository */
            return RepositoryInstantiationGuard::resolve(
                BookshopRepository::class,
                static fn (): BookshopRepository => new MySqlBookshopRepository($c->get(\PDO::class)),
                static fn (): BookshopRepository => new FallbackBookshopRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        PatrimonyRepository::class => function (ContainerInterface $c): PatrimonyRepository {
            /** @var PatrimonyRepository */
            return RepositoryInstantiationGuard::resolve(
                PatrimonyRepository::class,
                static fn (): PatrimonyRepository => new MySqlPatrimonyRepository($c->get(\PDO::class)),
                static fn (): PatrimonyRepository => new FallbackPatrimonyRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        SiteVisitRepository::class => function (ContainerInterface $c): SiteVisitRepository {
            /** @var SiteVisitRepository */
            return RepositoryInstantiationGuard::resolve(
                SiteVisitRepository::class,
                static fn (): SiteVisitRepository => new MySqlSiteVisitRepository($c->get(\PDO::class)),
                static fn (): SiteVisitRepository => new FallbackSiteVisitRepository(),
                $c->get(LoggerInterface::class),
                $_ENV
            );
        },
        UserRepository::class => \DI\autowire(InMemoryUserRepository::class),
    ]);
};
