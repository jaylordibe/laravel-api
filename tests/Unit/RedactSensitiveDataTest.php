<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Production logs leave the container and land in an aggregator that is indexed,
 * searched and broadly readable, so a credential written there has to be treated
 * as disclosed. These tests pin the redaction that stops the common accidents.
 */
class RedactSensitiveDataTest extends TestCase
{

    private TestHandler $handler;

    private Monolog $monolog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestHandler();
        $this->monolog = new Monolog('test', [$this->handler]);

        (new RedactSensitiveData())(new Logger($this->monolog));
    }

    #[Test]
    public function itRedactsSensitiveContextKeys(): void
    {
        $this->monolog->info('sign-in failed', [
            'email' => 'user@example.com',
            'password' => 'hunter2',
            'api_key' => 'sk_live_abcdef',
            'accessToken' => 'abc123',
        ]);

        $context = $this->firstRecord()->context;

        // The non-secret key survives: redaction that eats the diagnostic value
        // of the log gets turned off by the next person to debug an incident.
        self::assertSame('user@example.com', $context['email']);
        self::assertSame(RedactSensitiveData::PLACEHOLDER, $context['password']);
        self::assertSame(RedactSensitiveData::PLACEHOLDER, $context['api_key']);
        self::assertSame(RedactSensitiveData::PLACEHOLDER, $context['accessToken']);
    }

    #[Test]
    public function itRedactsNestedContextKeys(): void
    {
        // Exception context and serialized job payloads are nested, which is
        // where a top-level-only scrub quietly misses everything.
        $this->monolog->error('job failed', [
            'job' => [
                'payload' => [
                    'user' => 'jay',
                    'db_password' => 'super-secret',
                ],
            ],
        ]);

        $context = $this->firstRecord()->context;

        self::assertSame('jay', $context['job']['payload']['user']);
        self::assertSame(RedactSensitiveData::PLACEHOLDER, $context['job']['payload']['db_password']);
    }

    #[Test]
    public function itRedactsBearerTokensInMessages(): void
    {
        $this->monolog->warning('rejected Authorization: Bearer eyJhbGciOiJSUzI1NiJ9.payload.sig');

        $message = $this->firstRecord()->message;

        self::assertStringNotContainsString('eyJhbGciOiJSUzI1NiJ9', $message);
        // The fact that a bearer token was present is retained — that is the
        // part a person debugging a 401 actually needs.
        self::assertStringContainsString('Bearer ' . RedactSensitiveData::PLACEHOLDER, $message);
    }

    #[Test]
    public function itRedactsCredentialsEmbeddedInConnectionStrings(): void
    {
        $this->monolog->error('could not connect to redis://default:s3cr3tpass@cache.internal:6379');

        $message = $this->firstRecord()->message;

        self::assertStringNotContainsString('s3cr3tpass', $message);
        // Host and user remain, so the log still says WHERE it failed.
        self::assertStringContainsString('cache.internal', $message);
        self::assertStringContainsString('redis://default:', $message);
    }

    #[Test]
    public function itRedactsSignedUrlQueryParameters(): void
    {
        // A signed URL is a bearer credential for one object. Logging one whole
        // hands read access to anyone with log access, for its whole lifetime.
        $this->monolog->info('generated https://bucket.example.com/a/b.png?X-Amz-Signature=deadbeefcafe&X-Amz-Expires=900');

        $message = $this->firstRecord()->message;

        self::assertStringNotContainsString('deadbeefcafe', $message);
        self::assertStringContainsString('bucket.example.com/a/b.png', $message);
    }

    #[Test]
    public function itRedactsPemPrivateKeyBlocks(): void
    {
        $key = "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA\n-----END RSA PRIVATE KEY-----";

        $this->monolog->critical('passport key rejected: ' . $key);

        $message = $this->firstRecord()->message;

        self::assertStringNotContainsString('MIIEowIBAAKCAQEA', $message);
        self::assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $message);
    }

    #[Test]
    public function itLeavesOrdinaryRecordsUntouched(): void
    {
        // A redactor that mangles normal logs is worse than none, because people
        // stop trusting what they read.
        $this->monolog->info('user 42 updated their profile', ['userId' => 42, 'field' => 'address']);

        $record = $this->firstRecord();

        self::assertSame('user 42 updated their profile', $record->message);
        self::assertSame(['userId' => 42, 'field' => 'address'], $record->context);
    }

    #[Test]
    public function everyChannelThatWritesSomewhereRedactsSecrets(): void
    {
        // The processor existing is not the same as it being wired up, and
        // asserting only `stderr` is how a MISPLACED tap survived: it had been
        // nested inside papertrail's `handler_with`, where Laravel passes it to
        // the handler constructor and silently ignores it — so that channel wrote
        // unredacted secrets over UDP to a third party while the suite stayed
        // green.
        //
        // `stack` delegates to its members and `null` discards everything, so
        // neither needs its own tap. `browser` is registered by laravel/boost,
        // a require-dev package, so it does not exist in the production image
        // built with `composer install --no-dev`.
        $exempt = ['stack', 'null', 'emergency', 'browser'];
        $untapped = [];

        foreach (config('logging.channels') as $name => $channel) {
            if (in_array($name, $exempt, true) || !isset($channel['driver'])) {
                continue;
            }

            if (!in_array(RedactSensitiveData::class, $channel['tap'] ?? [], true)) {
                $untapped[] = $name;
            }
        }

        self::assertSame(
            [],
            $untapped,
            'These log channels would write secrets unredacted: ' . implode(', ', $untapped)
        );
    }

    #[Test]
    public function noChannelHidesTheTapInsideHandlerOptions(): void
    {
        // The specific shape of the bug: a `tap` key inside `handler_with` looks
        // right in a diff and does nothing at runtime.
        foreach (config('logging.channels') as $name => $channel) {
            self::assertArrayNotHasKey(
                'tap',
                $channel['handler_with'] ?? [],
                "Channel [{$name}] has a tap inside handler_with, where Laravel ignores it."
            );
        }
    }

    #[Test]
    public function itRedactsSecretsInsideAThrowableInContext(): void
    {
        // THE most likely carrier of a secret, and the one the original
        // implementation missed: Laravel's exception handler always logs
        // ['exception' => $e], and a QueryException interpolates its bindings
        // into the message. Processors run before handlers, so the object is
        // still live here — passing it through meant the formatter printed the
        // message verbatim later, right beside the scrubbed string copy.
        $exception = new RuntimeException('connect failed for redis://default:s3cr3tpass@cache.internal:6379');

        $this->monolog->error('job failed', ['exception' => $exception]);

        $context = $this->firstRecord()->context;

        self::assertIsArray($context['exception'], 'The Throwable must be reduced to a scrubbed array.');
        self::assertStringNotContainsString('s3cr3tpass', $context['exception']['message']);
        self::assertStringContainsString('cache.internal', $context['exception']['message']);
        self::assertSame(RuntimeException::class, $context['exception']['class']);
    }

    #[Test]
    public function itRedactsSecretsInAChainedPreviousException(): void
    {
        $root = new RuntimeException('Bearer eyJhbGciOiJSUzI1NiJ9.payload.sig');
        $wrapper = new RuntimeException('wrapping failure', 0, $root);

        $this->monolog->error('failed', ['exception' => $wrapper]);

        $previous = $this->firstRecord()->context['exception']['previous'];

        self::assertStringNotContainsString('eyJhbGciOiJSUzI1NiJ9', $previous['message']);
    }

    #[Test]
    public function itDoesNotBlankTheRecordWhenAPatternFailsToExecute(): void
    {
        // preg_replace returns null past pcre.backtrack_limit. Casting that to
        // string wiped the whole line, so the redactor destroyed the very record
        // describing the request that triggered it.
        $hostile = 'a://' . str_repeat('x', 200000);

        $this->monolog->warning($hostile);

        $message = $this->firstRecord()->message;

        self::assertNotSame('', $message, 'A failed pattern must never blank the log line.');
    }

    /**
     * @return LogRecord
     */
    private function firstRecord(): LogRecord
    {
        $records = $this->handler->getRecords();

        self::assertNotEmpty($records, 'Expected a log record to be handled.');

        return $records[0];
    }

}
