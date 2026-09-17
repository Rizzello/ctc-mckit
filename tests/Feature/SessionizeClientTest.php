<?php

namespace Tests\Feature;

use App\Sessionize\SessionizeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SessionizeClientTest extends TestCase
{
    public function test_fetches_all_and_the_derived_grid_smart_endpoint(): void
    {
        config([
            'sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All',
            'sessionize.timeout' => 7,
            'sessionize.retry' => 0,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response($this->fixture('all.json')),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response($this->fixture('grid-smart.json')),
        ]);

        $payload = app(SessionizeClient::class)->fetch();

        $this->assertArrayHasKey('sessions', $payload['all']);
        $this->assertCount(1, $payload['grid']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sessionize.com/api/v2/test-event/view/All');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sessionize.com/api/v2/test-event/view/GridSmart');
    }

    public function test_rejects_invalid_or_malformed_remote_responses(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All']);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response('{invalid-json'),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response($this->fixture('grid-smart.json')),
        ]);

        $this->expectException(RuntimeException::class);

        app(SessionizeClient::class)->fetch();
    }

    public function test_aborts_when_either_remote_endpoint_fails(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response($this->fixture('all.json')),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response([], 500),
        ]);

        $this->expectException(RequestException::class);

        app(SessionizeClient::class)->fetch();
    }

    public function test_retries_a_failed_get_request_using_the_configured_policy(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 1]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::sequence()
                ->pushStatus(500)
                ->push($this->fixture('all.json')),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response($this->fixture('grid-smart.json')),
        ]);

        app(SessionizeClient::class)->fetch();

        Http::assertSentCount(3);
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path("tests/Fixtures/sessionize/{$name}"));

        $this->assertIsString($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
