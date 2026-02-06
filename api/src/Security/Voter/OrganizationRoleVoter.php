<?php

namespace App\Security\Voter;

use App\Entity\Admin;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrganizationRoleVoter extends Voter
{
    public const ORG_ROLE_ADMIN = 'ORG_ROLE_ADMIN';
    public const ORG_ROLE_MANAGER = 'ORG_ROLE_MANAGER';
    public const ORG_ROLE_RESIDENT = 'ORG_ROLE_RESIDENT';
    public const ORG_MEMBER = 'ORG_MEMBER';

    private const ROLE_HIERARCHY = [
        OrganizationMembership::ROLE_ADMIN => 3,
        OrganizationMembership::ROLE_MANAGER => 2,
        OrganizationMembership::ROLE_RESIDENT => 1,
    ];

    private const ATTRIBUTE_TO_ROLE = [
        self::ORG_ROLE_ADMIN => OrganizationMembership::ROLE_ADMIN,
        self::ORG_ROLE_MANAGER => OrganizationMembership::ROLE_MANAGER,
        self::ORG_ROLE_RESIDENT => OrganizationMembership::ROLE_RESIDENT,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::ORG_ROLE_ADMIN,
            self::ORG_ROLE_MANAGER,
            self::ORG_ROLE_RESIDENT,
            self::ORG_MEMBER,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Platform admins (Admin entity) always pass org-level checks
        if ($user instanceof Admin) {
            return true;
        }

        if (!$user instanceof User) {
            return false;
        }

        $orgId = $this->getOrganizationIdFromRequest();
        if (!$orgId) {
            return false;
        }

        $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $orgId,
            'status' => OrganizationMembership::STATUS_APPROVED,
        ]);

        if (!$membership) {
            return false;
        }

        // ORG_MEMBER just checks approved membership existence
        if ($attribute === self::ORG_MEMBER) {
            return true;
        }

        $requiredRole = self::ATTRIBUTE_TO_ROLE[$attribute] ?? null;
        if (!$requiredRole) {
            return false;
        }

        $userRoleLevel = self::ROLE_HIERARCHY[$membership->getRole()] ?? 0;
        $requiredRoleLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userRoleLevel >= $requiredRoleLevel;
    }

    private function getOrganizationIdFromRequest(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $orgId = $request->headers->get('X-Organization-Id');
        return $orgId ? (int) $orgId : null;
    }
}
