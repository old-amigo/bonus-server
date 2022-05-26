<?php

namespace Rarus\Interns\BonusServer\Model;

class Configuration
{
    /**
     * @param int $levelsToRoot
     * @return array<string,string>
     */
    public static function getDBConfig(int $levelsToRoot): array
    {
        /** @var array<string> $connectionParams */
        $connectionParams = [
            'path' => dirname(__DIR__, $levelsToRoot) . '/db/bs_db.sqlite3',
            'driver' => 'pdo_sqlite'
        ];
        return $connectionParams;
    }
}