<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Command;

final class PickUpPizzaFromQueue
{
    public function __construct(public readonly string $chefId)
    {
    }
}
