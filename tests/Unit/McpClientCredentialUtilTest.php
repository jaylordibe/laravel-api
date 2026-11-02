<?php

namespace Tests\Unit;

use App\Utils\McpClientCredentialUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the parsing and matching of MCP client credentials (`MCP_CLIENT_TOKENS`) and allowed
 * origins (`MCP_ALLOWED_ORIGINS`). A parser that silently dropped a malformed entry would leave an
 * operator believing a client is configured when it is not; one that accepted a raw token as a
 * digest would put the secret in the environment.
 */
class McpClientCredentialUtilTest extends TestCase
{

    #[Test]
    public function itReadsNameDigestPairsToleratingSpacesAndEmptyEntries(): void
    {
        $n8nDigest = hash('sha256', 'n8n-token');
        $claudeDigest = hash('sha256', 'claude-token');

        self::assertSame(
            ['n8n' => $n8nDigest, 'claude-code' => $claudeDigest],
            McpClientCredentialUtil::parseCredentials(" n8n:{$n8nDigest} , ,claude-code:{$claudeDigest},")
        );
    }

    #[Test]
    public function itTreatsAnEmptyValueAsNoClients(): void
    {
        self::assertSame([], McpClientCredentialUtil::parseCredentials(''));
        self::assertSame([], McpClientCredentialUtil::parseCredentials(' , '));
    }

    /**
     * Values that must be refused.
     *
     * @return array<string, array{0: string}>
     */
    public static function malformedCredentials(): array
    {
        $digest = hash('sha256', 'n8n-token');
        $otherDigest = hash('sha256', 'other-token');

        return [
            'a raw token instead of a digest' => ['n8n:not-a-digest'],
            'an uppercase digest' => ['n8n:' . strtoupper($digest)],
            'a short digest' => ['n8n:' . substr($digest, 1)],
            'a missing name' => [':' . $digest],
            'a name with spaces' => ['my client:' . $digest],
            'an uppercase name' => ['N8N:' . $digest],
            'a missing separator' => [$digest],
            'a repeated name' => ["n8n:{$digest},n8n:{$otherDigest}"],
            'a repeated digest' => ["n8n:{$digest},other:{$digest}"],
        ];
    }

    #[Test]
    #[DataProvider('malformedCredentials')]
    public function itRefusesMalformedCredentials(string $raw): void
    {
        self::assertNull(McpClientCredentialUtil::parseCredentials($raw));
    }

    #[Test]
    public function itReadsExactOrigins(): void
    {
        self::assertSame(
            ['https://a.example.com', 'http://localhost:6274'],
            McpClientCredentialUtil::parseOrigins('https://a.example.com, http://localhost:6274')
        );
        self::assertSame([], McpClientCredentialUtil::parseOrigins(''));
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
        self::assertNull(McpClientCredentialUtil::parseOrigins($raw));
    }

    #[Test]
    public function itFindsTheClientWhoseDigestMatches(): void
    {
        $credentials = ['n8n' => hash('sha256', 'n8n-token'), 'claude-code' => hash('sha256', 'claude-token')];

        self::assertSame('claude-code', McpClientCredentialUtil::findClientByToken('claude-token', $credentials));
        self::assertSame('n8n', McpClientCredentialUtil::findClientByToken('n8n-token', $credentials));
    }

    #[Test]
    public function itFindsNoClientForAnUnknownTokenOrTheDigestItself(): void
    {
        $credentials = ['n8n' => hash('sha256', 'n8n-token')];

        self::assertNull(McpClientCredentialUtil::findClientByToken('guess', $credentials));
        self::assertNull(McpClientCredentialUtil::findClientByToken(hash('sha256', 'n8n-token'), $credentials));
        self::assertNull(McpClientCredentialUtil::findClientByToken('n8n-token', []));
    }

}
