<?php

// Huy Vu
// hvu@sugarcrm.com

namespace Toothpaste\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Toothpaste\Sugar;

class AnalyseRecordCommand extends Command
{
    protected static $defaultName = 'local:analysis:record';

    protected function configure()
    {
        $this
            ->setDescription('Analyse db storage of the largest tables')
            ->setHelp('Analyse db storage of the largest tables')
            ->addOption('instance', null, InputOption::VALUE_REQUIRED, 'Instance relative or absolute path')
            ->addOption('months', null, InputOption::VALUE_OPTIONAL, 'Breakdown of data by months')
            
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        \Toothpaste\Toothpaste::resetStartTime();

        $months = $input->getOption('months');
        $output->writeln('Analysing large tables ...');

        $instance = $input->getOption('instance');
        if (empty($instance)) {
            $output->writeln('Please provide the instance path. Check with --help for the correct syntax');
        } else {
            $path = Sugar\Instance::validate($instance, $output);

            if (!empty($path)) {
                $output->writeln('Entering ' . $path . '...');
                $output->writeln('Setting up instance...');
                Sugar\Instance::setup();
                $logic = new Sugar\Logic\AnalyseRecord($months);
                $logic->setLogger($output);
                $logic->performRecordAnalysis();
            } else {
                $output->writeln($instance . ' does not contain a valid Sugar installation. Aborting...');
            }
        }
    }
}
