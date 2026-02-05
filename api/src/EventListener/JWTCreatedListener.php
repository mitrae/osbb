<?php

namespace App\EventListener;

use App\Entity\Admin;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        if ($user instanceof User) {
            $payload['id'] = $user->getId();
            $payload['firstName'] = $user->getFirstName();
            $payload['lastName'] = $user->getLastName();
            $payload['type'] = 'user';
        } elseif ($user instanceof Admin) {
            $payload['id'] = $user->getId();
            $payload['type'] = 'admin';
        }

        $event->setData($payload);
    }
}
