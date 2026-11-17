<?php

declare(strict_types=1);

namespace Nowo\McpKitBundle\Tests\Unit\DependencyInjection;

use Nowo\McpKitBundle\Bridge\TransactionDoctrine\TransactionDoctrineWriteRunner;
use Nowo\McpKitBundle\DependencyInjection\Configuration;
use Nowo\McpKitBundle\DependencyInjection\NowoMcpKitExtension;
use Nowo\McpKitBundle\Http\McpEndpointController;
use Nowo\McpKitBundle\Http\McpEndpointOptions;
use Nowo\McpKitBundle\Routing\McpRouteLoader;
use Nowo\McpKitBundle\Security\UserProviderPrincipalRefresher;
use Nowo\McpKitBundle\Server\McpToolInvoker;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function str_repeat;

#[CoversClass(NowoMcpKitExtension::class)]
#[CoversClass(Configuration::class)]
#[CoversClass(McpEndpointOptions::class)]
final class NowoMcpKitExtensionTest extends TestCase
{
    #[Test]
    public function defaultsRegisterOnlyTheRouteLoader(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([], $container);

        self::assertFalse($container->getParameter('nowo_mcp_kit.enabled'));
        self::assertSame('/mcp', $container->getParameter('nowo_mcp_kit.path'));
        self::assertTrue($container->hasDefinition(McpRouteLoader::class));
        self::assertFalse($container->hasDefinition(McpEndpointController::class));
        self::assertSame('nowo_mcp_kit', (new NowoMcpKitExtension())->getAlias());
    }

    #[Test]
    public function enabledRequiresAnAuthenticator(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('hooks.authenticator is required');
        (new NowoMcpKitExtension())->load([['enabled' => true]], new ContainerBuilder());
    }

    #[Test]
    public function enabledRegistersTheEndpoint(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([['enabled' => true, 'hooks' => ['authenticator' => 'app.auth'], 'allowed_origins' => ['https://A.example']]], $container);

        self::assertTrue($container->getDefinition(McpEndpointController::class)->isPublic());
        self::assertSame(['https://a.example'], $container->getDefinition(NowoMcpKitExtension::OPTIONS_ID)->getArgument(0));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'relative path' => [['path' => 'mcp']];
        yield 'origin with path' => [['allowed_origins' => ['https://a.example/x']]];
        yield 'unknown legacy version' => [['legacy' => ['versions' => ['2025-03-26']]]];
        yield 'empty legacy versions' => [['legacy' => ['versions' => []]]];
        yield 'duplicate legacy versions' => [['legacy' => ['versions' => ['2025-11-25', '2025-11-25']]]];
        yield 'control in server name' => [['server_info' => ['name' => "a\x00"]]];
        yield 'server name not a string' => [['server_info' => ['name' => ['x']]]];
        yield 'control in title' => [['server_info' => ['title' => "a\nb"]]];
        yield 'long version' => [['server_info' => ['version' => str_repeat('v', 129)]]];
        yield 'small body cap' => [['limits' => ['max_body_bytes' => 10]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Test]
    #[DataProvider('invalidConfigs')]
    public function rejectsInvalidConfiguration(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new NowoMcpKitExtension())->load([$config], new ContainerBuilder());
    }

    #[Test]
    public function optionsListServedVersions(): void
    {
        $options = new McpEndpointOptions();
        self::assertSame(['2026-07-28', '2025-11-25', '2025-06-18'], $options->supportedVersions());
        self::assertTrue($options->isLegacyVersion('2025-06-18'));

        $off = new McpEndpointOptions([], false);
        self::assertSame(['2026-07-28'], $off->supportedVersions());
        self::assertFalse($off->isLegacyVersion('2025-11-25'));
    }

    #[Test]
    public function theFirewallCheckIsOffOnlyInDebug(): void
    {
        $config = [['enabled' => true, 'firewall_check' => false, 'hooks' => ['authenticator' => 'app.auth']]];
        $debug  = new ContainerBuilder();
        $debug->setParameter('kernel.debug', true);
        (new NowoMcpKitExtension())->load($config, $debug);
        self::assertTrue($debug->hasDefinition(McpEndpointController::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('tests only');
        (new NowoMcpKitExtension())->load($config, new ContainerBuilder());
    }

    #[Test]
    public function theWriteUnitIsOffByDefault(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([['enabled' => true, 'hooks' => ['authenticator' => 'app.auth']]], $container);

        self::assertFalse($container->hasDefinition(TransactionDoctrineWriteRunner::class));
        self::assertNull($container->getDefinition(McpToolInvoker::class)->getArgument(8));
        self::assertNull($container->getDefinition(McpToolInvoker::class)->getArgument(9));
        self::assertFalse($container->hasDefinition(UserProviderPrincipalRefresher::class));
    }

    #[Test]
    public function theWriteUnitOptionWiresTheTransactionDoctrineRunner(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([['enabled' => true, 'hooks' => ['authenticator' => 'app.auth'], 'write_unit' => ['enabled' => true]]], $container);

        $runner = $container->getDefinition(TransactionDoctrineWriteRunner::class);
        $unit   = $runner->getArgument(0);
        $policy = $runner->getArgument(1);
        self::assertInstanceOf(Reference::class, $unit);
        self::assertSame(WriteUnitInterface::class, (string) $unit);
        self::assertInstanceOf(Reference::class, $policy);
        self::assertSame(ConflictResponsePolicy::class, (string) $policy);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $policy->getInvalidBehavior());

        $argument = $container->getDefinition(McpToolInvoker::class)->getArgument(8);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(TransactionDoctrineWriteRunner::class, (string) $argument);

        $refresher = $container->getDefinition(McpToolInvoker::class)->getArgument(9);
        self::assertInstanceOf(Reference::class, $refresher);
        self::assertSame(UserProviderPrincipalRefresher::class, (string) $refresher);
        $provider = $container->getDefinition(UserProviderPrincipalRefresher::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $provider);
        self::assertSame('security.user_providers', (string) $provider);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $provider->getInvalidBehavior());
    }

    #[Test]
    public function theHostPrincipalRefresherWins(): void
    {
        $container = new ContainerBuilder();
        (new NowoMcpKitExtension())->load([['enabled' => true, 'hooks' => ['authenticator' => 'app.auth', 'principal_refresher' => 'app.refresher'], 'write_unit' => ['enabled' => true]]], $container);

        self::assertFalse($container->hasDefinition(UserProviderPrincipalRefresher::class));
        self::assertSame('app.refresher', (string) $container->getDefinition(McpToolInvoker::class)->getArgument(9));
    }

    #[Test]
    public function theWriteUnitOptionNeedsTransactionDoctrine(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('needs nowo-tech/transaction-doctrine ^1.3');
        (new ReflectionMethod(NowoMcpKitExtension::class, 'requireWriteUnit'))->invoke(new NowoMcpKitExtension(), 'Nowo\\Missing\\WriteUnitInterface');
    }
}
