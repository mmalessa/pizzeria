<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\UI\Console;

use Pizzeria\Kitchen\Application\Command\PickUpPizzaFromQueue;
use Pizzeria\Kitchen\Application\Handler\PickUpPizzaFromQueueHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Verification/demo harness: in the real system a Chef's automated
 * behaviour (doc/01_understand.md §2.2) drives this, not an operator.
 *
 * NOTE: this command runs as its own OS process, separate from the
 * RoadRunner jobs worker that actually consumes OrderSentToKitchen — with
 * this pass's in-memory (non-shared) repositories, it will not see
 * PizzaTasks the jobs worker created. Kept for when real shared persistence
 * lands; for this pass, drive pick-up/finish through the jobs pipeline
 * instead (see JobDispatcher's internal-ops cases).
 */
#[AsCommand(name: 'kitchen:pick-up-pizza', description: 'Manually drive PickUpPizzaFromQueue for a chef.')]
final class PickUpPizzaCommand extends Command
{
    public function __construct(private readonly PickUpPizzaFromQueueHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('chefId', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->handler)(new PickUpPizzaFromQueue((string) $input->getArgument('chefId')));
        $output->writeln('OK');

        return Command::SUCCESS;
    }
}
