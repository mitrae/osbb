<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Admin;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MembershipProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrganizationMembership) {
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

    private function handlePost(OrganizationMembership $data, Operation $operation, array $uriVariables, array $context): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Only users can request membership.');
        }

        $data->setUser($user);
        $data->setStatus(OrganizationMembership::STATUS_PENDING);
        // Regular users cannot set their own role — force ROLE_RESIDENT
        $data->setRole(OrganizationMembership::ROLE_RESIDENT);

        // Check for existing membership
        $existing = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $user,
            'organization' => $data->getOrganization(),
        ]);
        if ($existing) {
            throw new BadRequestHttpException('You already have a membership for this organization.');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function handlePatch(OrganizationMembership $data, Operation $operation, array $uriVariables, array $context): mixed
    {
        $currentUser = $this->security->getUser();

        // Platform admins (Admin entity) can always approve/reject
        if ($currentUser instanceof Admin) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        // Check if current user is an org admin for this membership's organization
        $orgId = $data->getOrganization()?->getId();
        $callerMembership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
            'user' => $currentUser,
            'organization' => $data->getOrganization(),
            'status' => OrganizationMembership::STATUS_APPROVED,
        ]);

        if (!$callerMembership || $callerMembership->getRole() !== OrganizationMembership::ROLE_ADMIN) {
            throw new AccessDeniedHttpException('Only organization admins can manage memberships.');
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
