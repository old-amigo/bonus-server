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
 * Class GenerateContactsCommand
 *
 * @package Rarus\Interns\BonusServer\Commands\DemoData
 */
class GenerateContactsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:contacts';
    protected const CONTACTS_COUNT = 'count';

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
            ->setDescription('генерация контактов в Битрикс24')
            ->setHelp('генерация тестовых контактов в Б24,')
            ->addOption(
                self::CONTACTS_COUNT,
                null,
                InputOption::VALUE_REQUIRED,
                'сколько контактов добавить?',
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
        $this->logger->debug('GenerateContactsCommand.start');

        $b24Webhook = (string)$_ENV['BITRIX24_WEBHOOK'];
        $contactsCount = (int)$input->getOption(self::CONTACTS_COUNT);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация контактов в Битрикс24</info>',
                '<info>===============================</info>',
                sprintf('домен для подключения: %s', $b24Webhook),
                sprintf('количество контактов: %s', $contactsCount),
            ]
        );

        try {
            $contacts = $this->generateContacts($contactsCount);
            // собираем операции добавления батч-запросы
            $core = (new \Bitrix24\SDK\Core\CoreBuilder())
                ->withLogger($this->logger)
                ->withWebhookUrl($b24Webhook)
                ->build();
            $batch = new \Bitrix24\SDK\Core\Batch($core, $this->logger);
            foreach ($contacts as $cnt => $contact) {
                $batch->addCommand('crm.contact.add', $contact);
            }
            $io->section('Добавляем контакты…');

            // исполняем запросы к Б24
            $timeStart = microtime(true);
            foreach ($batch->getTraversable(true) as $queryCnt => $queryResultData) {
                /**
                 * @var $queryResultData \Bitrix24\SDK\Core\Response\DTO\ResponseData
                 */
                $io->writeln(
                    [
                        sprintf(
                            '%s | contact id: %s',
                            $queryCnt + 1,
                            $queryResultData->getResult()->getResultData()[0]
                        ),
                    ]
                );
            }
            $timeEnd = microtime(true);
            $io->writeln(sprintf('batch query duration: %s seconds', round($timeEnd - $timeStart, 2)) . PHP_EOL . PHP_EOL);
            $io->success('контакты успешно добавлены');
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
        $this->logger->debug('GenerateContactsCommand.finish');

        return 0;
    }

    /**
     * @param int $contactsCount
     *
     * @return array<int, array> $contacts
     */
    protected function generateContacts(int $contactsCount): array
    {
        $contacts = [];
        for ($i = 0; $i < $contactsCount; $i++) {
            $contacts[] = [
                'fields' => [
                    'NAME'        => sprintf('имя-%s', $i),
                    'LAST_NAME'   => 'фамилия',
                    'SECOND_NAME' => 'отчество',
                ],
            ];
        }

        return $contacts;
    }
}

