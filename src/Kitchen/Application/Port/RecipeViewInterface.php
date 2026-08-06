<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\ValueObject\MenuItemId;

/**
 * Recipe (kitchen view) read model (doc/08_kitchen_read_models.md) — fed by
 * Resource Management's MenuItemAdded/Updated/Disabled/Enabled over Kafka.
 * Name/ingredients/recipe only, no price (doc/07_define_context_map.md §6).
 */
interface RecipeViewInterface
{
    /**
     * @param array{name: string, ingredients: list<string>, recipe: string} $recipe
     */
    public function put(MenuItemId $menuItemId, array $recipe): void;

    public function remove(MenuItemId $menuItemId): void;

    /**
     * @return array{name: string, ingredients: list<string>, recipe: string}|null
     */
    public function find(MenuItemId $menuItemId): ?array;
}
