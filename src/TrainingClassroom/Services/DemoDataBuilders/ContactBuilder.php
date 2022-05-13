<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

class ContactBuilder
{
    /**
     * @param int $count
     *
     * @return array
     */
    public function build(int $count = 1): array
    {
        $contacts = [];
        for ($i = 0; $i < $count; $i++) {
            $contacts[] = [
                'NAME'        => sprintf('имя-%s', $i),
                'LAST_NAME'   => 'фамилия',
                'SECOND_NAME' => 'отчество',
            ];
        }

        return $contacts;
    }
}