<?php

// Huy Vu 
// hvu@sugarcrm.com 

namespace Toothpaste\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Toothpaste\Sugar;

class RestoreRecordSQLCommand extends Command
{
    protected static $defaultName = 'local:data:restore-record-query';

    protected function configure()
    {
        $this
            ->setDescription('Restore a soft-deleted record (if present) and most of its relationships from a backup database')
            ->setHelp('Command to restore a soft-deleted record (if present) and most of its relationships')
            ->addOption('instance', null, InputOption::VALUE_REQUIRED, 'Instance relative or absolute path')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Module to restore')
            ->addOption('record', null, InputOption::VALUE_REQUIRED, 'Record id to restore')
            ->addOption('db_backup', null, InputOption::VALUE_REQUIRED, 'The name of the config of the backup database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Toothpaste\Toothpaste::resetStartTime();

        $output->writeln('Queries to be run on the latest instance:');

        $instance = $input->getOption('instance');
        $module = $input->getOption('module');
        $record = $input->getOption('record');
        $db_backup = $input->getOption('db_backup');

        if (empty($instance)) {
            $output->writeln('Please provide the instance path. Check with --help for the correct syntax');
        } else {
            $path = Sugar\Instance::validate($instance);

            if (!empty($path)) {
                if (!empty($module) && !empty($record)) {
                    $output->writeln('Entering ' . $path . '...');
                    $output->writeln('Setting up instance...');
                    Sugar\Instance::setup();
                    $logic = new Sugar\Logic\RestoreRecordSQL($module, $record, $db_backup);
                    $logic->setLogger($output);
                    $logic->printSQL();
                } else {
                    $output->writeln('Please provide the module name, the single record id and the name of the backup db to restore. Check with --help for the correct syntax');
                }
            } else {
                $output->writeln($instance . ' does not contain a valid Sugar installation. Aborting...');
            }
        }
    }
}
