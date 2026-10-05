<?php

declare(strict_types=1);

namespace App\Infrastructure\Erp\Dolibarr;

use App\Domain\Erp\ErpUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The REST transport to a Dolibarr instance.
 *
 * Authentication is a `DOLAPIKEY` header carrying a user's API key, so every
 * call acts as that user and inherits their permissions. A key belonging to a
 * user without rights on third parties or orders gets HTTP 403 on the first
 * write, which is the single most common way this integration fails to start.
 */
final class DolibarrClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {}

    /**
     * A list endpoint, filtered with Dolibarr's own `sqlfilters` syntax.
     *
     * Dolibarr answers 404 with an "not found" body when a filter matches
     * nothing, rather than an empty list, so that case is folded into `[]`
     * here instead of being treated as a failure.
     *
     * @param  array<string, string>  $query
     * @return array<int, array<string, mixed>>
     *
     * @throws ErpUnavailableException
     */
    public function list(string $path, array $query): array
    {
        try {
            $response = $this->request()->get($this->endpoint($path), $query);
        } catch (ConnectionException $exception) {
            throw ErpUnavailableException::transport('dolibarr', $exception->getMessage(), $exception);
        }

        if ($response->notFound()) {
            return [];
        }

        if ($response->failed()) {
            throw ErpUnavailableException::refused('dolibarr', "GET {$path}", $response->status(), $response->body());
        }

        $body = $response->json();

        // A single-object answer where a list was expected is still one row.
        return is_array($body) ? (array_is_list($body) ? $body : [$body]) : [];
    }

    /**
     * Creates a record and returns the id Dolibarr assigned it.
     *
     * Dolibarr returns a BARE integer for a successful POST - not an object,
     * not a Location header - so there is nothing to unwrap.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ErpUnavailableException
     */
    public function post(string $path, array $payload): int
    {
        try {
            $response = $this->request()->post($this->endpoint($path), $payload);
        } catch (ConnectionException $exception) {
            throw ErpUnavailableException::transport('dolibarr', $exception->getMessage(), $exception);
        }

        if ($response->failed()) {
            throw ErpUnavailableException::refused('dolibarr', "POST {$path}", $response->status(), $response->body());
        }

        return (int) $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['DOLAPIKEY' => $this->apiKey]);
    }

    /**
     * Accepts a base URL with or without the API path already on it: both
     * forms are in circulation depending on whether the instance sits behind
     * a rewrite, and getting it wrong is a 404 with no explanation.
     */
    private function endpoint(string $path): string
    {
        $base = rtrim($this->url, '/');

        if (! str_contains($base, '/api/index.php')) {
            $base .= '/api/index.php';
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Quotes a value for `sqlfilters`.
     *
     * Dolibarr parses that parameter itself and wraps values in single quotes,
     * so an apostrophe inside one ends the string early. Callers must still
     * check what comes back rather than trust the filter alone - see how the
     * gateway re-compares the email it searched for.
     */
    public static function quote(string $value): string
    {
        return str_replace("'", '', $value);
    }
}
