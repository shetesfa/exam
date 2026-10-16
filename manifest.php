<?php
/**
 * manifest.php - Dynamic PWA manifest fallback
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');

if (file_exists(__DIR__ . '/manifest.json')) {
    readfile(__DIR__ . '/manifest.json');
} else {
    echo json_encode(["name" => "አጸደ ትጉሃን ሰንበት ትምህርት ቤት", "start_url" => "index.php"], JSON_UNESCAPED_UNICODE);
}
