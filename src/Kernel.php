<?php

declare(strict_types=1);

namespace Pizzeria;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * One Symfony kernel shared by every Bounded Context (doc/09_architecture.md §1).
 * Kitchen is the only context wired in for this pass.
 *
 * MicroKernelTrait auto-loads config/bundles.php, config/packages/*.yaml,
 * config/services.yaml, and config/routes.yaml by convention — no
 * overrides needed for this project's layout.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
