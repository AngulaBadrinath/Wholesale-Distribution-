<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // 1. PostgreSQL Audit Log Immutability Trigger Function (Strict append-only enforcement)
            DB::unprepared("
                CREATE OR REPLACE FUNCTION protect_audit_log_immutability()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'Audit log records are strictly immutable and cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' THEN
                        RAISE EXCEPTION 'Audit log records are strictly immutable and cannot be updated.';
                    END IF;

                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS trg_protect_audit_logs ON audit_logs;
                CREATE TRIGGER trg_protect_audit_logs
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION protect_audit_log_immutability();
            ");

            // 2. PostgreSQL Security Log Immutability Trigger Function (Strict append-only enforcement)
            DB::unprepared("
                CREATE OR REPLACE FUNCTION protect_security_log_immutability()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'Security log records are strictly immutable and cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' THEN
                        RAISE EXCEPTION 'Security log records are strictly immutable and cannot be updated.';
                    END IF;

                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS trg_protect_security_logs ON security_logs;
                CREATE TRIGGER trg_protect_security_logs
                BEFORE UPDATE OR DELETE ON security_logs
                FOR EACH ROW
                EXECUTE FUNCTION protect_security_log_immutability();
            ");
        } elseif ($driver === 'sqlite') {
            // SQLite trigger compatibility for unit testing environments
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_protect_audit_logs_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Audit log records are strictly immutable and cannot be updated.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_protect_audit_logs_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Audit log records are strictly immutable and cannot be deleted.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_protect_security_logs_update
                BEFORE UPDATE ON security_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Security log records are strictly immutable and cannot be updated.');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_protect_security_logs_delete
                BEFORE DELETE ON security_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Security log records are strictly immutable and cannot be deleted.');
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_audit_logs ON audit_logs;');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_audit_log_immutability();');

            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_security_logs ON security_logs;');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_security_log_immutability();');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_audit_logs_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_audit_logs_delete;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_security_logs_update;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_security_logs_delete;');
        }
    }
};
