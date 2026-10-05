<?php

declare(strict_types=1);

namespace App\Infrastructure\Erp\Odoo;

use App\Domain\Erp\ErpUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The JSON-RPC transport to an Odoo server.
 *
 * JSON-RPC rather than the XML-RPC the documentation leads with: PHP dropped
 * ext-xmlrpc in 8.0, and Odoo exposes exactly the same calls over JSON at
 * /jsonrpc. Nothing is lost - `execute_kw` is `execute_kw` either way.
 *
 * Odoo answers HTTP 200 to almost everything, including its own tracebacks:
 * a failed call is a 200 whose body carries an `error` member. Code that only
 * checks the status code therefore reads a crash as a success, which is why
 * every call goes through `call()` below.
 */
final class OdooClient
{
    /**
     * The authenticated user id, which Odoo wants on every subsequent call.
     * Resolved once per instance - the gateway is built per job, so this is
     * one extra round trip per order, not per call.
     */
    private ?int $uid = null;

    public function __construct(
        private readonly string $url,
        private readonly string $database,
        private readonly string $username,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {}

    /**
     * Runs a method on an Odoo model, the way the admin interface would.
     *
     * @param  array<int, mixed>  $arguments
     * @param  array<string, mixed>  $options
     *
     * @throws ErpUnavailableException
     */
    public function execute(string $model, string $method, array $arguments, array $options = []): mixed
    {
        return $this->call('object', 'execute_kw', [
            $this->database,
            $this->uid(),
            $this->apiKey,
            $model,
            $method,
            $arguments,
            $options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws ErpUnavailableException
     */
    public function create(string $model, array $values): int
    {
        return (int) $this->execute($model, 'create', [$values]);
    }

    /**
     * The first record matching the domain, or null.
     *
     * @param  array<int, mixed>  $domain  Odoo's own filter syntax, e.g.
     *                                     `[['email', '=', 'a@b.c']]`
     *
     * @throws ErpUnavailableException
     */
    public function searchFirst(string $model, array $domain): ?int
    {
        /** @var array<int, int> $ids */
        $ids = (array) $this->execute($model, 'search', [$domain], ['limit' => 1]);

        return $ids === [] ? null : (int) reset($ids);
    }

    /**
     * @throws ErpUnavailableException
     */
    private function uid(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $uid = $this->call('common', 'authenticate', [
            $this->database,
            $this->username,
            $this->apiKey,
            [],
        ]);

        // Odoo does not raise on bad credentials: it returns `false`, which
        // would otherwise travel on as uid 0 and fail much later with
        // something unrelated to the real problem.
        if (! is_int($uid) || $uid <= 0) {
            throw ErpUnavailableException::transport(
                'odoo',
                sprintf(
                    'authentication refused for "%s" on database "%s" - check ODOO_USERNAME, ODOO_API_KEY and ODOO_DATABASE',
                    $this->username,
                    $this->database,
                ),
            );
        }

        return $this->uid = $uid;
    }

    /**
     * @param  array<int, mixed>  $args
     *
     * @throws ErpUnavailableException
     */
    private function call(string $service, string $method, array $args): mixed
    {
        $endpoint = rtrim($this->url, '/').'/jsonrpc';

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'service' => $service,
                        'method' => $method,
                        'args' => $args,
                    ],
                    // Odoo echoes it back; nothing here correlates on it.
                    'id' => 1,
                ]);
        } catch (ConnectionException $exception) {
            throw ErpUnavailableException::transport('odoo', $exception->getMessage(), $exception);
        }

        if ($response->failed()) {
            throw ErpUnavailableException::refused('odoo', "{$service}.{$method}", $response->status(), $response->body());
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();

        if (isset($body['error'])) {
            throw ErpUnavailableException::refused(
                'odoo',
                "{$service}.{$method}",
                $response->status(),
                $this->describe($body['error']),
            );
        }

        return $body['result'] ?? null;
    }

    /**
     * Odoo buries the useful sentence under `error.data.message`, with the
     * Python traceback next to it. The message is what a human needs; the
     * traceback is noise in a log line.
     */
    private function describe(mixed $error): string
    {
        if (! is_array($error)) {
            return (string) json_encode($error);
        }

        $data = is_array($error['data'] ?? null) ? $error['data'] : [];

        return (string) ($data['message'] ?? $error['message'] ?? json_encode($error));
    }
}
