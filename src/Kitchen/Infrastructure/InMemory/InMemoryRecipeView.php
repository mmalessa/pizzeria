<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\RecipeViewInterface;
use Pizzeria\Kitchen\Domain\ValueObject\MenuItemId;

final class InMemoryRecipeView implements RecipeViewInterface
{
    /** @var array<string, array{name: string, ingredients: list<string>, recipe: string}> */
    private array $recipes = [];

    public function put(MenuItemId $menuItemId, array $recipe): void
    {
        $this->recipes[$menuItemId->toString()] = $recipe;
    }

    public function remove(MenuItemId $menuItemId): void
    {
        unset($this->recipes[$menuItemId->toString()]);
    }

    public function find(MenuItemId $menuItemId): ?array
    {
        return $this->recipes[$menuItemId->toString()] ?? null;
    }
}
