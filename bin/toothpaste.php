<?php

// Enrico Simonetti
// enricosimonetti.com

if (is_file($autoload = dirname(__FILE__) . '/../../../autoload.php')) {
    require $autoload;
}

use Toothpaste\Toothpaste;
use Symfony\Component\Console\Application;

echo Toothpaste::getSoftwareInfo() . PHP_EOL;

if (!Toothpaste::isLinux()) {
    echo 'This software runs exclusively under Linux, aborting.' . PHP_EOL;
    exit(1);
}

if (extension_loaded('xdebug')) {
    echo 'Xdebug is enabled on this system. It is highly recommended to disable Xdebug on PHP CLI before running this script. Xdebug will cause unwanted slowness.' . PHP_EOL;
}

$application = new Application(Toothpaste::getSoftwareName(), Toothpaste::getSoftwareVersionNumber());

$application->add(new \Toothpaste\Commands\RepairCommand());
$application->add(new \Toothpaste\Commands\MySQLOptimizeCommand());
$application->add(new \Toothpaste\Commands\MySQLOptimizeWithIndexManipulationCommand());
$application->add(new \Toothpaste\Commands\RecordCountCommand());
$application->add(new \Toothpaste\Commands\TeamSetCleanupCommand());
$application->add(new \Toothpaste\Commands\MassModuleRetrievalCommand());
$application->add(new \Toothpaste\Commands\GenerateMetadataCommand());
$application->add(new \Toothpaste\Commands\ExtractMetadataCommand());
$application->add(new \Toothpaste\Commands\MaintenanceOnCommand());
$application->add(new \Toothpaste\Commands\MaintenanceOffCommand());
$application->add(new \Toothpaste\Commands\RepairMissingTablesCommand());
$application->add(new \Toothpaste\Commands\FileSystemBenchmarkCommand());
$application->add(new \Toothpaste\Commands\SugarBPMFlowCleanupCommand());
$application->add(new \Toothpaste\Commands\CustomTableOrphansCleanupCommand());
$application->add(new \Toothpaste\Commands\RBVUsageCommand());
$application->add(new \Toothpaste\Commands\LogicHooksUsageCommand());
$application->add(new \Toothpaste\Commands\SugarBPMAnalysisCommand());
$application->add(new \Toothpaste\Commands\RestoreRecordCommand());
$application->add(new \Toothpaste\Commands\RestoreRecordSQLCommand());
$application->add(new \Toothpaste\Commands\AnalyseStorageCommand());


$application->run();

exit(0);
