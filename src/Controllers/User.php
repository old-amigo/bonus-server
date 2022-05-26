<?php

namespace Rarus\Interns\BonusServer\Controllers;

use Doctrine\DBAL;
use Money\Money;
use Monolog\Logger;


class User
{
    public static function getUsers(): void
    {
        $connectionParams = [
            'path' => dirname(__DIR__, 2) . '/db/bs_db.sqlite3',
            'driver' => 'pdo_sqlite'
        ];

        try {
            $conf = new DBAL\Configuration();
            $conn = DBAL\DriverManager::getConnection($connectionParams, $conf);
            $data = $conn->fetchAllAssociative('SELECT * FROM users');

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            //todo log this
        }
    }

    /**
     * @param int $BX24_id
     * @return array<int, array<string, mixed>>
     */
    public static function getUserByBX24id(int $BX24_id): array
    {
        $connectionParams = [
            'path' => dirname(__DIR__, 2) . '/db/bs_db.sqlite3',
            'driver' => 'pdo_sqlite'
        ];

        try {
            $conf = new DBAL\Configuration();
            $conn = DBAL\DriverManager::getConnection($connectionParams, $conf);
            return $conn->fetchAllAssociative("SELECT * FROM users where bx24_id=$BX24_id");
        } catch (\Throwable $e) {
            //todo log this
            return [];
        }
    }

    /**
     * @return array<object>
     */
    public static function bx24Users(): array
    {
        $queryUrl = $_ENV['BITRIX24_WEBHOOK'] . 'crm.contact.list';

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl
            ]);

            $result = curl_exec($curl);
            $assoc = json_decode($result, false, 512, JSON_THROW_ON_ERROR);

        }
        catch (\Throwable $exception) {
            //todo log this
            return [];
        }
        return $assoc->result;
    }

    public static function newUser(int $userBX24id): void
    {
        $users = self::bx24Users();
        foreach ($users as $user) {
            if ((int)$user->ID === $userBX24id) {
                self::insertNewUser(
                    ($user->NAME . ' ' . $user->SECOND_NAME . ' ' . $user->LAST_NAME),
                    $userBX24id,
                    $_ENV['DEFAULT_BONUS_WELCOME_GIFT_AMOUNT']
                );
            }
        }
    }

    private static function insertNewUser(string $name, int $bx24id, int $bonuses): void
    {
        $connectionParams = [
            'path' => dirname(__DIR__, 2) . '/db/bs_db.sqlite3',
            'driver' => 'pdo_sqlite'
        ];

        try {
            $conf = new DBAL\Configuration();
            $conn = DBAL\DriverManager::getConnection($connectionParams, $conf);
            $conn->insert('users', [
               'name' => $name,
               'bx24_id' => $bx24id,
                'bonuses' => $bonuses
            ]);
        } catch (\Throwable $e) {
            //todo log this
        }
    }
}