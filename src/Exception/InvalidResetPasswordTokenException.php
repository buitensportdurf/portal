<?php

namespace App\Exception;

final class InvalidResetPasswordTokenException extends \Exception
{
    public function getReason(): string
    {
        return 'The reset password link is invalid. Please try to reset your password again.';
    }
}
