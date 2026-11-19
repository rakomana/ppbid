<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in South African Rand, e.g. 1250 => "R1,250.00".
     */
    public static function format(float|int|string|null $amount): string
    {
        return 'R'.number_format((float) $amount, 2);
    }
}
