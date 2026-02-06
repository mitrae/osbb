<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use App\Entity\SurveyVote;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VoteProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof SurveyVote && $operation instanceof Post) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedHttpException('Only residents can vote.');
            }
            $data->setUser($user);

            // Validate user is a member of the survey's org (via membership or resident link)
            $survey = $data->getQuestion()?->getSurvey();
            $organization = $survey?->getOrganization();

            if ($organization) {
                $hasMembership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                    'user' => $user,
                    'organization' => $organization,
                ]);

                $hasResident = $this->em->createQueryBuilder()
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

                if (!$hasMembership && $hasResident == 0) {
                    throw new AccessDeniedHttpException('You are not a member of this organization.');
                }

                // Calculate weight from Resident.ownedArea
                $weight = $this->em->createQueryBuilder()
                    ->select('SUM(r.ownedArea)')
                    ->from(Resident::class, 'r')
                    ->join('r.apartment', 'a')
                    ->join('a.building', 'b')
                    ->where('r.user = :user')
                    ->andWhere('b.organization = :org')
                    ->setParameter('user', $user)
                    ->setParameter('org', $organization)
                    ->getQuery()
                    ->getSingleScalarResult();

                $data->setWeight($weight ?: '0.00');
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
