<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sidebar order now lives in config/acl.php ('order' per parent menu) and is applied
     * by AclSeeder on every deploy. The previous version updated fixed menu IDs, which
     * may differ between databases, so it is intentionally a no-op.
     */
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
