<?php

require __DIR__ . '/../apps/api/vendor/autoload.php';

$app = require_once __DIR__ . '/../apps/api/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Test 1: Root endpoint
$request1 = \Illuminate\Http\Request::create('/', 'GET');
$response1 = $kernel->handle($request1);
echo "=== Response from / ===" . PHP_EOL;
echo "Status: " . $response1->getStatusCode() . PHP_EOL;
echo "Body: " . $response1->getContent() . PHP_EOL . PHP_EOL;

// Test 2: Health endpoint
$request2 = \Illuminate\Http\Request::create('/api/health', 'GET');
$response2 = $kernel->handle($request2);
echo "=== Response from /api/health ===" . PHP_EOL;
echo "Status: " . $response2->getStatusCode() . PHP_EOL;
echo "Body: " . $response2->getContent() . PHP_EOL;
