<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['csrf' => csrf()], JSON_UNESCAPED_UNICODE);
