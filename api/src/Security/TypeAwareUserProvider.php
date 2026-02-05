<?php

namespace App\Security;

use App\Entity\Admin;
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
        if (str_starts_with($identifier, 'admin:')) {
            $email = substr($identifier, 6);
            $user = $this->entityManager->getRepository(Admin::class)->findOneBy(['email' => $email]);
            if ($user) {
                return $user;
            }
            throw new UserNotFoundException(sprintf('Admin "%s" not found.', $email));
        }

        if (str_starts_with($identifier, 'user:')) {
            $email = substr($identifier, 5);
        } else {
            // Fallback: no prefix means legacy token, assume user
            $email = $identifier;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            return $user;
        }

        throw new UserNotFoundException(sprintf('User "%s" not found.', $email));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($user instanceof Admin) {
            return $this->loadUserByIdentifier('admin:' . $user->getEmail());
        }

        if ($user instanceof User) {
            return $this->loadUserByIdentifier('user:' . $user->getEmail());
        }

        throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || $class === Admin::class;
    }
}
