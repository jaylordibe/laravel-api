<?php

namespace Tests\Unit;

use App\Utils\McpConfigUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the parsing of the MCP endpoint's configuration and the redirect rule an MCP client's OAuth
 * registration is held to. A parser that silently dropped a malformed entry would leave an operator
 * believing a restriction is in place when it is not; a redirect rule that let plain http through would
 * send authorization codes over the wire in the clear.
 */
class McpConfigUtilTest extends TestCase
{

    #[Test]
    public function itReadsExactOrigins(): void
    {
        self::assertSame(
            ['https://a.example.com', 'http://localhost:6274'],
            McpConfigUtil::parseOrigins('https://a.example.com, http://localhost:6274')
        );
        self::assertSame([], McpConfigUtil::parseOrigins(''));
    }

    /**
     * Origins that must be refused.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformedOrigins(): array
    {
        return [
            'a path' => ['https://a.example.com/app'],
            'a trailing slash' => ['https://a.example.com/'],
            'a wildcard' => ['*'],
            'a bare host' => ['a.example.com'],
        ];
    }

    #[Test]
    #[DataProvider('malformedOrigins')]
    public function itRefusesMalformedOrigins(string $raw): void
    {
        self::assertNull(McpConfigUtil::parseOrigins($raw));
    }

    #[Test]
    public function itReadsHttpsRedirectOriginsWithEmptyMeaningAny(): void
    {
        self::assertSame(
            ['https://claude.ai', 'https://n8n.example.com'],
            McpConfigUtil::parseRedirectOrigins('https://claude.ai, ,https://n8n.example.com')
        );
        self::assertSame([], McpConfigUtil::parseRedirectOrigins(''));
    }

    /**
     * Redirect-origin settings that must be refused.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformedRedirectOrigins(): array
    {
        return [
            'plain http' => ['http://n8n.example.com'],
            'a path' => ['https://claude.ai/api'],
            'a wildcard' => ['*'],
            'a bare host' => ['claude.ai'],
        ];
    }

    #[Test]
    #[DataProvider('malformedRedirectOrigins')]
    public function itRefusesMalformedRedirectOrigins(string $raw): void
    {
        self::assertNull(McpConfigUtil::parseRedirectOrigins($raw));
    }

    #[Test]
    public function itRecognisesLoopbackRedirects(): void
    {
        self::assertTrue(McpConfigUtil::isLoopbackRedirectUri('http://localhost:3118/callback'));
        self::assertTrue(McpConfigUtil::isLoopbackRedirectUri('http://127.0.0.1/callback'));
        self::assertTrue(McpConfigUtil::isLoopbackRedirectUri('http://[::1]:9000/cb'));
        self::assertFalse(McpConfigUtil::isLoopbackRedirectUri('https://localhost/callback'));
        self::assertFalse(McpConfigUtil::isLoopbackRedirectUri('http://localhost.evil.example/callback'));
        self::assertFalse(McpConfigUtil::isLoopbackRedirectUri('http://user:pass@localhost/callback'));
    }

    #[Test]
    public function itAllowsLoopbackAndAnyHttpsRedirectWithoutAnAllowlist(): void
    {
        self::assertTrue(McpConfigUtil::isPermittedRedirectUri('http://localhost:3118/callback', []));
        self::assertTrue(McpConfigUtil::isPermittedRedirectUri('https://claude.ai/api/mcp/auth_callback', []));
    }

    #[Test]
    public function itNarrowsHttpsRedirectsToTheAllowlistButNeverLoopback(): void
    {
        $allowed = ['https://claude.ai'];

        self::assertTrue(McpConfigUtil::isPermittedRedirectUri('https://claude.ai/api/mcp/auth_callback', $allowed));
        self::assertFalse(McpConfigUtil::isPermittedRedirectUri('https://evil.example/callback', $allowed));
        self::assertTrue(McpConfigUtil::isPermittedRedirectUri('http://127.0.0.1/cb', $allowed));
    }

    /**
     * Redirect URIs a registering client must not use.
     *
     * @return array<string, array{0: string}>
     */
    public static function forbiddenRedirects(): array
    {
        return [
            'plain http to a public host' => ['http://n8n.example.com/callback'],
            'a custom scheme' => ['cursor://callback'],
            'a fragment' => ['https://claude.ai/cb#frag'],
            'credentials' => ['https://user:pass@claude.ai/cb'],
            'an unparseable value' => ['claude.ai/cb'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenRedirects')]
    public function itRefusesForbiddenRedirects(string $uri): void
    {
        self::assertFalse(McpConfigUtil::isPermittedRedirectUri($uri, []));
    }

}
