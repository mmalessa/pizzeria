<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Service;

use Pizzeria\Kitchen\Domain\ValueObject\EstimatedWaitTime;

/**
 * doc/08_kitchen_value_objects.md — computed at AcceptOrder time from queue
 * depth, active chef count, and a configured preparation time. The
 * per-pizza preparation time is a placeholder here — doc/09_architecture.md
 * §6 leaves the runtime/deployment story open, and per-pizza timing isn't
 * decided anywhere in doc/08_kitchen_*.md either; this exists to produce a
 * plausible, non-zero estimate for OrderAccepted, not a tuned figure.
 */
final class WaitTimeEstimationPolicy
{
    private const int SECONDS_PER_PIZZA = 300;

    public function estimate(int $pizzasAlreadyQueued, int $activeChefCount, int $pizzasInThisOrder): EstimatedWaitTime
    {
        $effectiveChefCount = max(1, $activeChefCount);
        $totalPizzasAhead = $pizzasAlreadyQueued + $pizzasInThisOrder;
        $batches = (int) ceil($totalPizzasAhead / $effectiveChefCount);

        return new EstimatedWaitTime($batches * self::SECONDS_PER_PIZZA);
    }
}
