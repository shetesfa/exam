<?php
/**
 * api/ping.php
 * Lightweight connectivity check for offline SyncManager.
 */
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'time' => date('c')]);
