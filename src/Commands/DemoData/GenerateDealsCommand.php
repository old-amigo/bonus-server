<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Commands\DemoData;

use Bitrix24\SDK\Core\Batch;
use Bitrix24\SDK\Core\CoreBuilder;
use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Services\ServiceBuilder;
use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\Commands\Exceptions\WrongBitrix24ConfigurationException;
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
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:deals';
    protected const COUNT = 'count';
    protected const START_STAGE = 'stage';
    protected const B24_DEAL_CATEGORY_NAME = 'delivery';
    protected const STAGE_NEW_ORDER = 'new_order';
    protected const STAGE_BONUS_PAYMENT = 'bonus_payment';
    protected const STAGE_ORDER_DELIVERED = 'order_delivered';
    protected const STAGE_ORDER_CANCELLED = 'order_cancelled';
    protected const B24_MIN_ENTITIES_COUNT = 2;
    protected array $dealStages = [
        self::STAGE_NEW_ORDER,
        self::STAGE_BONUS_PAYMENT,
        self::STAGE_ORDER_DELIVERED,
        self::STAGE_ORDER_CANCELLED,
    ];

    protected array $b24Contacts;
    protected array $b24Products;

    /**
     * GenerateDealsCommand constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
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
                'на какую стадию передвинуть готовую сделку',
                'new_order'
            )
            ->addOption(
                self::COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'сколько сделок добавить?',
                5
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

        $b24WebhookUrl = (string)$_ENV['BITRIX24_WEBHOOK'];
        $b24Domain = parse_url($b24WebhookUrl, PHP_URL_HOST);
        $newItemsCount = (int)$input->getOption(self::COUNT);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация сделок в Битрикс24</info>',
                '<info>============================</info>',
                sprintf('домен для подключения: %s', $b24Domain),
                sprintf('количество добавляемых сделок: %s', $newItemsCount),
            ]
        );

        try {
            $b24ServiceBuilder = $this->getServiceBuilder($b24WebhookUrl);
            // получили контакты и продукты из Б24
            $this->getContacts($b24ServiceBuilder);
            $this->getProducts($b24ServiceBuilder);

            // предусловия
            // есть нужное направление сделок
            $categories = $b24ServiceBuilder->getCRMScope()->dealCategory()
                ->list([], [], [], 0)->getResponseData()->getResult()->getResultData();
            $this->assertDealsHasDeliveryCategory($output, $categories);
            // в направлении сделок есть служебные стадии
            $deliveryCategoryId = $this->getDeliveryCategoryId($categories);
            $stages = $b24ServiceBuilder->getCRMScope()->dealCategoryStages()
                ->list($deliveryCategoryId)->getResponseData()->getResult()->getResultData();
            $this->assertDeliveryCategoryHasAllStages($output, $stages);
            // есть продукты
            $this->assertProductsExists($output, $this->b24Products);
            // есть контакты
            $this->assertContactsExists($output, $this->b24Contacts);
            $output->writeln('<info>проверка портала успешно пройдена, можно генерировать сделки…</info>');


            // генерим сделки
            for ($i = 0; $i < $newItemsCount; $i++) {
                $newDeal = $this->generateDealDTO($deliveryCategoryId);
                // добавили сделку в Б24
                $dealId = $b24ServiceBuilder->getCRMScope()->deals()->add($newDeal)->getResponseData()->getResult()->getResultData()[0];
                // сделали ТЧ для сделки на оснвании продуктов и перечитали сделку
                $productRows = $this->generateProductRows($dealId);
                $b24ServiceBuilder->getCRMScope()->dealProductRows()->set($dealId, $productRows);
                $updatedDeal = $b24ServiceBuilder->getCRMScope()->deals()->get($dealId)->getResponseData()->getResult()->getResultData();

                $output->writeln(
                    sprintf(
                        '%s | %s - %s | %s %s | %s',
                        $i + 1,
                        $updatedDeal['ID'],
                        $updatedDeal['TITLE'],
                        $updatedDeal['OPPORTUNITY'],
                        $updatedDeal['CURRENCY_ID'],
                        sprintf('https://%s/crm/deal/details/%s/', $b24Domain, $updatedDeal['ID'])
                    )
                );


                // передвинули сделку на нужную стадию
                // поддождали \ ушли на след стадию \

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
                    sprintf('%s', $exception->getMessage()),
                ]
            );
        } catch (\Throwable $exception) {
            $io = new SymfonyStyle($input, $output);
            $io->caution('неизвестная ошибка');
            $io->text(
                [
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
     * @param int $categoryId
     *
     * @return array
     * @throws \Exception
     */
    protected function generateDealDTO(int $categoryId): array
    {
        $contactId = array_column($this->b24Contacts, 'ID')[random_int(0, count($this->b24Contacts) - 1)];

        return [
            'TITLE'       => sprintf('test-order-%s', time()),
            'CONTACT_ID'  => $contactId,
            'COMMENTS'    => sprintf('тестовый заказ для контакта %s', $contactId),
            'CATEGORY_ID' => $categoryId,
        ];
    }

    /**
     * @param int $dealId
     *
     * @return array
     */
    protected function generateProductRows(int $dealId): array
    {
        $productsCount = 2;

        $productRows = [];
        for ($i = 0; $i < $productsCount; $i++) {
            $product = $this->b24Products[random_int(0, count($this->b24Products) - 1)];
            $productRows[] = $this->generateProductRowDTO($dealId, $product);
        }


        return $productRows;
    }

    /**
     * @param int   $dealId
     * @param array $product
     *
     * @return array
     * @throws \Exception
     */
    protected function generateProductRowDTO(int $dealId, array $product): array
    {
        return [
            'CUSTOMIZED'            => 'N',
            'DISCOUNT_RATE'         => 0,
            'DISCOUNT_SUM'          => 0,
            'DISCOUNT_TYPE_ID'      => 1,
            'MEASURE_NAME'          => $product['MEASURE'],
            'ORIGINAL_PRODUCT_NAME' => $product['NAME'],
            'OWNER_ID'              => $dealId,
            'OWNER_TYPE'            => 'D',
            'PRICE'                 => $product['PRICE'],
            'PRICE_ACCOUNT'         => $product['PRICE'],
            'PRICE_BRUTTO'          => $product['PRICE'],
            'PRICE_EXCLUSIVE'       => $product['PRICE'],
            'PRICE_NETTO'           => $product['PRICE'],
            'PRODUCT_DESCRIPTION'   => $product['DESCRIPTION'],
            'PRODUCT_ID'            => $product['ID'],
            'PRODUCT_NAME'          => $product['NAME'],
            'QUANTITY'              => random_int(1, 5),
            'TAX_INCLUDED'          => 'Y',
            'TAX_RATE'              => '20',
        ];
    }

    /**
     * @param ServiceBuilder $b24ServiceBuilder
     *
     * @throws BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function getContacts(ServiceBuilder $b24ServiceBuilder): void
    {
        $this->b24Contacts = $b24ServiceBuilder->getCRMScope()->contacts()
            ->list([], [], [], 0)->getResponseData()->getResult()->getResultData();
    }

    /**
     * @param ServiceBuilder $b24ServiceBuilder
     *
     * @throws BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function getProducts(ServiceBuilder $b24ServiceBuilder): void
    {
        $this->b24Products = $b24ServiceBuilder->getCRMScope()->products()
            ->list([], [], [])->getResponseData()->getResult()->getResultData();
    }

    /**
     * @param OutputInterface $output
     * @param array           $contacts
     *
     * @throws WrongBitrix24ConfigurationException
     */
    protected function assertContactsExists(OutputInterface $output, array $contacts): void
    {
        if ($output->isVerbose()) {
            $output->writeln(
                [
                    '',
                    'проверяем, есть ли контакты в Б24…',
                ]
            );
            foreach ($contacts as $contact) {
                $output->writeln(sprintf(' %s | %s %s', $contact['ID'], $contact['NAME'], $contact['LAST_NAME']));
            }
        }

        if (count($contacts) < self::B24_MIN_ENTITIES_COUNT) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('в Битрикс24 должно быть минимум %s контакта' . self::B24_MIN_ENTITIES_COUNT),
                0,
                null,
                'вызовите команду генерации контактов или добавьте их руками в CRM',
            );
        }
    }

    /**
     * @param OutputInterface $output
     * @param array           $products
     *
     * @throws WrongBitrix24ConfigurationException
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
                $output->writeln(sprintf(' %s | %s - %s %s', $product['ID'], $product['NAME'], $product['PRICE'], $product['CURRENCY_ID']));
            }
        }


        if (count($products) < self::B24_MIN_ENTITIES_COUNT) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('в Битрикс24 должно быть минимум %s продукта' . self::B24_MIN_ENTITIES_COUNT),
                0,
                null,
                'вызовите команду генерации продуктов или добавьте их руками в товарный каталог Битрикс24 в CRM',
            );
        }
    }

    /**
     * @param array $categories
     *
     * @return int
     */
    protected function getDeliveryCategoryId(array $categories): int
    {
        return (int)array_column($categories, 'ID', 'NAME')[self::B24_DEAL_CATEGORY_NAME];
    }

    /**
     * @param OutputInterface $output
     * @param array           $stages
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
                $output->writeln(sprintf(' %s - %s', $stage['STATUS_ID'], $stage['NAME']));
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
     * @param OutputInterface $output
     * @param array           $categories
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
                $output->writeln(sprintf(' %s - %s', $category['ID'], $category['NAME']));
            }
        }
        $categoryNames = array_column($categories, 'NAME');
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

    /**
     * @param string $webhookUrl
     *
     * @return ServiceBuilder
     * @throws InvalidArgumentException
     */
    protected function getServiceBuilder(string $webhookUrl): ServiceBuilder
    {
        $core = (new CoreBuilder())
            ->withWebhookUrl($webhookUrl)
            ->withLogger($this->logger)
            ->build();
        $batch = new Batch($core, $this->logger);


        return new ServiceBuilder($core, $batch, $this->logger);
    }
}