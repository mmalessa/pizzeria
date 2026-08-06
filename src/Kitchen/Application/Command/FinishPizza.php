<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Command;

final class FinishPizza
{
    public function __construct(public readonly string $pizzaTaskId)
    {
    }
}
