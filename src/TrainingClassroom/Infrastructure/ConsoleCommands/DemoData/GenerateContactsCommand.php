<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Infrastructure\ConsoleCommands\DemoData;

use Bitrix24\SDK\Core\Exceptions\BaseException;
use Bitrix24\SDK\Services\ServiceBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateContactsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    protected ServiceBuilder $b24ApiClientServiceBuilder;
    /**
     * @var string
     */
    protected static $defaultName = 'generate:contacts';
    protected const CONTACTS_COUNT = 'count';

    /**
     * GenerateContactsCommand constructor.
     *
     * @param \Bitrix24\SDK\Services\ServiceBuilder $b24ApiClientServiceBuilder
     * @param LoggerInterface                       $logger
     */
    public function __construct(ServiceBuilder $b24ApiClientServiceBuilder, LoggerInterface $logger)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        $this->logger = $logger;
        $this->b24ApiClientServiceBuilder = $b24ApiClientServiceBuilder;
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

        $contactsCount = (int)$input->getOption(self::CONTACTS_COUNT);
        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Генерация контактов в Битрикс24</info>',
                '<info>===============================</info>',
                sprintf('количество контактов: %s', $contactsCount),
            ]
        );

        try {
            $io->section('Добавляем контакты…');
            $contacts = $this->generateNewItems($contactsCount);
            // исполняем запросы к Б24
            foreach ($this->b24ApiClientServiceBuilder->getCRMScope()->contact()->batch->add($contacts) as $cnt => $item) {
                $io->writeln(
                    [
                        sprintf(
                            '%s | new contact id: %s',
                            $cnt + 1,
                            $item->getId()
                        ),
                    ]
                );
            }
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
    protected function generateNewItems(int $contactsCount): array
    {
        $contacts = [];
        for ($i = 0; $i < $contactsCount; $i++) {
            $contacts[] = [
                'NAME'        => sprintf('имя-%s', $i),
                'LAST_NAME'   => 'фамилия',
                'SECOND_NAME' => 'отчество',
            ];
        }

        return $contacts;
    }
}

