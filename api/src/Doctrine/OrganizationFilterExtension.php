<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Apartment;
use App\Entity\Building;
use App\Entity\ConnectionRequest;
use App\Entity\OrganizationMembership;
use App\Entity\Request;
use App\Entity\Resident;
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
        $this->addWhere($queryBuilder, $resourceClass, isItem: true);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass, bool $isItem = false): void
    {
        $user = $this->security->getUser();
        $isPlatformAdmin = $user instanceof User && $user->isPlatformAdmin();

        $orgId = $this->getOrganizationId();

        // Filter OrganizationMembership
        if ($resourceClass === OrganizationMembership::class) {
            if ($user instanceof User) {
                $this->filterMemberships($queryBuilder, $user, $orgId, $isPlatformAdmin);
            }
            return;
        }

        // Apartment: filter via building.organization (skip for item lookups to allow IRI denormalization)
        if ($resourceClass === Apartment::class) {
            if ($isItem) {
                return;
            }
            if (!$orgId) {
                if (!$isPlatformAdmin) {
                    $queryBuilder->andWhere('1 = 0');
                }
                return;
            }
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->join(sprintf('%s.building', $rootAlias), 'apt_b')
                ->andWhere('apt_b.organization = :org_filter_id')
                ->setParameter('org_filter_id', $orgId);
            return;
        }

        // Resident: filter via apartment.building.organization (skip for item lookups to allow IRI denormalization)
        if ($resourceClass === Resident::class) {
            if ($isItem) {
                return;
            }
            if (!$orgId) {
                if (!$isPlatformAdmin) {
                    $queryBuilder->andWhere('1 = 0');
                }
                return;
            }
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->join(sprintf('%s.apartment', $rootAlias), 'res_a')
                ->join('res_a.building', 'res_b')
                ->andWhere('res_b.organization = :org_filter_id')
                ->setParameter('org_filter_id', $orgId);
            return;
        }

        // ConnectionRequest
        if ($resourceClass === ConnectionRequest::class) {
            if ($user instanceof User) {
                $this->filterConnectionRequests($queryBuilder, $user, $orgId, $isPlatformAdmin);
            }
            return;
        }

        // For org-filtered entities, require org context (skip for item lookups on Building to allow IRI denormalization)
        if (!in_array($resourceClass, self::ORG_FILTERED_ENTITIES)) {
            return;
        }

        if ($isItem) {
            return;
        }

        if (!$orgId) {
            if (!$isPlatformAdmin) {
                $queryBuilder->andWhere('1 = 0');
            }
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
            ->setParameter('org_filter_id', $orgId);
    }

    private function filterMemberships(QueryBuilder $queryBuilder, User $user, ?int $orgId, bool $isPlatformAdmin): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // If org context present, platform admin or org admin sees all memberships for that org
        if ($orgId) {
            if ($isPlatformAdmin) {
                $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
                    ->setParameter('org_filter_id', $orgId);
                return;
            }

            $adminMembership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $user,
                'organization' => $orgId,
                'role' => OrganizationMembership::ROLE_ADMIN,
            ]);

            if ($adminMembership) {
                $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
                    ->setParameter('org_filter_id', $orgId);
                return;
            }
        }

        // Platform admin without org context sees all memberships
        if ($isPlatformAdmin) {
            return;
        }

        // Otherwise, users only see their own memberships
        $queryBuilder->andWhere(sprintf('%s.user = :current_user_id', $rootAlias))
            ->setParameter('current_user_id', $user->getId());
    }

    private function filterConnectionRequests(QueryBuilder $queryBuilder, User $user, ?int $orgId, bool $isPlatformAdmin): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // If org context present, platform admin or org admin sees all requests for that org
        if ($orgId) {
            if ($isPlatformAdmin) {
                $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
                    ->setParameter('org_filter_id', $orgId);
                return;
            }

            $adminMembership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $user,
                'organization' => $orgId,
                'role' => OrganizationMembership::ROLE_ADMIN,
            ]);

            if ($adminMembership) {
                $queryBuilder->andWhere(sprintf('%s.organization = :org_filter_id', $rootAlias))
                    ->setParameter('org_filter_id', $orgId);
                return;
            }
        }

        // Platform admin without org context sees all connection requests
        if ($isPlatformAdmin) {
            return;
        }

        // Otherwise, users only see their own connection requests
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
