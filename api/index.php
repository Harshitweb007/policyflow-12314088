<?php

// Forward Vercel requests through to Laravel's front controller.
// In Vercel's read-only serverless environment, storage and compiled view
// directories must be pre-created in /tmp if not already present.

$tmpDirs = [
    '/tmp/views',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
];

foreach ($tmpDirs as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

require __DIR__.'/../public/index.php';
