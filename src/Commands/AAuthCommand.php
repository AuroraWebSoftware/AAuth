<?php

namespace Aurora\AAuth\Commands;

use Illuminate\Console\Command;

class AAuthCommand extends Command
{
    public $signature = 'aauth';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
