<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields;

use Bitrix24\SDK\Services\CRM\Contact\Service\Contact;
use Bitrix24\SDK\Services\CRM\Contact\Service\ContactUserfield;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;

class ContactFields
{
    private ContactUserfield $contactUserfield;
    private Contact $contactService;
    private LoggerInterface $log;

    /**
     * @param \Bitrix24\SDK\Services\CRM\Contact\Service\ContactUserfield $contactUserfield
     * @param \Bitrix24\SDK\Services\CRM\Contact\Service\Contact          $contactService
     * @param \Psr\Log\LoggerInterface                                    $log
     */
    public function __construct(
        ContactUserfield $contactUserfield,
        Contact $contactService,
        LoggerInterface $log
    ) {
        $this->contactUserfield = $contactUserfield;
        $this->contactService = $contactService;
        $this->log = $log;
    }

    /**
     * Записать в пользовательское поле баланс бонусов у контакта
     *
     * @param int          $b24ContactId
     * @param \Money\Money $bonusBalance
     */
    public function setBonusBalance(int $b24ContactId, Money $bonusBalance): void
    {
        $this->contactService->update(
            $b24ContactId,
            [
                'UF_CRM_' . $this->getBonusBalanceUserFieldName() => (new DecimalMoneyFormatter(new ISOCurrencies()))->format(
                    $bonusBalance
                ),
            ]
        );
    }

    /**
     * @return string
     */
    public function getBonusBalanceUserFieldName(): string
    {
        return 'B_BALANCE';
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function assertUserFieldsExists(): void
    {
        $currentUserFieldsNames = array_column($this->contactUserfield->list([], [])->getUserfields(), 'FIELD_NAME');
        if (!in_array('UF_CRM_' . $this->getBonusBalanceUserFieldName(), $currentUserFieldsNames, true)) {
            throw new WrongBitrix24ConfigurationException(
                sprintf(
                    'predefined contact userfield %s not found, run CLI command «php bin/console install:predefined-userfields»',
                    $this->getBonusBalanceUserFieldName()
                ),
                0,
                null,
                'создайте поле вызвав в консоли команду php bin/console install:predefined-userfields'
            );
        }
    }

    /**
     * Установка пользовательских полей для контакта
     *
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     */
    public function installUserFields(): void
    {
        $fieldsToInstall = [
            [
                'FIELD_NAME'        => $this->getBonusBalanceUserFieldName(),
                'EDIT_FORM_LABEL'   => [
                    'ru' => 'Баланс бонусов',
                    'en' => 'Bonus balance',
                ],
                'LIST_COLUMN_LABEL' => [
                    'ru' => 'Баланс бонусов',
                    'en' => 'Bonus balance',
                ],
                'USER_TYPE_ID'      => 'double',
                'XML_ID'            => 'rarus_bs_bonus_balance',
                'SHOW_IN_LIST'      => 'Y',
                'SETTINGS'          => [
                    'DEFAULT_VALUE' => '0.0',
                    'PRECISION'     => 2,
                ],
            ],
        ];

        $currentUserFields = array_column(
            $this->contactUserfield->list([], [])->getUserfields(),
            'FIELD_NAME'
        );
        foreach ($fieldsToInstall as $userFieldItem) {
            if (!in_array('UF_CRM_' . $userFieldItem['FIELD_NAME'], $currentUserFields, true)) {
                $this->contactUserfield->add($userFieldItem)->getId();
            } else {
                $this->log->debug('ContactFields.installUserFields.fieldAlreadyInstalled', [
                    'fieldItem' => $userFieldItem,
                ]);
            }
        }
    }
}