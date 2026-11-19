<?php

use Illuminate\Support\Facades\Schedule;

// Close expired auctions and record the winners.
Schedule::command('auctions:close')->everyMinute()->withoutOverlapping();

// Tell bidders when a listing they bid on is about to end.
Schedule::command('auctions:notify-ending-soon')->everyMinute()->withoutOverlapping();
