<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a phone number to a comparable digit string with a leading +.
     */
    public static function normalize(string $phone): string
    {
        $trimmed = trim($phone);

        if ($trimmed === '') {
            return '';
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        return ($hasPlus || strlen($digits) > 10 ? '+' : '').$digits;
    }
}
