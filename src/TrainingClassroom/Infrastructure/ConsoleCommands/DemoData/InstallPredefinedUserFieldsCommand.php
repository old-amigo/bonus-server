<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Infrastructure\ConsoleCommands\DemoData;

use Psr\Log\LoggerInterface;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallPredefinedUserFieldsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    protected ContactFields $b24contactFields;
    protected DealFields $b24dealFields;
    /**
     * @var string
     */
    protected static $defaultName = 'install:predefined-userfields';

    /**
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields $b24contactFields
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields    $b24dealFields
     * @param \Psr\Log\LoggerInterface                                                                 $logger
     */
    public function __construct(ContactFields $b24contactFields, DealFields $b24dealFields, LoggerInterface $logger)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        $this->logger = $logger;
        $this->b24contactFields = $b24contactFields;
        $this->b24dealFields = $b24dealFields;
        parent::__construct();
    }

    /**
     * настройки
     */
    protected function configure(): void
    {
        $this
            ->setDescription('установка штатных пользовательских полей')
            ->setHelp('установка пользовательских полей для сущностей «Контакты» и «Сделки»');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->debug('InstallPredefinedUserFieldsCommand.start');

        $io = new SymfonyStyle($input, $output);

        $output->writeln(
            [
                '<info>Установка пользовательских полей</info>',
                '<info>===============================</info>',
            ]
        );

        // контакты
        $this->b24contactFields->installUserFields();
        $output->writeln('<info>Контакты — поля созданы</info>');

        // сделки
        $this->b24dealFields->installUserFields();
        $output->writeln(
            [
                '<info>Сделки — поля созданы</info>',
                '',
                '<info>Сущности CRM подготовлены к работе с предметной областью (ಠ_ಠ)つ──☆*ﾟ</info>',
            ]
        );
        $this->logger->debug('InstallPredefinedUserFieldsCommand.finish');

        return 0;
    }
}

