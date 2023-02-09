<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunSampleJob extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:sample-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run sample job.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('STARTED SAMPLE JOB - ' . now()->toDayDateTimeString());

        $numbers = [1, 2, 3, 4, 5];

        $start = now();
        $bar = $this->output->createProgressBar(count($numbers));
        $bar->start();

        foreach ($numbers as $number) {
            Log::debug($number);
            $bar->advance();
        }

        $bar->finish();
        $time = $start->diff(now());
        $this->comment("\nProcessed in $time->h hours and $time->i minutes and $time->s seconds");

        Log::info('FINISHED SAMPLE JOB - ' . now()->toDayDateTimeString());

        return 0;
    }
}
