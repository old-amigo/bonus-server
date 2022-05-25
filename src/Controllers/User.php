<?php

namespace Rarus\Interns\BonusServer\Controllers;

use Doctrine\DBAL;
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

    public static function bx24Users(): array
    {
        $queryUrl = $_ENV['BITRIX24_WEBHOOK'] . 'crm.contact.list';

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_SSL_VERIFYPEER => 0,
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
}