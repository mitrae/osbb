<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;
use App\Entity\RequestComment;
use App\Entity\Resident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RequestCommentProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof RequestComment && $operation instanceof Post) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedHttpException('Only users can post comments.');
            }
            $data->setAuthor($user);

            // Validate: user must be request author or org member (via membership or resident)
            $request = $data->getRequest();
            $organization = $request?->getOrganization();

            if ($organization) {
                $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                    'user' => $user,
                    'organization' => $organization,
                ]);

                if (!$membership) {
                    // Check resident link
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

                    if ($count == 0) {
                        throw new AccessDeniedHttpException('You are not a member of this organization.');
                    }
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
