<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Infrastructure\ConsoleCommands\DemoData;

use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryItemResult;
use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult;
use Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult;
use Bitrix24\SDK\Services\ServiceBuilder;
use Exception;
use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class GenerateDealsCommand
 *
 * @package Rarus\Interns\BonusServer\Commands\DemoData
 */
class GenerateDealsCommand extends Command
{
    protected ServiceBuilder $b24ApiClientServiceBuilder;
    protected LoggerInterface $logger;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:deals';
    protected const COUNT = 'count';
    protected const START_STAGE = 'start_stage';
    protected const TARGET_STAGE = 'target_stage';
    protected const B24_DEAL_CATEGORY_NAME = 'delivery';
    protected const STAGE_NEW_ORDER = 'new_order';
    protected const STAGE_BONUS_PAYMENT = 'bonus_payment';
    protected const STAGE_ORDER_DELIVERED = 'order_delivered';
    protected const STAGE_ORDER_CANCELLED = 'order_cancelled';
    protected const B24_MIN_ENTITIES_COUNT = 2;
    /**
     * @var array|string[]
     */
    protected array $dealStages = [
        self::STAGE_NEW_ORDER,
        self::STAGE_BONUS_PAYMENT,
        self::STAGE_ORDER_DELIVERED,
        self::STAGE_ORDER_CANCELLED,
    ];

    /**
     * GenerateDealsCommand constructor.
     *
     * @param \Bitrix24\SDK\Services\ServiceBuilder $b24ApiClientServiceBuilder
     * @param LoggerInterface                       $logger
     */
    public function __construct(ServiceBuilder $b24ApiClientServiceBuilder, LoggerInterface $logger)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        $this->b24ApiClientServiceBuilder = $b24ApiClientServiceBuilder;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * настройки
     */
    protected function configure(): void
    {
        $this
            ->setDescription('генерация сделок Битрикс24')
            ->setHelp('генерация тестовых сделок в Б24,')
            ->addOption(
                self::START_STAGE,
                null,
                InputOption::VALUE_REQUIRED,
                'в какой стадии создаётся сделка',
                self::STAGE_NEW_ORDER
            )
            ->addOption(
                self::TARGET_STAGE,
                null,
                InputOption::VALUE_OPTIONAL,
                'в какую стадию передвинуть сделку',
            )
            ->addOption(
                self::COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'сколько сделок добавить?',
                1
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('GenerateDealsCommand.start');

        $newItemsCount = (int)$input->getOption(self::COUNT);
        $b24StartStageName = (string)$input->getOption(self::START_STAGE);
        $b24TargetStageName = (string)$input->getOption(self::TARGET_STAGE);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация сделок в Битрикс24</info>',
                '<info>============================</info>',
                sprintf('количество добавляемых сделок: %s', $newItemsCount),
                sprintf('стартовая стадия: %s', $b24StartStageName),
                sprintf('целевая стадия: %s', $b24TargetStageName),
            ]
        );

