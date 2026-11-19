<?php

use Illuminate\Support\Facades\Schedule;

// Close expired auctions and record the winners.
Schedule::command('auctions:close')->everyMinute()->withoutOverlapping();
