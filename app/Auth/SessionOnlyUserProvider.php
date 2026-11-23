<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

/**
 * Session guard that never restores a user from a remember-me cookie.
 *
 * "Remember me" is an OTP-skip window only. The login session still ends
 * at the normal session lifetime, and the next visit must send credentials.
 */
class SessionOnlyUserProvider extends EloquentUserProvider
{
    /**
     * @param  mixed  $identifier
     * @param  string  $token
     * @return null
     */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return null;
    }
}
