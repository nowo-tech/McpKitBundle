<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

use Nowo\McpKitBundle\Event\McpToolCallEvent;

/**
 * Optional host hook (`hooks.principal_refresher`): rebuilds the principal after a write unit cleared the entity
 * managers (a replay, or any conflict, `committed` included), so the single {@see McpToolCallEvent}
 * carries a managed `$user` and `$hostContext` again. When set, it replaces the default refresh through the firewall's
 * user provider. A failure is logged and the event goes out with the original principal: it is never dropped.
 */
interface McpPrincipalRefresherInterface
{
    /**
     * The principal with its host objects reloaded.
     *
     * @param McpPrincipal $principal the principal of the call (its objects may be detached)
     *
     * @return McpPrincipal the refreshed principal (same token id, name, tick and grants)
     */
    public function refresh(McpPrincipal $principal): McpPrincipal;
}
