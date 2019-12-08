<?php

namespace Nevadskiy\Tokens\Console;

use Illuminate\Console\Command;
use Nevadskiy\Tokens\TokenEntity;

class ClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear expired, used and soft deleted tokens from the database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        TokenEntity::dead()->forceDelete();
        $this->info('All dead tokens have been removed.');
    }
}
