<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

use RuangBaca\Api\LibraryApi;
use RuangBaca\Exceptions\ApiException;

$statusCode = 200;
try {
    $api = new LibraryApi();
    $result = $api->handle(
        (string) ($_GET['action'] ?? ''),
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    );
} catch (ApiException $exception) {
    $statusCode = $exception->statusCode;
    $result = ['error' => $exception->getMessage()];
} catch (Throwable $exception) {
    $statusCode = 500;
    $result = ['error' => 'Terjadi gangguan pada server. Coba muat ulang halaman.'];
    error_log($exception->__toString());
}

http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
