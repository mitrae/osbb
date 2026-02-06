<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TypeAwareUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Strip legacy prefixes for backward compat with old tokens
        if (str_starts_with($identifier, 'admin:')) {
            $identifier = substr($identifier, 6);
        } elseif (str_starts_with($identifier, 'user:')) {
            $identifier = substr($identifier, 5);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        if ($user) {
            return $user;
        }

        throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getEmail());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }
}
