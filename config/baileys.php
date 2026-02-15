<?php

return [
    'url' => env('BAILEYS_GATEWAY_URL', 'http://127.0.0.1:3001'),
    'token' => env('BAILEYS_GATEWAY_TOKEN'),
    'timeout' => (int) env('BAILEYS_GATEWAY_TIMEOUT', 10),
];

