<?php

namespace Nexelity\Bprof\Console;

use Nexelity\Bprof\Models\Trace;
use Illuminate\Console\Command;

class TrimBprofTraces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bprof:trim {ageInHours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trims bprof traces older than X hours from the database';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $hours = (int) $this->argument('ageInHours');
        $this->info(sprintf('Clearing bprof traces older than %d hours...', $hours));
        Trace::query()
            ->whereDate('created_at', '<', now()->subHours($hours))
            ->delete();
        $this->info('Done!');
    }
}
