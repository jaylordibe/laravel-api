<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

/**
 * Monolog "tap" that strips credentials and bearer tokens out of log records
 * before a handler ever writes them.
 *
 * Production logs go to stdout/stderr, which means they leave the container and
 * land in a log aggregator that is backed up, indexed, searched and typically
 * readable by more people than the database is. A token that reaches that
 * pipeline has to be treated as disclosed, and it cannot be recalled.
 *
 * Nothing here deliberately logs a secret. The leaks are incidental: an exception
 * whose context carries the request payload, a failed job serialized with its
 * arguments, a connection error quoting the DSN it tried, a signed URL logged as
 * "the file we could not fetch". Each is a different code path, so redaction is
 * applied centrally at the Monolog layer rather than at each call site — the
 * call site that forgets is exactly the one that leaks.
 *
 * This reduces exposure; it is not a licence to log secrets deliberately.
 */
class RedactSensitiveData
{

    /**
     * Placeholder written in place of a redacted value.
     *
     * @var string
     */
    public const string PLACEHOLDER = '[redacted]';

    /**
     * Context and "extra" keys whose VALUE is replaced wholesale, matched
     * case-insensitively and ignoring separators, so `api_key`, `apiKey` and
     * `API-KEY` are all covered by one entry.
     *
     * @var array<int, string>
     */
    private const array SENSITIVE_KEYS = [
        'password',
        'passwordconfirmation',
        'currentpassword',
        'newpassword',
        'secret',
        'token',
        'accesstoken',
        'refreshtoken',
        'apikey',
        'apitoken',
        'authorization',
        'authtoken',
        'bearer',
        'clientsecret',
        'privatekey',
        'publickey',
        'passportprivatekey',
        'passportpublickey',
        'appkey',
        'credentials',
        'dbpassword',
        'redispassword',
        'mailpassword',
        'mailgunsecret',
        'awssecretaccesskey',
        'awsaccesskeyid',
        'signature',
        'cookie',
        'setcookie',
        'sessionid',
    ];

    /**
     * Value patterns redacted wherever they appear in a message or a string
     * value, including inside a URL or an exception message where the secret is
     * not sitting under a helpfully named key.
     *
     * Each replacement keeps the surrounding structure so the log still shows
     * WHICH credential was present. `Bearer [redacted]` is a usable debugging
     * signal; a blank space is not.
     *
     * @var array<string, string>
     */
    private const array SENSITIVE_PATTERNS = [
        // Authorization headers: "Bearer eyJ...", "Basic dXNlcjpwYXNz".
        '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9\-._~+\/=]{8,}/i' => '$1 ' . self::PLACEHOLDER,
        // Credentials embedded in a URL or DSN: scheme://user:secret@host.
        '/([a-z][a-z0-9+.\-]*:\/\/[^\/\s:@]+):[^@\s\/]+@/i' => '$1:' . self::PLACEHOLDER . '@',
        // Query-string secrets, which is how a signed URL leaks.
        '/([?&](?:signature|sig|token|access_token|key|api_key|x-amz-signature|x-goog-signature)=)[^&\s"\']+/i' => '$1' . self::PLACEHOLDER,
        // PEM private key blocks (Passport keys, service-account keys).
        '/-----BEGIN[A-Z ]*PRIVATE KEY-----.*?-----END[A-Z ]*PRIVATE KEY-----/s' => self::PLACEHOLDER,
    ];

    /**
     * Customize the given logger instance.
     *
     * @param Logger $logger
     *
     * @return void
     */
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $this->redact($record));
    }

    /**
     * Redact a single log record.
     *
     * @param LogRecord $record
     *
     * @return LogRecord
     */
    private function redact(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra)
        );
    }

    /**
     * Redact an array of context/extra values, recursing into nested arrays.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function scrubArray(array $values): array
    {
        $scrubbed = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $scrubbed[$key] = self::PLACEHOLDER;
                continue;
            }

            $scrubbed[$key] = match (true) {
                is_array($value) => $this->scrubArray($value),
                is_string($value) => $this->scrubString($value),
                // A Throwable in context is THE most likely carrier of a secret,
                // not an edge case: Laravel's exception handler always logs
                // `['exception' => $e]`, and a QueryException's message has the
                // bound parameters interpolated into the SQL. Processors run
                // before handlers, so the object is still live here — if it is
                // passed through, the formatter stringifies it later and prints
                // the message verbatim. The result was a log line carrying the
                // same text twice: the scrubbed string copy, and the unscrubbed
                // object copy right beside it.
                $value instanceof Throwable => $this->scrubThrowable($value),
                is_object($value) => $this->scrubString($this->stringifyObject($value)),
                default => $value,
            };
        }

        return $scrubbed;
    }

    /**
     * Redact secret-shaped substrings from a single string.
     *
     * @param string $value
     *
     * @return string
     */
    private function scrubString(string $value): string
    {
        // A very long attacker-influenced string can exhaust pcre.backtrack_limit,
        // and preg_replace then returns NULL. Casting that to string blanked the
        // whole record — so the redactor would DESTROY the log line recording the
        // request that triggered it, which is a nastier outcome than the leak it
        // guards against. On failure keep the previous value and mark it.
        foreach (self::SENSITIVE_PATTERNS as $pattern => $replacement) {
            $replaced = preg_replace($pattern, $replacement, $value);

            if ($replaced === null) {
                return self::PLACEHOLDER . ' (redaction failed)';
            }

            $value = $replaced;
        }

        return $value;
    }

    /**
     * Reduce a Throwable to a scrubbed array, preserving the parts that make a
     * log entry useful and scrubbing the one part that carries secrets.
     *
     * @param Throwable $throwable
     * @param int $depth - guards a pathological getPrevious() chain
     *
     * @return array<string, mixed>
     */
    private function scrubThrowable(Throwable $throwable, int $depth = 0): array
    {
        $scrubbed = [
            'class' => $throwable::class,
            'message' => $this->scrubString($throwable->getMessage()),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ];

        $previous = $throwable->getPrevious();

        if ($previous instanceof Throwable && $depth < 5) {
            $scrubbed['previous'] = $this->scrubThrowable($previous, $depth + 1);
        }

        return $scrubbed;
    }

    /**
     * Best-effort string form of a non-Throwable object, so it can be scrubbed
     * rather than passed through.
     *
     * @param object $value
     *
     * @return string
     */
    private function stringifyObject(object $value): string
    {
        if ($value instanceof \Stringable || method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value::class;
    }

    /**
     * Determine whether a context key names a secret.
     *
     * @param string $key
     *
     * @return bool
     */
    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        if ($normalized === '') {
            return false;
        }

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

}
