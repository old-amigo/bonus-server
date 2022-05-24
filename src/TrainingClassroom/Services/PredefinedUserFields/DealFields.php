<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields;

use Bitrix24\SDK\Services\CRM\Deal\Service\Deal;
use Bitrix24\SDK\Services\CRM\Deal\Service\DealUserfield;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;

class DealFields
{
    private DealUserfield $dealUserfield;
    private Deal $dealService;
    private LoggerInterface $log;

    /**
     * @param \Bitrix24\SDK\Services\CRM\Deal\Service\DealUserfield $dealUserfield
     * @param \Bitrix24\SDK\Services\CRM\Deal\Service\Deal          $dealService
     * @param \Psr\Log\LoggerInterface                              $log
     */
    public function __construct(
        DealUserfield $dealUserfield,
        Deal $dealService,
        LoggerInterface $log
    ) {
        $this->dealUserfield = $dealUserfield;
        $this->dealService = $dealService;
        $this->log = $log;
    }

    /**
     * Сумма к оплате бонусами
     *
     * @return string
     */
    public function getPayWithBonusesUserFieldName(): string
    {
        return 'B_TO_PAY';
    }

    /**
     * Начислено бонусов
     *
     * @return string
     */
    public function getAccruedBonusesUserFieldName(): string
    {
        return 'B_ACCRUED';
    }

    /**
     * Записать значение в пользовательское поле «Сумма к оплате бонусами»
     *
     * @param int          $b24DealId
     * @param \Money\Money $bonusBalance
     */
    public function setPayWithBonuses(int $b24DealId, Money $bonusBalance): void
    {
        $this->dealService->update(
            $b24DealId,
            [
                'UF_CRM_' . $this->getPayWithBonusesUserFieldName() => (new DecimalMoneyFormatter(new ISOCurrencies()))->format(
                    $bonusBalance
                ),
            ]
        );
    }

    /**
     * Записать значение в пользовательское поле «Начислено бонусов»
     *
     * @param int          $b24DealId
     * @param \Money\Money $bonusBalance
     */
    public function setAccruedBonuses(int $b24DealId, Money $bonusBalance): void
    {
        $this->dealService->update(
            $b24DealId,
            [
                'UF_CRM_' . $this->getAccruedBonusesUserFieldName() => (new DecimalMoneyFormatter(new ISOCurrencies()))->format(
                    $bonusBalance
                ),
            ]
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function assertUserFieldsExists(): void
    {
        $currentUserFieldsNames = array_column($this->dealUserfield->list([], [])->getUserfields(), 'FIELD_NAME');

        if (!in_array('UF_CRM_' . $this->getPayWithBonusesUserFieldName(), $currentUserFieldsNames, true)) {
            throw new WrongBitrix24ConfigurationException(
                sprintf(
                    'predefined contact userfield %s not found, run CLI command «php bin/console install:predefined-userfields»',
                    $this->getPayWithBonusesUserFieldName()
                ),
                0,
                null,
                'создайте поле вызвав в консоли команду php bin/console install:predefined-userfields'
            );
        }

        if (!in_array('UF_CRM_' . $this->getAccruedBonusesUserFieldName(), $currentUserFieldsNames, true)) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('predefined contact userfield %s not found', $this->getAccruedBonusesUserFieldName()),
                0,
                null,
                'создайте поле вызвав в консоли команду php bin/console install:predefined-userfields'
            );
        }
    }

    /**
     * Установка пользовательских полей для Сделки
     *
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     */
    public function installUserFields(): void
    {
        $fieldsToInstall = [
            [
                'FIELD_NAME'        => $this->getPayWithBonusesUserFieldName(),
                'EDIT_FORM_LABEL'   => [
                    'ru' => 'Сумма к оплате бонусами',
                    'en' => 'Pay with bonuses',
                ],
                'LIST_COLUMN_LABEL' => [
                    'ru' => 'Сумма к оплате бонусами',
                    'en' => 'Pay with bonuses',
                ],
                'USER_TYPE_ID'      => 'double',
                'XML_ID'            => 'rarus_bs_pay_with_bonuses',
                'SHOW_IN_LIST'      => 'Y',
                'SETTINGS'          => [
                    'DEFAULT_VALUE' => '0.0',
                    'PRECISION'     => 2,
                ],
            ],
            [
                'FIELD_NAME'        => $this->getAccruedBonusesUserFieldName(),
                'EDIT_FORM_LABEL'   => [
                    'ru' => 'Начислено бонусов',
                    'en' => 'Accrued bonuses',
                ],
                'LIST_COLUMN_LABEL' => [
                    'ru' => 'Начислено бонусов',
                    'en' => 'Accrued bonuses',
                ],
                'USER_TYPE_ID'      => 'double',
                'XML_ID'            => 'rarus_bs_accrued_bonuses',
                'SHOW_IN_LIST'      => 'Y',
                'SETTINGS'          => [
                    'DEFAULT_VALUE' => '0.0',
                    'PRECISION'     => 2,
                ],
            ],
        ];

        $currentUserFields = array_column(
            $this->dealUserfield->list([], [])->getUserfields(),
            'FIELD_NAME'
        );
        foreach ($fieldsToInstall as $userFieldItem) {
            if (!in_array('UF_CRM_' . $userFieldItem['FIELD_NAME'], $currentUserFields, true)) {
                $this->dealUserfield->add($userFieldItem)->getId();
            } else {
                $this->log->debug('DealFields.installUserFields.fieldAlreadyInstalled', [
                    'fieldItem' => $userFieldItem,
                ]);
            }
        }
    }
}