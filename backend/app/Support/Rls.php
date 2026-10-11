<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes every table in `public` through its REST API (anon key = public).
 * Laravel connects as the owning `postgres` role, which bypasses RLS, so enabling
 * RLS with no policies shuts that API out without touching the app.
 *
 * Every migration that creates a table must call Rls::enable('table') after it.
 */
class Rls
{
    /** API roles Supabase grants access to by default. */
    public const API_ROLES = ['anon', 'authenticated'];

    public static function enable(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf('alter table %s enable row level security', self::quote($table)));
        foreach (self::existingApiRoles() as $role) {
            DB::statement(sprintf('revoke all on table %s from %s', self::quote($table), $role));
        }
    }

    /** The Supabase API roles present on this database (none on plain Postgres). */
    public static function existingApiRoles(): array
    {
        return array_column(
            DB::select('select rolname from pg_roles where rolname in (?, ?)', self::API_ROLES),
            'rolname',
        );
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
