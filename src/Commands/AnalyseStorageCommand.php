<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Toothpaste\Sugar;

class AnalyseStorageCommand extends Command
{
    protected static $defaultName = 'local:analysis:storage';

    protected function configure()
    {
        $this
            ->setDescription('Perform an analysis on the current storage')
            ->setHelp('Command to perform a storage analysis in the upload folder')
            ->addOption('instance', null, InputOption::VALUE_REQUIRED, 'Instance relative or absolute path')
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Output directory for analysis result, default to false which will print the result on the screen')
            ->addOption('timezone', null, InputOption::VALUE_OPTIONAL, 'Specify your timezone, otherwise default to Australia/Sydney timezone. See https://www.php.net/manual/en/timezones.php')
            ->addOption('detailed', null, InputOption::VALUE_OPTIONAL, 'Detailed mode, default to false')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Toothpaste\Toothpaste::resetStartTime();

        $dir = $input->getOption('dir');
        $timezone = $input->getOption('timezone');
        $detailed = $input->getOption('detailed');
        $instance = $input->getOption('instance');

        if (empty($instance)) {
            $output->writeln('Please provide the instance path. Check with --help for the correct syntax');
        } else {
            $path = Sugar\Instance::validate($instance, $output);

            if (!empty($path)) {
                $output->writeln('Entering ' . $path . '...');
                $output->writeln('Setting up instance...');
                Sugar\Instance::setup();

                $logic = new Sugar\Logic\AnalyseStorage($dir, $timezone, $detailed);
                $logic->setLogger($output);
                $logic->performStorageAnalysis();
            } else {
                $output->writeln($instance . ' does not contain a valid Sugar installation. Aborting...');
            }
        }
    }
}
