<?php

namespace App\Console\Commands;

use App\Services\AutoMatchingService;
use Illuminate\Console\Command;

class RunAutoMatching extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matching:auto-run';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run auto-matching algorithm for lost and found items';


    /**
     * Execute the console command.
     */
    public function handle(AutoMatchingService $autoMatchingService)
    {
        $this->info('Starting auto-matching process...');

        $result = $autoMatchingService->runAutoMatching();

        if ($result['success']) {
            $this->info($result['message']);
        } else {
            $this->error($result['message']);
        }
    }
}
