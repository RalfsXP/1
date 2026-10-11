<?php

/*
 * Site health page (GET /api/admin/health). Only users whose email is listed
 * in ADMIN_EMAILS can see it; everyone else gets a 404.
 */
return [

    // Comma-separated, case-insensitive: ADMIN_EMAILS=ralfs@example.com,other@example.com
    'admin_emails' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    // Supabase free plan: 500 MB database. Raise it if the project is upgraded.
    'db_limit_mb' => (int) env('DB_SIZE_LIMIT_MB', 500),

    // Public Atlassian Statuspage feeds of the hosting platforms.
    'platforms' => [
        'supabase' => 'https://status.supabase.com/api/v2/status.json',
        'vercel' => 'https://www.vercel-status.com/api/v2/status.json',
    ],

];
