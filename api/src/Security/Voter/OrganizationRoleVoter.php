<?php

namespace App\Security\Voter;

use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OrganizationRoleVoter extends Voter
{
    public const ORG_ROLE_ADMIN = 'ORG_ROLE_ADMIN';
    public const ORG_ROLE_MANAGER = 'ORG_ROLE_MANAGER';
    public const ORG_MEMBER = 'ORG_MEMBER';

    private const ROLE_HIERARCHY = [
        OrganizationMembership::ROLE_ADMIN => 3,
        OrganizationMembership::ROLE_MANAGER => 2,
    ];

    private const ATTRIBUTE_TO_ROLE = [
        self::ORG_ROLE_ADMIN => OrganizationMembership::ROLE_ADMIN,
        self::ORG_ROLE_MANAGER => OrganizationMembership::ROLE_MANAGER,
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
            self::ORG_MEMBER,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Platform admins always pass org-level checks
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $orgId = $this->getOrganizationIdFromRequest();
        if (!$orgId) {
            return false;
        }

        // Check OrganizationMembership
        $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $orgId,
        ]);

        // ORG_MEMBER: true if user has membership OR has a linked Resident in the org
        if ($attribute === self::ORG_MEMBER) {
            if ($membership) {
                return true;
            }
            return $this->hasResidentInOrg($user, $orgId);
        }

        // Role-based checks require a membership
        if (!$membership) {
            return false;
        }

        $requiredRole = self::ATTRIBUTE_TO_ROLE[$attribute] ?? null;
        if (!$requiredRole) {
            return false;
        }

        $userRoleLevel = self::ROLE_HIERARCHY[$membership->getRole()] ?? 0;
        $requiredRoleLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userRoleLevel >= $requiredRoleLevel;
    }

    private function hasResidentInOrg(User $user, int $orgId): bool
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Resident::class, 'r')
            ->join('r.apartment', 'a')
            ->join('a.building', 'b')
            ->where('r.user = :user')
            ->andWhere('b.organization = :org')
            ->setParameter('user', $user)
            ->setParameter('org', $orgId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
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
