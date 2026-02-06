<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;
use App\Entity\Organization;
use App\Entity\Request;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class RequestProcessor implements ProcessorInterface
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
        if ($data instanceof Request && $operation instanceof Post) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new AccessDeniedHttpException('Only residents can create requests.');
            }
            $data->setAuthor($user);

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

            // Validate user is an approved member of this org
            $membership = $this->em->getRepository(OrganizationMembership::class)->findOneBy([
                'user' => $user,
                'organization' => $organization,
                'status' => OrganizationMembership::STATUS_APPROVED,
            ]);
            if (!$membership) {
                throw new AccessDeniedHttpException('You are not an approved member of this organization.');
            }

            $data->setOrganization($organization);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
