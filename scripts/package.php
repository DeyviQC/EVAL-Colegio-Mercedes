<?php

declare(strict_types=1);

// Portable source export: no environment secrets, private MySQL data or dependency trees.
$root = dirname(__DIR__);
$output = $root.'/dist';
if (!is_dir($output)) mkdir($output, 0700, true);
$zip = new ZipArchive();
$archive = $output.'/EVAL-laptop.zip';
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create the distribution ZIP.');
$prefix = 'EVAL/';
$snapshot = null;
try {
    foreach (['backend', 'frontend', 'openspec', 'app', 'config', 'database', 'public', 'resources', 'scripts', 'tests', 'docs'] as $folder) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            $name = $file->getFilename();
            if (str_starts_with($name, '.env') && $name !== '.env.example') continue;
            if (in_array($name, ['auth.json', '.npmrc', '.pypirc'], true)) continue;
            if (preg_match('~(?:^|/)(?:vendor|node_modules|\.git|test-results|playwright-report|artifacts|\.phpunit\.cache)(?:/|$)~', $relative)) continue;
            if (str_starts_with($relative, 'backend/storage/') || str_starts_with($relative, 'backend/bootstrap/cache/')) continue;
            if ($relative === 'backend/database/database.sqlite' || $relative === 'config/local.php' || str_ends_with($name, '.log') || str_ends_with($name, '.cache')) continue;
            $zip->addFile($file->getPathname(), $prefix.$relative);
        }
    }
    foreach (['README.md', '.gitignore', 'Abrir EVAL.cmd'] as $file) $zip->addFile($root.'/'.$file, $prefix.$file);
    foreach (['storage/uploads', 'storage/logs', 'backend/bootstrap/cache', 'backend/storage/app/private', 'backend/storage/app/tmp', 'backend/storage/app/public', 'backend/storage/framework/cache/data', 'backend/storage/framework/sessions', 'backend/storage/framework/views', 'backend/storage/logs'] as $directory) $zip->addEmptyDir($prefix.$directory);
    // The legacy prototype snapshot remains separate from the new MySQL foundation.
    if (is_file($root.'/storage/eval.sqlite')) {
        $snapshot = tempnam(sys_get_temp_dir(), 'eval-export-');
        $source = new SQLite3($root.'/storage/eval.sqlite', SQLITE3_OPEN_READONLY);
        $target = new SQLite3($snapshot);
        if (!$source->backup($target) || $target->querySingle('PRAGMA integrity_check') !== 'ok') throw new RuntimeException('Cannot verify the prototype snapshot.');
        $target->close();$source->close();
        $zip->addFile($snapshot, $prefix.'storage/eval.sqlite');
    }
    if (is_dir($root.'/storage/uploads')) foreach (new DirectoryIterator($root.'/storage/uploads') as $file) if ($file->isFile()) $zip->addFile($file->getPathname(), $prefix.'storage/uploads/'.$file->getFilename());
    if (!$zip->close()) throw new RuntimeException('Cannot finalize the distribution.');
    echo "Prepared distribution: $archive\n";
} finally {
    if ($snapshot && is_file($snapshot)) unlink($snapshot);
}

