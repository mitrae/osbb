<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Admin;
use App\Entity\Apartment;
use App\Entity\ApartmentOwnership;
use App\Entity\Building;
use App\Entity\OrganizationMembership;
use App\Entity\Request;
use App\Entity\Survey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class OrganizationFilterExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private const ORG_FILTERED_ENTITIES = [
        Request::class,
        Survey::class,
        Building::class,
    ];

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $user = $this->security->getUser();

        // Platform admins bypass all filters
        if ($user instanceof Admin) {
            return;
        }

        $orgId = $this->getOrganizationId();

        // Filter OrganizationMembership — users see own or org admin sees all for their org
        if ($resourceClass === OrganizationMembership::class) {
            if ($user instanceof User) {
                $this->filterMemberships($queryBuilder, $user, $orgId);
            }
            return;
        }

        // Apartment: filter via building.organization
        if ($resourceClass === Apartment::class) {
            if (!$orgId) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->join(sprintf('%s.building', $rootAlias), 'apt_b')
                ->andWhere('apt_b.organization = :org_filter_id')
                ->setParameter('org_filter_id', $orgId);
            return;
        }

        // ApartmentOwnership: filter via apartment.building.organization
        if ($resourceClass === ApartmentOwnership::class) {
            if (!$orgId) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->join(sprintf('%s.apartment', $rootAlias), 'own_a')
                ->join('own_a.building', 'own_b')
                ->andWhere('own_b.organization = :org_filter_id')
                ->setParameter('org_filter_id', $orgId);
            return;
        }

        // For org-filtered entities, require org context
        if (!in_array($resourceClass, self::ORG_FILTERED_ENTITIES)) {
            return;
        }

        if (!$orgId) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
            ->setParameter('org_filter_id', $orgId);
    }

    private function filterMemberships(QueryBuilder $queryBuilder, User $user, ?int $orgId): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // If org context present, check if user is org admin — they see all memberships for that org
        if ($orgId) {
            $adminMembership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $user,
                'organization' => $orgId,
                'status' => OrganizationMembership::STATUS_APPROVED,
                'role' => OrganizationMembership::ROLE_ADMIN,
            ]);

            if ($adminMembership) {
                $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
                    ->setParameter('org_filter_id', $orgId);
                return;
            }
        }

        // Otherwise, users only see their own memberships
        $queryBuilder->andWhere(sprintf('%s.user = :current_user_id', $rootAlias))
            ->setParameter('current_user_id', $user->getId());
    }

    private function getOrganizationId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $orgId = $request->headers->get('X-Organization-Id');
        return $orgId ? (int) $orgId : null;
    }
}
