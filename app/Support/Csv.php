<?php

namespace App\Support;

class Csv
{
    /** Mencegah teks dibaca sebagai rumus oleh Excel atau Sheets. */
    public static function safe(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}