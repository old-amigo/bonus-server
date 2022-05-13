<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

class DealBuilder
{
    /**
     * @param int    $categoryId
     * @param int    $b24ContactId
     * @param string $b24StageId
     *
     * @return array
     */
    public function build(int $categoryId, int $b24ContactId, string $b24StageId): array
    {
        return [
            'TITLE'       => sprintf('test-order-%s', time()),
            'CONTACT_ID'  => $b24ContactId,
            'COMMENTS'    => sprintf('тестовый заказ для контакта %s', $b24ContactId),
            'CATEGORY_ID' => $categoryId,
            'STAGE_ID'    => $b24StageId,
        ];
    }
}