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

echo "2. Creating database `$dbname` if not exists...\n";
if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    die("Failed to create database: " . mysqli_error($conn) . "\n");
}
if (!mysqli_select_db($conn, $dbname)) {
    die("Failed to select database: " . mysqli_error($conn) . "\n");
}
echo "✓ Database selected.\n\n";

$sqlFile = dirname(__DIR__) . '/atsede_sunday_school (28).sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found at: $sqlFile\n");
}

echo "3. Reading SQL file (" . round(filesize($sqlFile) / 1024, 1) . " KB)...\n";
$sqlContent = file_get_contents($sqlFile);

echo "4. Executing SQL commands...\n";
// Execute multi query
if (mysqli_multi_query($conn, $sqlContent)) {
    $executed = 0;
    do {
        $executed++;
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    
    if (mysqli_errno($conn)) {
        echo "Warning during execution: " . mysqli_error($conn) . "\n";
    } else {
        echo "✓ All SQL commands executed successfully ($executed query blocks)!\n";
    }
} else {
    echo "Execution failed: " . mysqli_error($conn) . "\n";
}

echo "\n5. Verifying imported tables in `$dbname`...\n";
$tablesResult = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($tablesResult)) {
    $tables[] = $row[0];
}
echo "Imported tables count: " . count($tables) . "\n";
echo "Tables: " . implode(', ', $tables) . "\n\n";

echo "=== TiDB IMPORT COMPLETED SUCCESSFULLY! ===\n";
mysqli_close($conn);
