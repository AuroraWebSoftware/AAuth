<?php

namespace AuroraWebSoftware\AAuth\Commands;

use Illuminate\Console\Command;

class AAuthCommand extends Command
{
    public $signature = 'aauth';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');
        $this->comment(config('aauth.aauth'));

        return self::SUCCESS;
    }
}
