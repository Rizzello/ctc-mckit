<?php

namespace App\Sessionize;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SessionizeClient
{
    /**
     * @return array{all: array<string, mixed>, grid: list<array<string, mixed>>}
     */
    public function fetch(): array
    {
        $allUrl = config('sessionize.endpoint_url');

        if (! is_string($allUrl) || ! $this->isAllEndpoint($allUrl)) {
            throw new RuntimeException('Sessionize synchronization is not configured.');
        }

        $all = $this->getJson($allUrl);
        $grid = $this->getJson($this->gridSmartUrl($allUrl));

        if (array_is_list($all) || ! array_is_list($grid)) {
            throw new RuntimeException('Sessionize returned an invalid response.');
        }

        /** @var array<string, mixed> $all */
        /** @var list<array<string, mixed>> $grid */
        return compact('all', 'grid');
    }

    private function isAllEndpoint(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'], $parts['path'])
            && str_ends_with(rtrim($parts['path'], '/'), '/view/All');
    }

    private function gridSmartUrl(string $allUrl): string
    {
        return (string) preg_replace('#/view/All/?$#', '/view/GridSmart', $allUrl);
    }

    /** @return array<mixed> */
    private function getJson(string $url): array
    {
        $response = $this->request()->get($url)->throw();
        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Sessionize returned an invalid response.');
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        $timeout = max(1, (int) config('sessionize.timeout', 10));
        $attempts = max(1, (int) config('sessionize.retry', 2) + 1);

        return Http::acceptJson()
            ->withUserAgent('MC Kit schedule synchronizer')
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout)
            ->retry($attempts, 200);
    }
}