        try {
            // предусловия
            // есть нужное направление сделок
            $categories = $this->b24ApiClientServiceBuilder->getCRMScope()->dealCategory()
                ->list([], [], [], 0)->getDealCategories();
            $this->assertDealsHasDeliveryCategory($output, $categories);

            // в направлении сделок есть служебные стадии
            $deliveryCategoryId = $this->getDeliveryCategoryId($categories);
            $b24DeliveryCategoryStages = $this->b24ApiClientServiceBuilder->getCRMScope()->dealCategoryStage()
                ->list($deliveryCategoryId)->getDealCategoryStages();
            $this->assertDeliveryCategoryHasAllStages($output, $b24DeliveryCategoryStages);

            // есть контакты
            $b24Contacts = $this->b24ApiClientServiceBuilder->getCRMScope()->contact()
                ->list([], [], [], 0)->getContacts();
            $this->assertContactsExists($output, $b24Contacts);

            // есть продукты
            $b24Products = $this->b24ApiClientServiceBuilder->getCRMScope()->product()
                ->list([], [], [], 0)->getProducts();
            $this->assertProductsExists($output, $b24Products);

            $output->writeln('<info>проверка портала успешно пройдена, можно генерировать сделки…</info>');

            // генерим сделки
            for ($i = 0; $i < $newItemsCount; $i++) {
                // добавили сделку для существующего контакта
                $randomContactId = (int)array_column($b24Contacts, 'ID')[random_int(0, count($b24Contacts) - 1)];
                $dealId = $this->b24ApiClientServiceBuilder->getCRMScope()->deal()->add(
                    $this->generateNewDeal(
                        $deliveryCategoryId,
                        $randomContactId,
                        $this->getDealStageByName($b24StartStageName, $b24DeliveryCategoryStages)->STATUS_ID
                    )
                )->getId();

                // сгенерировали ТЧ для сделки на основании продуктов и перечитали сделку
                $productRows = $this->generateProductRows($b24Products, $dealId);
                $this->b24ApiClientServiceBuilder->getCRMScope()->dealProductRows()->set($dealId, $productRows);

                // передвинули сделку на нужную стадию если надо
                if ($b24TargetStageName !== null) {
                    $this->b24ApiClientServiceBuilder->getCRMScope()->deal()->update(
                        $dealId,
                        [
                            'STAGE_ID' => $this->getDealStageByName($b24TargetStageName, $b24DeliveryCategoryStages)->STATUS_ID,
                        ], []
                    );
                }
                $updatedDeal = $this->b24ApiClientServiceBuilder->getCRMScope()->deal()->get($dealId)->deal();
                $output->writeln(
                    sprintf(
                        '%s | %s - %s | %s %s | %s',
                        $i + 1,
                        $updatedDeal->ID,
                        $updatedDeal->TITLE,
                        $updatedDeal->OPPORTUNITY,
                        $updatedDeal->CURRENCY_ID,
                        sprintf(
                            '%s/crm/deal/details/%s/',
                            $this->b24ApiClientServiceBuilder->getCRMScope()
                                ->deal()->core->getApiClient()->getCredentials()->getDomainUrl(),
                            $updatedDeal->ID
                        )
                    )
                );
            }
            $io->success('сделки успешно созданы');
        } catch (WrongBitrix24ConfigurationException $exception) {
            $io = new SymfonyStyle($input, $output);
            $io->caution('Битрикс24 сконфигурирован неправильно');
            $io->text(
                [
                    '(╯°益°)╯彡┻━┻',
                    '',
                    sprintf('проблема: %s', $exception->getMessage()),
                    '',
                    sprintf('как починить: %s', $exception->getAdvice()),
                ]
            );
        } catch (BaseException $exception) {
            $io = new SymfonyStyle($input, $output);
            $io->caution('ошибка при работе с Битрикс24');
            $io->text(
                [
                    '(╯°益°)╯彡┻━┻',
                    '',
                    sprintf('%s', $exception->getMessage()),
                ]
            );
        } catch (\Throwable $exception) {
            $io = new SymfonyStyle($input, $output);
            $io->caution('неизвестная ошибка');
            $io->text(
                [
                    '(╯°益°)╯彡┻━┻',
                    '',
                    sprintf('message: %s', $exception->getMessage()),
                    sprintf('file %s:%s', $exception->getFile(), $exception->getLine()),
                    sprintf('trace %s', $exception->getTraceAsString()),
                ]
            );
        }
        $this->logger->debug('GenerateDealsCommand.finish');

