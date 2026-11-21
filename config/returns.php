<?php

/*
|--------------------------------------------------------------------------
| Returns
|--------------------------------------------------------------------------
|
| Winners can log a return for faulty units. Returns are never refunded:
| faulty units are replaced.
|
*/

return [

    // Days after the auction ends that the winner may log a return.
    'window_days' => (int) env('RETURNS_WINDOW_DAYS', 7),

];
