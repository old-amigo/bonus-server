<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Infrastructure\ConsoleCommands\DemoData;

use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Services\ServiceBuilder;
use Money\Currency;
use Money\Money;
use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
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
    protected ServiceBuilder $b24ApiClientServiceBuilder;
    protected ProductBuilder $productBuilder;
    protected PredefinedConfiguration $conf;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:products';
    protected const COUNT = 'count';

    /**
     * GenerateContactsCommand constructor.
     *
     * @param \Bitrix24\SDK\Services\ServiceBuilder                                                 $b24ApiClientServiceBuilder
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductBuilder $productBuilder
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration         $conf
     * @param LoggerInterface                                                                       $logger
     */
    public function __construct(
        ServiceBuilder $b24ApiClientServiceBuilder,
        ProductBuilder $productBuilder,
        PredefinedConfiguration $conf,
        LoggerInterface $logger
    ) {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        $this->logger = $logger;
        $this->b24ApiClientServiceBuilder = $b24ApiClientServiceBuilder;
        $this->productBuilder = $productBuilder;
        $this->conf = $conf;
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
        $newItemsCount = (int)$input->getOption(self::COUNT);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация продуктов в Битрикс24</info>',
                '<info>===============================</info>',
                sprintf('количество добавляемых продуктов: %s', $newItemsCount),
            ]
        );

        try {
            $products = $this->generateNewItems($newItemsCount, $this->conf->getDefaultBonusCurrency());
            $io->section('Добавляем продукты…');
            foreach ($this->b24ApiClientServiceBuilder->getCRMScope()->product()->batch->add($products) as $queryCnt => $item) {
                $io->writeln(
                    [
                        sprintf(
                            '%s | new product id: %s',
                            $queryCnt + 1,
                            $item->getId()
                        ),
                    ]
                );
            }
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
     * @param int             $itemsCount
     * @param \Money\Currency $currency
     *
     * @return array<int, array>
     * @throws \Exception
     */
    protected function generateNewItems(int $itemsCount, Currency $currency): array
    {
        $items = [];
        for ($i = 0; $i < $itemsCount; $i++) {
            $items[] = $this->productBuilder
                ->withPrice(new Money(random_int(20000, 250000), $currency))
                ->withName(sprintf('demo product - %s', random_int(5000, 100000)))
                ->build();
        }

        return $items;
    }
}

