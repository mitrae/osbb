<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ConnectionRequest;
use App\Entity\OrganizationMembership;
use App\Entity\Resident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ConnectionRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ConnectionRequest) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($operation instanceof Post) {
            return $this->handlePost($data, $operation, $uriVariables, $context);
        }

        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $operation, $uriVariables, $context);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function handlePost(ConnectionRequest $data, Operation $operation, array $uriVariables, array $context): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Only users can submit connection requests.');
        }

        $data->setUser($user);
        $data->setStatus(ConnectionRequest::STATUS_PENDING);

        // Validate building belongs to selected organization
        $building = $data->getBuilding();
        $organization = $data->getOrganization();
        if ($building && $organization && $building->getOrganization()?->getId() !== $organization->getId()) {
            throw new BadRequestHttpException('Building does not belong to the selected organization.');
        }

        // Validate apartment belongs to selected building
        $apartment = $data->getApartment();
        if ($apartment && $building && $apartment->getBuilding()?->getId() !== $building->getId()) {
            throw new BadRequestHttpException('Apartment does not belong to the selected building.');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function handlePatch(ConnectionRequest $data, Operation $operation, array $uriVariables, array $context): mixed
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        // Platform admins can always approve/reject
        if (!$currentUser->isPlatformAdmin()) {
            // Check org admin membership
            $orgId = $data->getOrganization()?->getId();
            $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $currentUser,
                'organization' => $orgId,
                'role' => OrganizationMembership::ROLE_ADMIN,
            ]);
            if (!$membership) {
                throw new AccessDeniedHttpException('Only organization admins can review connection requests.');
            }
        }

        // On approve: link resident to user and ensure org membership
        if ($data->getStatus() === ConnectionRequest::STATUS_APPROVED) {
            $resident = $data->getResident();
            if (!$resident) {
                throw new BadRequestHttpException('A resident must be specified when approving a connection request.');
            }
            $resident->setUser($data->getUser());
            $this->em->persist($resident);

            $this->ensureResidentMembership($data->getUser(), $resident);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function ensureResidentMembership(User $user, Resident $resident): void
    {
        $organization = $resident->getApartment()?->getBuilding()?->getOrganization();
        if (!$organization) {
            return;
        }

        // Skip if user already has any membership in this org (admin/manager trumps resident)
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
}
