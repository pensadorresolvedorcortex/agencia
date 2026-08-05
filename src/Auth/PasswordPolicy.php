<?php
/** Password validation shared by sensitive account flows. @package PGW */
declare(strict_types=1);
namespace PGW\Auth;
final class PasswordPolicy
{
    public function accepts(string $password, string $confirmation, int $minimumLength = 10): bool
    {
        $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
        return $length >= max(10, $minimumLength) && hash_equals($password, $confirmation);
    }
}
