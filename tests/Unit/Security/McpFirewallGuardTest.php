<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\Security;

use Nowo\McpKitBundle\Security\McpFirewallGuard;
use Nowo\McpKitBundle\Security\McpPrincipal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(McpFirewallGuard::class)]
final class McpFirewallGuardTest extends TestCase
{
    #[Test]
    public function offTrustsEverything(): void
    {
        self::assertTrue((new McpFirewallGuard(false))->isTrusted(Request::create('/mcp'), new McpPrincipal(new stdClass(), 'i', 'n')));
    }

    #[Test]
    public function withoutTokenStorageNothingIsTrusted(): void
    {
        self::assertFalse((new McpFirewallGuard(true))->isTrusted(Request::create('/mcp'), new McpPrincipal(new stdClass(), 'i', 'n')));
    }

    #[Test]
    public function withoutTheSecurityHelperTheFirewallContextAttributeDecides(): void
    {
        $user    = new InMemoryUser('ada', '');
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main'));
        $guard   = new McpFirewallGuard(true, $storage);
        $request = Request::create('/mcp');

        self::assertFalse($guard->isTrusted($request, new McpPrincipal($user, 'i', 'n')));
        $request->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        self::assertTrue($guard->isTrusted($request, new McpPrincipal($user, 'i', 'n')));
        self::assertTrue($guard->isTrusted($request, new McpPrincipal(new InMemoryUser('ada', 'x'), 'i', 'n')));
        self::assertFalse($guard->isTrusted($request, new McpPrincipal(new InMemoryUser('grace', ''), 'i', 'n')));
        self::assertFalse($guard->isTrusted($request, new McpPrincipal(new stdClass(), 'i', 'n')));
    }

    #[Test]
    public function withoutTheSecurityHelperAStartedSessionIsRefused(): void
    {
        $user    = new InMemoryUser('ada', '');
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main'));
        $request = Request::create('/mcp');
        $request->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $guard = new McpFirewallGuard(true, $storage);

        self::assertTrue($guard->isTrusted($request, new McpPrincipal($user, 'i', 'n')));
        $session->start();
        self::assertFalse($guard->isTrusted($request, new McpPrincipal($user, 'i', 'n')));
    }
}
