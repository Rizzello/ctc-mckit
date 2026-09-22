<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpaController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse|RedirectResponse
    {
        $entryPoint = public_path('index.html');

        if (is_file($entryPoint)) {
            return response()->file($entryPoint, [
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $path = trim($request->path(), '/');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontendUrl.($path === '' ? '/' : '/'.$path);

        if ($request->getQueryString() !== null) {
            $url .= '?'.$request->getQueryString();
        }

        return redirect()->away($url);
    }
}
