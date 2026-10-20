<?php
/**
 * Complete PHP Syntax Checker
 */
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$errors = 0;
$total = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        // Skip .git or vendor if any
        if (strpos($path, '.git') !== false) continue;

        $total++;
        $cmd = '"C:\\xampp\\php\\php.exe" -l ' . escapeshellarg($path);
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);

        if ($return_var !== 0) {
            echo "[SYNTAX ERROR] {$path}\n";
            echo implode("\n", $output) . "\n\n";
            $errors++;
        }
    }
}

echo "Linted {$total} PHP files: {$errors} errors detected.\n";
if ($errors > 0) exit(1);
