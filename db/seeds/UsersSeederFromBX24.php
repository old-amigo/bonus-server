<?php


use Dotenv\Dotenv;
use Phinx\Seed\AbstractSeed;

class UsersSeederFromBX24 extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();
        $bx24users = \Rarus\Interns\BonusServer\Controllers\User::bx24Users();

        $data = [];

        foreach ($bx24users as $user) {
            $data[] = [
                'name' => $user->NAME . ' ' . $user->SECOND_NAME . ' ' . $user->LAST_NAME,
                'bx24_id' => $user->ID,
                'bonuses' => $_ENV['DEFAULT_BONUS_WELCOME_GIFT_AMOUNT']
            ];
            \Rarus\Interns\BonusServer\Controllers\BonusController::setBonuceBalance($user->ID, $_ENV['DEFAULT_BONUS_WELCOME_GIFT_AMOUNT']);
        }

        $this->table('users')
            ->insert($data)
            ->saveData();
    }
}
