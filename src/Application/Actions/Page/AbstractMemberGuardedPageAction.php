<?php

declare(strict_types=1);

namespace App\Application\Actions\Page;

use App\Domain\Member\MemberAuthRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

abstract class AbstractMemberGuardedPageAction extends AbstractPageAction
{
    protected MemberAuthRepository $memberAuthRepository;

    public function __construct(LoggerInterface $logger, Twig $twig, MemberAuthRepository $memberAuthRepository)
    {
        parent::__construct($logger, $twig);
        $this->memberAuthRepository = $memberAuthRepository;
    }

    /**
     * @return array<string, mixed>|Response
     */
    protected function resolveAuthenticatedMember(Response $response, bool $requireCompletedProfile = true)
    {
        if (empty($_SESSION['member_authenticated'])) {
            return $response->withHeader('Location', '/entrar')->withStatus(302);
        }

        $memberId = (int) ($_SESSION['member_user_id'] ?? 0);
        $member = $this->memberAuthRepository->findById($memberId);

        if ($member === null) {
            $this->clearMemberSession();

            return $response->withHeader('Location', '/entrar')->withStatus(302);
        }

        if ((string) ($member['status'] ?? '') !== 'active') {
            $this->clearMemberSession();

            return $response->withHeader('Location', '/entrar')->withStatus(302);
        }

        $_SESSION['member_name'] = (string) ($member['full_name'] ?? 'Membro');
        $_SESSION['member_email'] = (string) ($member['email'] ?? '');
        $_SESSION['member_role_key'] = (string) ($member['role_key'] ?? 'member');
        $_SESSION['member_role_name'] = (string) ($member['role_name'] ?? 'Membro');
        $_SESSION['member_profile_photo_path'] = (string) ($member['profile_photo_path'] ?? '');

        $profileCompleted = ((int) ($member['profile_completed'] ?? 0) === 1)
            && trim((string) ($member['phone_mobile'] ?? '')) !== '';

        if ($requireCompletedProfile && !$profileCompleted) {
            return $response->withHeader('Location', '/membro/perfil/completar')->withStatus(302);
        }

        return $member;
    }

    protected function ensureMinimumRole(Response $response, array $member, string $requiredRoleKey): ?Response
    {
        $weights = [
            'member' => 10,
            'operator' => 20,
            'manager' => 30,
            'admin' => 40,
        ];

        $memberRoleKey = (string) ($member['role_key'] ?? 'member');
        $memberWeight = (int) ($weights[$memberRoleKey] ?? 0);
        $requiredWeight = (int) ($weights[$requiredRoleKey] ?? PHP_INT_MAX);

        if ($memberWeight < $requiredWeight) {
            $this->storeSessionFlash(MemberHomePageAction::FLASH_KEY, [
                'status' => 'forbidden',
            ]);

            return $response->withHeader('Location', '/membro')->withStatus(303);
        }

        return null;
    }

    private function clearMemberSession(): void
    {
        unset(
            $_SESSION['member_authenticated'],
            $_SESSION['member_user_id'],
            $_SESSION['member_name'],
            $_SESSION['member_email'],
            $_SESSION['member_role_key'],
            $_SESSION['member_role_name'],
            $_SESSION['member_profile_photo_path']
        );
    }
}
