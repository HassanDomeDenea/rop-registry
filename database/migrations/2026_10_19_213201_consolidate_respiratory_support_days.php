<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A single type of support keeps its days in `support_days`; only Oâ‚‚ + CPAP uses the
     * separate `o2_days` and `cpap_days`, which are left as they are.
     *
     * The imported records used either `support_days` or the per-type days, never both, so
     * a single type's days move to `support_days`; a value is only cleared once
     * `support_days` holds the same number.
     */
    public function up(): void
    {
        foreach (['o2' => 'o2_days', 'cpap' => 'cpap_days'] as $support => $column) {
            DB::table('patients')->where('respiratory_support', $support)->whereNull('support_days')
                ->update(['support_days' => DB::raw($column)]);

            DB::table('patients')->where('respiratory_support', $support)->whereColumn($column, 'support_days')
                ->update([$column => null]);
        }
    }

    /**
     * The days moved to `support_days` are not moved back.
     */
    public function down(): void
    {
        //
    }
};
