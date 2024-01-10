<?php

namespace Nexelity\Bprof\Console;

use Nexelity\Bprof\Models\Trace;
use Illuminate\Console\Command;

class TruncateBprofTraces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bprof:truncate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncates all bprof traces from the database';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Truncating bprof traces...');
        Trace::query()->truncate();
        $this->info('Done!');
    }
}
