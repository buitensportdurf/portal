<?php

namespace App\Security;

/**
 * Keeps redirect targets taken from a request on this site, so a crafted link cannot send
 * someone elsewhere.
 */
final class LocalPath
{
    public static function filter(mixed $path): ?string
    {
        return is_string($path) && preg_match('#^/(?![/\\\\])#', $path) ? $path : null;
    }
}
