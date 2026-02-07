<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            // Protect roles: only platform admins can change roles
            if (!$this->security->isGranted('ROLE_PLATFORM_ADMIN')) {
                if ($operation instanceof Patch && isset($context['previous_data'])) {
                    $data->setRoles($context['previous_data']->getRoles());
                } else {
                    $data->setRoles(['ROLE_USER']);
                }
            }

            if ($data->getPlainPassword()) {
                $data->setPassword($this->passwordHasher->hashPassword($data, $data->getPlainPassword()));
                $data->eraseCredentials();
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
