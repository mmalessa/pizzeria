<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\UI\Console;

use Pizzeria\Kitchen\Application\Command\FinishPizza;
use Pizzeria\Kitchen\Application\Handler\FinishPizzaHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Verification/demo harness — see PickUpPizzaCommand's note on process
 * isolation with this pass's in-memory repositories.
 */
#[AsCommand(name: 'kitchen:finish-pizza', description: 'Manually drive FinishPizza for a PizzaTask.')]
final class FinishPizzaCommand extends Command
{
    public function __construct(private readonly FinishPizzaHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('pizzaTaskId', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->handler)(new FinishPizza((string) $input->getArgument('pizzaTaskId')));
        $output->writeln('OK');

        return Command::SUCCESS;
    }
}
