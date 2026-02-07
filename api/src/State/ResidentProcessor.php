<?php

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ResidentProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Resident && $operation instanceof Delete) {
            $unlinkUser = $data->getUser();
            $data->setUser(null);

            if ($unlinkUser) {
                $this->removeResidentMembershipIfOrphan($unlinkUser, $data);
            }
        }

        if ($data instanceof Resident && $operation instanceof Patch) {
            $previous = $context['previous_data'] ?? null;

            // Org admin can only disconnect (set user to null), not edit other fields
            if (!$this->security->isGranted('ROLE_PLATFORM_ADMIN') && $previous instanceof Resident) {
                $data->setFirstName($previous->getFirstName());
                $data->setLastName($previous->getLastName());
                $data->setOwnedArea($previous->getOwnedArea());
                $data->setApartment($previous->getApartment());
            }

            // Detect user link/unlink
            $previousUser = $previous instanceof Resident ? $previous->getUser() : null;
            $newUser = $data->getUser();

            if (!$previousUser && $newUser) {
                $this->ensureResidentMembership($newUser, $data);
            } elseif ($previousUser && !$newUser) {
                $this->removeResidentMembershipIfOrphan($previousUser, $data);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function ensureResidentMembership(User $user, Resident $resident): void
    {
        $organization = $resident->getApartment()?->getBuilding()?->getOrganization();
        if (!$organization) {
            return;
        }

        $existing = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $organization,
        ]);
        if ($existing) {
            return;
        }

        $membership = new OrganizationMembership();
        $membership->setUser($user);
        $membership->setOrganization($organization);
        $membership->setRole(OrganizationMembership::ROLE_RESIDENT);
        $this->em->persist($membership);
    }

    private function removeResidentMembershipIfOrphan(User $user, Resident $excludeResident): void
    {
        $organization = $excludeResident->getApartment()?->getBuilding()?->getOrganization();
        if (!$organization) {
            return;
        }

        $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $organization,
            'role' => OrganizationMembership::ROLE_RESIDENT,
        ]);
        if (!$membership) {
            return;
        }

        // Check if user has other residents in this org
        $otherResidents = $this->em->getRepository(Resident::class)->createQueryBuilder('r')
            ->join('r.apartment', 'a')
            ->join('a.building', 'b')
            ->where('r.user = :user')
            ->andWhere('b.organization = :org')
            ->andWhere('r.id != :excludeId')
            ->setParameter('user', $user)
            ->setParameter('org', $organization)
            ->setParameter('excludeId', $excludeResident->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (empty($otherResidents)) {
            $this->em->remove($membership);
        }
    }
}
