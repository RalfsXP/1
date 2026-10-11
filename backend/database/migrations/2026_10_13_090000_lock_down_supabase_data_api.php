<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Supabase security lints ("RLS Disabled in Public", "Sensitive Columns Exposed"):
 * every table in `public` was readable and writable through Supabase's REST API with
 * the public anon key. mērķis never uses that API — Laravel connects directly as the
 * table owner (postgres, BYPASSRLS) — so: RLS on everywhere with no policies, and no
 * privileges for the API roles, now or on tables created later. Postgres only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (DB::select("select tablename from pg_tables where schemaname = 'public'") as $table) {
            Rls::enable($table->tablename);
        }

        foreach (Rls::existingApiRoles() as $role) {
            DB::statement("revoke all on all tables in schema public from {$role}");
            DB::statement("revoke all on all sequences in schema public from {$role}");
            DB::statement("revoke all on all functions in schema public from {$role}");
            // Objects the migrating role (postgres) creates later start out private too.
            DB::statement("alter default privileges in schema public revoke all on tables from {$role}");
            DB::statement("alter default privileges in schema public revoke all on sequences from {$role}");
            DB::statement("alter default privileges in schema public revoke all on functions from {$role}");
        }
    }

    /**
     * Security hardening isn't rolled back: the down migration leaves the lock-down in place.
     */
    public function down(): void
    {
        //
    }
};
