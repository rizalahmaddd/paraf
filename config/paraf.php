<?php

return [
    'disk' => env('PARAF_DISK', 'local'),

    'max_upload_kb' => (int) env('PARAF_MAX_UPLOAD_KB', 25600),

    'expiry_days' => [
        'default' => 14,
        'min' => 1,
        'max' => 90,
    ],

    // Files are sealed with libsodium using a key derived from APP_KEY, so rotating APP_KEY
    // makes stored documents unreadable. Turn off only when the disk encrypts server-side.
    'encrypt_files' => (bool) env('PARAF_ENCRYPT_FILES', true),

    'node_binary' => env('PARAF_NODE_BINARY', 'node'),

    'download_link_minutes' => 15,

    'passcode' => [
        'max_attempts' => 5,
        'lockout_seconds' => 15 * 60,
    ],

    'reminder_days_before_expiry' => [3, 1],

    'draft_retention_days' => 30,

    'signer_colors' => ['#2563EB', '#16A34A', '#9333EA', '#EA580C', '#0D9488', '#CA8A04', '#DB2777', '#4F46E5'],

    'owner_color' => '#0891B2',
];
