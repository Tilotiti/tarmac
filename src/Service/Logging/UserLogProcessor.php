<?php

namespace App\Service\Logging;

use App\Entity\User;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserLogProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $user = $this->security->getUser();

        if ($user === null) {
            $record['extra']['user'] = [
                'authenticated' => false,
                'username' => 'anonymous',
            ];

            return $record;
        }

        $userData = [
            'authenticated' => true,
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        // Add additional data if user is our User entity
        if ($user instanceof User) {
            $userData['email'] = $user->getEmail();

            $fullName = $user->getFullName();
            if ($fullName) {
                $userData['full_name'] = $fullName;
            }
        }

        $record['extra']['user'] = $userData;

        return $record;
    }
}

