# Toothpaste

*This tool is provided AS-IS and might have bugs. Please help create and provide bug fixes!*

## Description
CLI utility to analyse, optimise and provide additional functionality to your Sugar system. As it is a CLI only tool, it cannot execute from within Sugar Cloud.<br />
This tool allows the execution of various CLI actions including repair, useful ongoing maintenance, identification of possible problems and extracting data from a Sugar installation.

For more information see: https://github.com/esimonetti/toothpaste/blob/master/README.md

## What's New 

+ local:data:restore-record-query 

Restore a soft-deleted record (if present) and most of its relationships from a backup database.

A slave db config is to be added as per https://support.sugarcrm.com/Documentation/Sugar_Versions/9.2/Serve/Administration_Guide/Advanced_Configuration_Options/index.html#Configuring_a_Slave_Database

For example: 

```
$sugar_config['db']['hvu-920ent3-backup'] = array(
'db_host_name' => <db_host_name>,
'db_user_name' => <db_user_name>,
'db_password' => <db_password>,
'db_name' => <db_name>,
'db_type' => 'mysql',
'db_manager' => 'MysqliManager'
);
```

### Usage:

```
./vendor/bin/toothpaste local:data:restore-record-query --instance=/var/www/html --module=Accounts --record=635be41c-0d9c-11ea-b1c6-0242ac120006 --db_backup=hvu920ent-backup
```

+ local:analysis:storage

Perform an analysis on the current storage (in the 'upload' folder)

### Usage

Debug mode for terminal output only (obmit debug option to save into a file)
```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html --debug=1‍‍
```

Set timezone for the timestamp of the date modified of the file

```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html --timezone=America/Los_Angeles‍‍ 

```