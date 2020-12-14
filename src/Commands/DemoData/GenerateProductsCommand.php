<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Commands\DemoData;

use Bitrix24\SDK\Core\Exceptions\BaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class GenerateProductsCommand
 *
 * @package Rarus\Interns\BonusServer\Commands\DemoData
 */
class GenerateProductsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:products';
    protected const COUNT = 'count';

    /**
     * GenerateContactsCommand constructor.
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
            ->setDescription('генерация продуктов в товарном каталоге Битрикс24')
            ->setHelp('генерация тестовых продуктов в Б24,')
            ->addOption(
                self::COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'сколько продуктов добавить?',
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
        $this->logger->debug('GenerateProductsCommand.start');

        $b24Webhook = (string)$_ENV['BITRIX24_WEBHOOK'];
        $newItemsCount = (int)$input->getOption(self::COUNT);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация продуктов в Битрикс24</info>',
                '<info>===============================</info>',
                sprintf('домен для подключения: %s', $b24Webhook),
                sprintf('количество добавляемых продуктов: %s', $newItemsCount),
            ]
        );

        try {
            $contacts = $this->generateNewItems($newItemsCount);
            // собираем операции добавления батч-запросы
            $core = (new \Bitrix24\SDK\Core\CoreBuilder())
                ->withLogger($this->logger)
                ->withWebhookUrl($b24Webhook)
                ->build();
            $batch = new \Bitrix24\SDK\Core\Batch($core, $this->logger);
            foreach ($contacts as $cnt => $contact) {
                $batch->addCommand('crm.product.add', $contact);
            }
            $io->section('Добавляем продукты…');

            // исполняем запросы к Б24
            $timeStart = microtime(true);
            foreach ($batch->getTraversable(true) as $queryCnt => $queryResultData) {
                /**
                 * @var $queryResultData \Bitrix24\SDK\Core\Response\DTO\ResponseData
                 */
                $io->writeln(
                    [
                        sprintf(
                            '%s | new product id: %s',
                            $queryCnt + 1,
                            $queryResultData->getResult()->getResultData()[0]
                        ),
                    ]
                );
            }
            $timeEnd = microtime(true);
            $io->writeln(sprintf('batch query duration: %s seconds', round($timeEnd - $timeStart, 2)) . PHP_EOL . PHP_EOL);
            $io->success('продукты успешно добавлены');
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
                    sprintf('%s', $exception->getMessage()),
                ]
            );
        }
        $this->logger->debug('GenerateProductsCommand.finish');

        return 0;
    }

    /**
     * @param int $itemsCount
     *
     * @return array<int, array>
     * @throws \Exception
     */
    protected function generateNewItems(int $itemsCount): array
    {
        $items = [];
        for ($i = 0; $i < $itemsCount; $i++) {
            $items[] = [
                'fields' => [
                    'ACTIVE'          => 'Y',
                    'PRICE'           => random_int(200, 2500),
                    'NAME'            => sprintf('demo product - %s', random_int(5000, 100000)),
                    'XML_ID'          => '',
                    'CURRENCY_ID'     => 'RUB',
                    'DETAIL_PICTURE'  => null,
                    'PREVIEW_PICTURE' => null,
                    'MEASURE'         => null,
                    'SECTION_ID'      => null,
                    'SORT'            => null,
                    'VAT_ID'          => null,
                ],
            ];
        }

        return $items;
    }
}

