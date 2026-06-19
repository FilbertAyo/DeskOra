<?php

namespace App\Otp;

use App\Models\User;
use SadiqSalau\LaravelOtp\Contracts\OtpInterface;

class LoginInactivityOtp implements OtpInterface
{
    public function __construct(protected User $user) {}

    /**
     * @return array{user: User}
     */
    public function process(): array
    {
        return ['user' => $this->user];
    }
}
