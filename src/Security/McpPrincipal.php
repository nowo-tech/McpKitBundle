<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Security;

/**
 * Who a request is, as the host's authenticator answers it, for this request only. It holds no secret: `$tokenId` is
 * an opaque id.
 */
final readonly class McpPrincipal
{
    /**
     * Creates the principal.
     *
     * @param object $user the person behind the token; must be the firewall's security user
     * @param string $tokenId opaque token id (never the secret)
     * @param string $tokenName token name chosen by its person
     * @param bool $destructiveAllowed whether the token may use destructive tools (off by default in hosts)
     * @param array<string, string> $grants opaque host data for the authorizer, e.g. `['tasks' => 'my_tasks']`
     * @param object|null $hostContext anything else the host builds for its tools on this request
     */
    public function __construct(
        public object $user,
        public string $tokenId,
        public string $tokenName,
        public bool $destructiveAllowed = false,
        public array $grants = [],
        public ?object $hostContext = null,
    ) {
    }
}
