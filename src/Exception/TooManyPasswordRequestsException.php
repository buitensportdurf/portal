<?php

namespace App\Exception;

use DateTime;
use DateTimeInterface;

final class TooManyPasswordRequestsException extends \Exception
{
    public function __construct(
        private readonly DateTimeInterface $availableAt, string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getAvailableAt(): DateTimeInterface
    {
        return $this->availableAt;
    }

    public function getRetryAfter(): int
    {
        return $this->getAvailableAt()->getTimestamp() - (new DateTime('now'))->getTimestamp();
    }

    public function getReason(): string
    {
        return 'You have already requested a reset password email. Please check your email or try again soon.';
    }
}
