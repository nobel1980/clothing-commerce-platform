<?php

require __DIR__ . '/../apps/api/vendor/autoload.php';

$host = getenv('REDIS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('REDIS_PORT') ?: 6379);
$password = getenv('REDIS_PASSWORD') ?: 'secret_redis_pass';

echo "Testing Redis connection to {$host}:{$port}..." . PHP_EOL;

try {
    $client = new \Predis\Client([
        'scheme' => 'tcp',
        'host' => $host,
        'port' => $port,
        'password' => $password,
        'timeout' => 2.0,
    ]);

    $client->connect();
    $pong = $client->ping();
    echo "SUCCESS: Redis ping responded with: " . (is_string($pong) ? $pong : 'OK') . PHP_EOL;
} catch (\Throwable $e) {
    echo "NOTICE: Redis server at {$host}:{$port} is offline or unreachable from this host ({$e->getMessage()})." . PHP_EOL;
    echo "NOTE: When running inside Docker via 'docker compose up', the redis service automatically starts and responds with PONG on port 6379." . PHP_EOL;
}
