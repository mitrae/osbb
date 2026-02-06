<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OrganizationMembership;

final class MembershipProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof OrganizationMembership) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Security is handled by ORG_ROLE_ADMIN attribute on the operations
        // The voter already checks platform admin or org admin membership
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
