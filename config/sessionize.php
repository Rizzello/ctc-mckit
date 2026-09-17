<?php

return [
    'endpoint_url' => env('SESSIONIZE_ENDPOINT_URL'),
    'timeout' => (int) env('SESSIONIZE_TIMEOUT', 10),
    'retry' => (int) env('SESSIONIZE_RETRY', 2),
];
