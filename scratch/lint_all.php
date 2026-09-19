<?php
/**
 * scratch/lint_all.php
 * Syntax check across all PHP files in the project.
 */

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$excludedDirs = ['vendor', 'backups', 'exam-main', 'node_modules'];

$filesToLint = [];
foreach ($phpFiles as $file) {
    $filePath = $file[0];
    $skip = false;
    foreach ($excludedDirs as $dir) {
        if (strpos($filePath, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR) !== false) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $filesToLint[] = $filePath;
    }
}

sort($filesToLint);
echo "Linting " . count($filesToLint) . " PHP files...\n";

$errors = [];
$passed = 0;

foreach ($filesToLint as $file) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        $errors[] = [
            'file' => $file,
            'output' => implode("\n", $output)
        ];
    } else {
        $passed++;
    }
}

echo "Lint results: $passed passed, " . count($errors) . " failed.\n";

if (!empty($errors)) {
    echo "\nErrors found:\n";
    foreach ($errors as $err) {
        echo "=== {$err['file']} ===\n{$err['output']}\n\n";
    }
    exit(1);
}

echo "All PHP files passed syntax check!\n";
exit(0);
