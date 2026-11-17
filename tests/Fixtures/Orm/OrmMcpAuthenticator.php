<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Fixtures\Orm;

use Nowo\McpKitBundle\Http\McpHttpRequest;
use Nowo\McpKitBundle\Security\McpAuthFailure;
use Nowo\McpKitBundle\Security\McpPrincipal;
use Nowo\McpKitBundle\Security\McpRequestAuthenticatorInterface;

/**
 * Host authenticator whose principal user is a managed entity loaded from the host entity manager.
 */
final class OrmMcpAuthenticator implements McpRequestAuthenticatorInterface
{
    public function __construct(
        private readonly FixtureOrm $orm,
    ) {
    }

    public function authenticateRequest(McpHttpRequest $request): McpPrincipal|McpAuthFailure
    {
        if ($request->credential() !== 'tok-grace') {
            return McpAuthFailure::Invalid;
        }
        $user = $this->orm->em->getRepository(OrmUser::class)->findOneBy(['username' => 'grace']);

        return $user instanceof OrmUser ? new McpPrincipal($user, 'id-grace', 'Grace full', true, ['demo' => 'full']) : McpAuthFailure::Invalid;
    }
}
