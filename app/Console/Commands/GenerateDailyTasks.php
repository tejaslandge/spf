<?php

namespace App\Console\Commands;

use App\Services\DailyTaskGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('operations:generate-tasks {date? : The date to generate tasks for (Y-m-d), defaults to today}')]
#[Description('Generate the daily operations tasks from system data (due EMIs, overdue follow-ups, KYC, payouts, pending loans)')]
class GenerateDailyTasks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyTaskGenerator $generator): int
    {
        $argument = $this->argument('date');

        try {
            $date = $argument ? Carbon::createFromFormat('Y-m-d', (string) $argument)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            $this->error('Invalid date. Use the Y-m-d format, e.g. 2026-11-03.');

            return self::FAILURE;
        }

        $created = $generator->generateForDate($date);

        $this->info("Generated {$created} daily operation task(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
