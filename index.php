<?php

declare(strict_types=1);

use Modolus\Core\Kernel;

require __DIR__ . '/modolus/bootstrap.php';

$host = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0];
$path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$kernel = new Kernel(modolus_modules(__DIR__));
$response = $kernel->handle([
    'host' => $host,
    'path' => $path,
    'method' => $method,
]);

http_response_code((int) $response['status']);
header('Content-Type: text/html; charset=utf-8');
echo $response['body'];
