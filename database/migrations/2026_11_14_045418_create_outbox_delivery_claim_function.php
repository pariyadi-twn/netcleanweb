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
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.claim_outbox_deliveries(p_limit integer, p_lease_seconds integer)
            RETURNS TABLE (delivery_id bigint, tenant_id bigint)
            LANGUAGE sql
            VOLATILE
            SECURITY DEFINER
            SET search_path = pg_catalog, pg_temp
            AS $function$
                WITH candidates AS MATERIALIZED (
                    SELECT delivery.id
                    FROM public.event_deliveries AS delivery
                    WHERE delivery.next_attempt_at <= statement_timestamp()
                      AND (delivery.lease_until IS NULL OR delivery.lease_until < statement_timestamp())
                      AND NOT EXISTS (
                          SELECT 1
                          FROM public.consumer_receipts AS receipt
                          WHERE receipt.tenant_id = delivery.tenant_id
                            AND receipt.event_id = delivery.event_id
                            AND receipt.consumer_name = delivery.consumer_name
                      )
                    ORDER BY delivery.id
                    FOR UPDATE OF delivery SKIP LOCKED
                    LIMIT LEAST(GREATEST(COALESCE(p_limit, 0), 0), 1000)
                ), claimed AS (
                    UPDATE public.event_deliveries AS delivery
                    SET attempts = delivery.attempts + 1,
                        lease_until = statement_timestamp() + make_interval(secs => LEAST(GREATEST(COALESCE(p_lease_seconds, 1), 1), 3600)),
                        last_dispatched_at = statement_timestamp(),
                        next_attempt_at = statement_timestamp() + make_interval(secs => LEAST(GREATEST(COALESCE(p_lease_seconds, 1), 1), 3600)),
                        updated_at = statement_timestamp()
                    FROM candidates
                    WHERE delivery.id = candidates.id
                    RETURNING delivery.id AS delivery_id, delivery.tenant_id AS tenant_id
                )
                SELECT claimed.delivery_id, claimed.tenant_id
                FROM claimed
                ORDER BY claimed.delivery_id
            $function$;

            REVOKE ALL ON FUNCTION public.claim_outbox_deliveries(integer, integer) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION public.claim_outbox_deliveries(integer, integer) TO netclean_runtime;
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.claim_outbox_deliveries(integer, integer)');
    }
};
