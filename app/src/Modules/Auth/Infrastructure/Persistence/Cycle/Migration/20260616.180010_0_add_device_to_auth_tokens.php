<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class AddDeviceToAuthTokens extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('auth_tokens')
            ->addColumn('ip', 'string', ['length' => 45, 'nullable' => true])
            ->addColumn('user_agent', 'text', ['nullable' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('auth_tokens')
            ->dropColumn('ip')
            ->dropColumn('user_agent')
            ->update();
    }
}
