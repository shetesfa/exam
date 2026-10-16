<?php
/**
 * scratch/import_to_tidb.php
 * Automated SQL Importer to TiDB Cloud Serverless
 * Usage: php scratch/import_to_tidb.php <host> <user> <pass> [port] [dbname]
 */

if ($argc < 4) {
    echo "Usage: php scratch/import_to_tidb.php <host> <user> <password> [port=4000] [dbname=atsede_sunday_school]\n";
    exit(1);
}

$host = $argv[1];
$user = $argv[2];
$pass = $argv[3];
$port = isset($argv[4]) ? intval($argv[4]) : 4000;
$dbname = isset($argv[5]) ? $argv[5] : 'atsede_sunday_school';

echo "=== TiDB Database Uploader ===\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "User: $user\n";
echo "Database: $dbname\n\n";

echo "1. Connecting to TiDB via SSL...\n";
$conn = mysqli_init();
if (!$conn) {
    die("Error: mysqli_init failed\n");
}

// Connect with SSL
$connected = @mysqli_real_connect($conn, $host, $user, $pass, null, $port, null, MYSQLI_CLIENT_SSL);
if (!$connected) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}
echo "✓ Successfully connected to TiDB!\n\n";

echo "2. Re-creating database `$dbname`...\n";
mysqli_query($conn, "DROP DATABASE IF EXISTS `$dbname`");
if (!mysqli_query($conn, "CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Failed to create database: " . mysqli_error($conn) . "\n");
}
if (!mysqli_select_db($conn, $dbname)) {
    die("Failed to select database: " . mysqli_error($conn) . "\n");
}
echo "✓ Clean database selected.\n\n";

$sqlFile = dirname(__DIR__) . '/scratch/tidb_dump_utf8.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = dirname(__DIR__) . '/scratch/tidb_dump.sql';
}
if (!file_exists($sqlFile)) {
    die("SQL file not found at: $sqlFile\n");
}

echo "3. Reading SQL file (" . round(filesize($sqlFile) / 1024, 1) . " KB)...\n";
$sqlContent = file_get_contents($sqlFile);

echo "4. Executing SQL commands...\n";
// Split into queries safely
$lines = explode("\n", $sqlContent);
$query = '';
$executedCount = 0;
$errorCount = 0;

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
        continue;
    }
    $query .= $line . "\n";
    if (str_ends_with($trimmed, ';')) {
        if (mysqli_query($conn, $query)) {
            $executedCount++;
        } else {
            $err = mysqli_error($conn);
            // Ignore minor notices
            if (!str_contains($err, 'already exists')) {
                echo "Warning on query: " . substr(trim($query), 0, 80) . "... -> $err\n";
                $errorCount++;
            }
        }
        $query = '';
    }
}

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

echo "✓ Finished executing queries ($executedCount successful, $errorCount warnings)!\n\n";

echo "5. Verifying imported tables in `$dbname`...\n";
$tablesResult = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($tablesResult)) {
    $tables[] = $row[0];
}
echo "Imported tables count: " . count($tables) . "\n";
echo "Tables list:\n";
foreach ($tables as $t) {
    $cntRes = mysqli_query($conn, "SELECT COUNT(*) FROM `$t`");
    $cntRow = $cntRes ? mysqli_fetch_row($cntRes) : [0];
    echo "  - $t: " . $cntRow[0] . " rows\n";
}

echo "\n=== TiDB IMPORT COMPLETED SUCCESSFULLY! ===\n";
mysqli_close($conn);
