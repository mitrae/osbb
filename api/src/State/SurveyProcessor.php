<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;
use App\Entity\Organization;
use App\Entity\Resident;
use App\Entity\Survey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SurveyProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Survey && $operation instanceof Post) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedHttpException('Only users can create surveys.');
            }
            $data->setCreatedBy($user);

            // Read organization from X-Organization-Id header
            $httpRequest = $this->requestStack->getCurrentRequest();
            $orgId = $httpRequest?->headers->get('X-Organization-Id');

            if (!$orgId) {
                throw new BadRequestHttpException('X-Organization-Id header is required.');
            }

            $organization = $this->em->getRepository(Organization::class)->find((int) $orgId);
            if (!$organization) {
                throw new BadRequestHttpException('Organization not found.');
            }

            // Validate user has access: platform admin, membership, or resident link
            if (!$user->isPlatformAdmin() && !$this->userHasOrgAccess($user, $organization)) {
                throw new AccessDeniedHttpException('You are not a member of this organization.');
            }

            $data->setOrganization($organization);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function userHasOrgAccess(User $user, Organization $organization): bool
    {
        $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $organization,
        ]);
        if ($membership) {
            return true;
        }

        $count = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Resident::class, 'r')
            ->join('r.apartment', 'a')
            ->join('a.building', 'b')
            ->where('r.user = :user')
            ->andWhere('b.organization = :org')
            ->setParameter('user', $user)
            ->setParameter('org', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
