<?php

return [
    'enabled' => env('LIBRARY_ENABLED', true),
    'root' => env('LIBRARY_STORAGE_ROOT', storage_path('app/private/library')),
    'eligible_statuses' => ['active', 'associate'],
    'requests_per_minute' => 120,
    'login_attempts_per_minute' => 10,
    'max_pdf_bytes' => 64 * 1024 * 1024,
    'python_binary' => env('LIBRARY_PYTHON_BINARY', 'python'),
];
