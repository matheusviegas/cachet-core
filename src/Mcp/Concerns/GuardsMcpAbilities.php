<?php

namespace Cachet\Mcp\Concerns;

use Cachet\Concerns\CachetUser;
use Laravel\Mcp\Response;

trait GuardsMcpAbilities
{
    /**
     * Determine whether the current MCP session holds an API token with the given ability.
     * A token never exceeds the permissions of its owner's groups.
     */
    protected function tokenCan(string $ability): bool
    {
        $user = auth('sanctum')->user();

        return $user instanceof CachetUser
            && $user->tokenCan($ability)
            && $user->hasPermission($ability);
    }

    /**
     * Create the error response returned when the required token ability is missing.
     */
    protected function missingAbility(string $ability): Response
    {
        return Response::error("Unauthorized. This tool requires an API token with the [{$ability}] ability.");
    }
}
