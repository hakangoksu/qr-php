<?php

namespace App\Command;

use App\RequestLogging\RequestLogRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bin/console app:request-log:prune
 * Deletes old request log rows now.
 */
#[AsCommand(name: 'app:request-log:prune', description: 'Delete request log rows older than the retention period')]
final class PruneRequestLogCommand extends Command
{
    public function __construct(private readonly RequestLogRetention $retention)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Deleted %d request log rows.', $this->retention->prune()));

        return Command::SUCCESS;
    }
}
