<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->enableExtensions();
        $this->convertJsonToJsonb();
        $this->optimizeTables();
        $this->createIndexes();
    }

    private function enableExtensions(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "citext"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "unaccent"');
    }

    /**
     * Ensure all metadata columns are JSONB (GIN-safe)
     */
    private function convertJsonToJsonb(): void
    {
        $tables = [
            'customers',
            'payments',
            'vouchers',
            'sms_logs',
            'system_logs',
        ];

        foreach ($tables as $table) {
            // Skip if table does not exist
            DB::statement("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_name = '{$table}'
                        AND column_name = 'metadata'
                        AND data_type = 'json'
                    ) THEN
                        ALTER TABLE {$table}
                        ALTER COLUMN metadata TYPE jsonb
                        USING metadata::jsonb;
                    END IF;
                END
                \$\$;
            ");
        }
    }

    private function optimizeTables(): void
    {
        $tables = [
            'users',
            'customers',
            'payments',
            'vouchers',
            'sms_logs',
            'system_logs',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} SET (fillfactor = 90)");
            DB::statement("ANALYZE {$table}");
        }
    }

    /**
     * PostgreSQL-safe indexes (NO invalid GIN on json)
     */
    private function createIndexes(): void
    {
        // JSONB expression indexes (FAST & SAFE)
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_payments_metadata_package
            ON payments ((metadata->>'package'))
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_vouchers_metadata_package
            ON vouchers ((metadata->>'package'))
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_customers_metadata_registration_source
            ON customers ((metadata->>'registration_source'))
        ");

        // Full-text search indexes
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_sms_logs_content_fts
            ON sms_logs USING gin (to_tsvector('english', content))
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_system_logs_message_fts
            ON system_logs USING gin (to_tsvector('english', message))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_payments_metadata_package');
        DB::statement('DROP INDEX IF EXISTS idx_vouchers_metadata_package');
        DB::statement('DROP INDEX IF EXISTS idx_customers_metadata_registration_source');
        DB::statement('DROP INDEX IF EXISTS idx_sms_logs_content_fts');
        DB::statement('DROP INDEX IF EXISTS idx_system_logs_message_fts');

        DB::statement('DROP EXTENSION IF EXISTS "unaccent"');
        DB::statement('DROP EXTENSION IF EXISTS "citext"');
        DB::statement('DROP EXTENSION IF EXISTS "pg_trgm"');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
    }
};
