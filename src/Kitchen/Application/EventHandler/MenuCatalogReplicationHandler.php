<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\EventHandler;

use Pizzeria\Kitchen\Application\Port\RecipeViewInterface;
use Pizzeria\Kitchen\Domain\ValueObject\MenuItemId;
use Psr\Log\LoggerInterface;

/**
 * Consumes Resource Management's MenuItemAdded/Updated/Disabled/Enabled
 * over Kafka into the Recipe (kitchen view) read model
 * (doc/08_kitchen_integration_events.md, doc/07_define_context_map.md §6:
 * name/ingredients/recipe only, no price).
 */
final class MenuCatalogReplicationHandler
{
    public function __construct(
        private readonly RecipeViewInterface $recipeView,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array{menuItemId: string, name: string, ingredients: list<string>, recipe: string} $payload */
    public function onMenuItemAdded(array $payload): void
    {
        $this->put($payload);
    }

    /** @param array{menuItemId: string, name: string, ingredients: list<string>, recipe: string} $payload */
    public function onMenuItemUpdated(array $payload): void
    {
        $this->put($payload);
    }

    /** @param array{menuItemId: string} $payload */
    public function onMenuItemDisabled(array $payload): void
    {
        $this->recipeView->remove(MenuItemId::fromString($payload['menuItemId']));
        $this->logger->info('[Recipe] removed menuItemId {id}', ['id' => $payload['menuItemId']]);
    }

    /** @param array{menuItemId: string, name: string, ingredients: list<string>, recipe: string} $payload */
    public function onMenuItemEnabled(array $payload): void
    {
        $this->put($payload);
    }

    /** @param array{menuItemId: string, name: string, ingredients: list<string>, recipe: string} $payload */
    private function put(array $payload): void
    {
        $this->recipeView->put(
            MenuItemId::fromString($payload['menuItemId']),
            ['name' => $payload['name'], 'ingredients' => $payload['ingredients'], 'recipe' => $payload['recipe']],
        );
        $this->logger->info('[Recipe] put menuItemId {id} name={name}', ['id' => $payload['menuItemId'], 'name' => $payload['name']]);
    }
}
