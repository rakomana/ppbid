<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum bid increment (ZAR)
    |--------------------------------------------------------------------------
    |
    | A new bid must beat the current top bid by at least this amount.
    |
    */

    'min_increment' => (float) env('AUCTION_MIN_INCREMENT', 10),

];
