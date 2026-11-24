<?php

namespace Cachet\Commands;

use Cachet\Actions\Component\RecordComponentUptime;
use Illuminate\Console\Command;

class RecordUptimeCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'cachet:record-uptime {--days=2 : How many days to recalculate, today included}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate the daily uptime history of every component';

    /**
     * Execute the console command.
     */
    public function handle(RecordComponentUptime $recordComponentUptime): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->components->error('The --days option must be at least 1.');

            return self::INVALID;
        }

        $written = $recordComponentUptime->handle($days);

        $this->components->info("Recorded {$written} component uptime days.");

        return self::SUCCESS;
    }
}
