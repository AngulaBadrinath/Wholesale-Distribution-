<?php

namespace App\Enums;

enum AccountStatus: string
{
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case DISABLED = 'DISABLED';

    /**
     * Determine whether an account in this state is permitted to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::ACTIVE;
    }
}
