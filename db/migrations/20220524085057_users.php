<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Users extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('bx24_id', 'integer')
            ->addColumn('bonuses', 'decimal')
            ->create();
    }
}
