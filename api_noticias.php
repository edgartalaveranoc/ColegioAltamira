<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode(db()->query('SELECT titulo,resumen,categoria,publicado FROM noticias ORDER BY publicado DESC LIMIT 3')->fetchAll(), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo '[]'; }
