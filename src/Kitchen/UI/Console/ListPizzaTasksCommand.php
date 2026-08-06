<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\UI\Console;

use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Verification/demo harness — see PickUpPizzaCommand. Note: since each
 * RoadRunner worker holds its own in-memory state, this only shows what
 * *this* worker process has seen — see the session note on worker pool
 * size for this pass.
 */
#[AsCommand(name: 'kitchen:list-pizza-tasks', description: 'List all known PizzaTasks and their status.')]
final class ListPizzaTasksCommand extends Command
{
    public function __construct(private readonly PizzaTaskRepositoryInterface $pizzaTasks)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = array_map(
            static fn ($task): array => [
                $task->pizzaTaskId()->toString(),
                $task->kitchenOrderId()->toString(),
                $task->menuItemId()->toString(),
                $task->status(),
                $task->chefId()?->toString() ?? '-',
            ],
            $this->pizzaTasks->findAll(),
        );

        $io->table(['pizzaTaskId', 'kitchenOrderId', 'menuItemId', 'status', 'chefId'], $rows);

        return Command::SUCCESS;
    }
}
