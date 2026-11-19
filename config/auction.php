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

    /*
    |--------------------------------------------------------------------------
    | Default auction length (hours)
    |--------------------------------------------------------------------------
    |
    | New listings expire this many hours after they're created, unless the
    | admin picks a different expiry on the form.
    |
    */

    'default_duration_hours' => (int) env('AUCTION_DEFAULT_DURATION_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | "Ending soon" notification (minutes)
    |--------------------------------------------------------------------------
    |
    | Bidders get one in-app notification when a listing they bid on has this
    | many minutes or fewer left.
    |
    */

    'ending_soon_minutes' => (int) env('AUCTION_ENDING_SOON_MINUTES', 60),

];