        return 0;
    }

    /**
     * @param int    $categoryId
     * @param int    $b24ContactId
     * @param string $b24StageId
     *
     * @return array
     */
    protected function generateNewDeal(int $categoryId, int $b24ContactId, string $b24StageId): array
    {
        return [
            'TITLE'       => sprintf('test-order-%s', time()),
            'CONTACT_ID'  => $b24ContactId,
            'COMMENTS'    => sprintf('тестовый заказ для контакта %s', $b24ContactId),
            'CATEGORY_ID' => $categoryId,
            'STAGE_ID'    => $b24StageId,
        ];
    }

    /**
     * @param array $b24Products
     * @param int   $dealId
     *
     * @return array
     * @throws \Exception
     */
    protected function generateProductRows(array $b24Products, int $dealId): array
    {
        //todo унести в константы
        $productsCount = 2;

        $productRows = [];
        for ($i = 0; $i < $productsCount; $i++) {
            $randomProduct = $b24Products[random_int(0, count($b24Products) - 1)];
            $productRows[] = $this->generateProductRow($dealId, $randomProduct);
        }

        return $productRows;
    }

    /**
     * @param int               $dealId
     * @param ProductItemResult $product
     *
     * @return array
     * @throws \Exception
     */
    protected function generateProductRow(int $dealId, ProductItemResult $product): array
    {
        return [
            'CUSTOMIZED'            => 'N',
            'DISCOUNT_RATE'         => 0,
            'DISCOUNT_SUM'          => 0,
            'DISCOUNT_TYPE_ID'      => 1,
            'MEASURE_NAME'          => $product->MEASURE,
            'ORIGINAL_PRODUCT_NAME' => $product->NAME,
            'OWNER_ID'              => $dealId,
            'OWNER_TYPE'            => 'D',
            'PRICE'                 => $product->PRICE,
            'PRICE_ACCOUNT'         => $product->PRICE,
            'PRICE_BRUTTO'          => $product->PRICE,
            'PRICE_EXCLUSIVE'       => $product->PRICE,
            'PRICE_NETTO'           => $product->PRICE,
            'PRODUCT_DESCRIPTION'   => $product->DESCRIPTION,
            'PRODUCT_ID'            => $product->ID,
            'PRODUCT_NAME'          => $product->NAME,
            //todo унести в константы
            'QUANTITY'              => random_int(1, 5),
            'TAX_INCLUDED'          => 'Y',
            'TAX_RATE'              => '20',
        ];
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface                       $output
     * @param array<int, \Bitrix24\SDK\Services\CRM\Contact\Result\ContactItemResult> $contacts
     *
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    private function assertContactsExists(OutputInterface $output, array $contacts): void
    {
        if ($output->isVerbose()) {
            $output->writeln(
                [
                    '',
                    'проверяем, есть ли контакты в Б24…',
                ]
            );
            foreach ($contacts as $contact) {
                $output->writeln(sprintf(' %s | %s %s', $contact->ID, $contact->NAME, $contact->LAST_NAME));
            }
        }

        if (count($contacts) < self::B24_MIN_ENTITIES_COUNT) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('в Битрикс24 должно быть минимум %s контакта', self::B24_MIN_ENTITIES_COUNT),
                0,
                null,
                'вызовите команду генерации контактов «php bin/console generate:contacts» или добавьте их руками в CRM',
            );
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface                       $output
     * @param array<int, \Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult> $products
     *
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    protected function assertProductsExists(OutputInterface $output, array $products): void
    {
        if ($output->isVerbose()) {
            $output->writeln(
                [
                    '',
                    'проверяем, есть ли продукты в ТК Б24…',
                ]
            );
            foreach ($products as $product) {
                $output->writeln(sprintf(' %s | %s - %s %s', $product->ID, $product->NAME, $product->PRICE, $product->CURRENCY_ID));
            }
        }

        if (count($products) < self::B24_MIN_ENTITIES_COUNT) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('в Битрикс24 должно быть минимум %s продукта', self::B24_MIN_ENTITIES_COUNT),
                0,
                null,
                'вызовите команду генерации продуктов «php bin/console generate:products» или добавьте их руками в товарный каталог Битрикс24 в CRM',
            );
        }
    }

    /**
     * @param DealCategoryItemResult[] $categories
     *
     * @return int
     */
    protected function getDeliveryCategoryId(array $categories): int
    {
        return (int)array_column($categories, 'ID', 'NAME')[self::B24_DEAL_CATEGORY_NAME];
    }

    /**
     * @param OutputInterface               $output
     * @param DealCategoryStageItemResult[] $stages
     *
     * @throws WrongBitrix24ConfigurationException
     */
    protected function assertDeliveryCategoryHasAllStages(OutputInterface $output, array $stages): void
    {
        if ($output->isVerbose()) {
            $output->writeln(
                [
                    '',
                    sprintf('проверяем, есть ли нужные стадии %s в направлении:', implode(', ', $this->dealStages)),
                ]
            );
            foreach ($stages as $stage) {
                $output->writeln(sprintf(' %s - %s', $stage->STATUS_ID, $stage->NAME));
            }
        }
        $stages = array_values(array_column($stages, 'NAME', 'STATUS_ID'));

        $diff = array_intersect($stages, $this->dealStages);
        if (count($diff) !== count($this->dealStages)) {
            $lostStages = implode(', ', array_diff($this->dealStages, $diff));
            throw new WrongBitrix24ConfigurationException(
                sprintf('в списке стадий не найдены стадии: %s', $lostStages),
                0,
                null,
                sprintf(
                    'откройте Битрикс24, CRM → Сделки, выберите «воронки и туннели продаж» выберите направление %s, добавьте нужные или отредактируйте существующие: %s ',
                    self::B24_DEAL_CATEGORY_NAME,
                    $lostStages
                )
            );
        }
    }

    /**
     * @param string                        $b24DealStageName
     * @param DealCategoryStageItemResult[] $b24DealStages
     *
     * @return \Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult
     * @throws \Exception
     */
    protected function getDealStageByName(string $b24DealStageName, array $b24DealStages): DealCategoryStageItemResult
    {
        foreach ($b24DealStages as $stage) {
            if ($stage->NAME === $b24DealStageName) {
                return $stage;
            }
        }
        throw new Exception(sprintf('стадия сделок по имени %s не найдена', $b24DealStageName));
    }

    /**
     * @param OutputInterface          $output
     * @param DealCategoryItemResult[] $categories
     *
     * @throws WrongBitrix24ConfigurationException
     */
    protected function assertDealsHasDeliveryCategory(OutputInterface $output, array $categories): void
    {
        if ($output->isVerbose()) {
            $output->writeln(
                [
                    '',
                    sprintf('проверяем, есть ли направление %s в списке направлений сделок:', self::B24_DEAL_CATEGORY_NAME),
                ]
            );
            foreach ($categories as $category) {
                $output->writeln(sprintf(' %s - %s', $category->ID, $category->NAME));
            }
        }
        $categoryNames = [];
        foreach ($categories as $category) {
            $categoryNames[] = $category->NAME;
        }
        if (in_array(self::B24_DEAL_CATEGORY_NAME, $categoryNames, true)) {
            return;
        }

        throw new WrongBitrix24ConfigurationException(
            sprintf('в списке направлений сделок в Битрикс24 не найдено направление с названием %s', self::B24_DEAL_CATEGORY_NAME),
            0,
            null,
            sprintf(
                'рядом с кнопкой «добавить сделку» выберите в дропдауне пункт «воронки и туннели продаж» и добавьте новую воронку с названием %s',
                self::B24_DEAL_CATEGORY_NAME
            )
        );
    }
}