<?php

namespace Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_https_forwarded_by_a_trusted_proxy_generates_secure_urls(): void
    {
        $response = $this->schemeResponse([
            'HTTP_HOST' => 'conference.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'conference.test',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '172.20.0.2',
            'SERVER_PORT' => '80',
        ]);

        $response->assertOk()->assertExactJson([
            'secure' => true,
            'url' => 'https://conference.test/example',
            'route' => 'https://conference.test/login',
        ]);
    }

    public function test_direct_http_requests_remain_http_without_forwarded_https_metadata(): void
    {
        $response = $this->schemeResponse([
            'HTTP_HOST' => 'localhost:8080',
            'HTTPS' => 'off',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PORT' => '8080',
        ]);

        $response->assertOk()->assertJsonPath('secure', false);

        self::assertStringStartsWith('http://', (string) $response->json('url'));
        self::assertStringStartsWith('http://', (string) $response->json('route'));
    }

    /**
     * @param  array<string, string>  $server
     */
    private function schemeResponse(array $server): TestResponse
    {
        Route::get('/_test/scheme', static function (Request $request): JsonResponse {
            return response()->json([
                'secure' => $request->isSecure(),
                'url' => url('/example'),
                'route' => route('login'),
            ]);
        });

        return $this->withServerVariables($server)->get('/_test/scheme');
    }
}
