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

Assuming Sugar is located in `/var/www/html`

```
./vendor/bin/toothpaste local:data:restore-record-query --instance=/var/www/html --module=Accounts --record=635be41c-0d9c-11ea-b1c6-0242ac120006 --db_backup=hvu920ent-backup
```

+ local:analysis:storage

Perform an analysis on the current storage (in the 'upload' folder)

### Usage

Assuming Sugar is located in `/var/www/html`

Most basic form:
```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html
```

Output
```
SUMMARY
+------+-------+--------+-----+------+------+-------+---------+
| YEAR | NOTES | EMAILS | KBS | DOCS | PICS | TOTAL |  SIZE   |
+------+-------+--------+-----+------+------+-------+---------+
| 2019 |   3   |   3    |  1  |  2   |  8   |  17   | 1.31 MB |
| 2015 |   1   |   0    |  0  |  0   |  0   |   1   | 43.1 KB |
+------+-------+--------+-----+------+------+-------+---------+
```

Detailed mode for the list of files organised by Year-Month-Day
```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html --detailed=1‍‍
```

Save to disk
```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html --dir=.
```

Set timezone for the timestamp of the date modified of the file

```
./vendor/bin/toothpaste local:analysis:storage --instance=/var/www/html --timezone=America/Los_Angeles‍‍ 
```

+ local:analysis:record

Assuming Sugar is located in `/var/www/html`

Most basic form:
```
./vendor/bin/toothpaste local:analysis:record --instance=/var/www/html
```

Output (default to last 6 months)
```
10 biggest tables (approx.):
+-----------------------+---------+-----------+------------+------------+------------------+
|        TABLES         |  ROWS   | DATA_SIZE | INDEX_SIZE | TOTAL_SIZE | INDEX_DATA_RATIO |
+-----------------------+---------+-----------+------------+------------+------------------+
| emails_text           | 0.07Mil | 1.17G     | 0.01G      | 1.18G      | 0.01             |
| notes                 | 0.18Mil | 0.17G     | 0.26G      | 0.43G      | 1.58             |
| pmse_bpm_flow         | 0.18Mil | 0.07G     | 0.33G      | 0.40G      | 4.69             |
| leads_audit           | 0.39Mil | 0.08G     | 0.24G      | 0.33G      | 2.98             |
| calls                 | 0.09Mil | 0.04G     | 0.12G      | 0.17G      | 2.79             |
| emails_email_addr_rel | 0.22Mil | 0.03G     | 0.11G      | 0.14G      | 3.54             |
| emails                | 0.10Mil | 0.04G     | 0.08G      | 0.12G      | 1.93             |
| activities_users      | 0.21Mil | 0.03G     | 0.09G      | 0.12G      | 2.64             |
| activities            | 0.10Mil | 0.05G     | 0.05G      | 0.10G      | 0.96             |
| tasks                 | 0.04Mil | 0.03G     | 0.07G      | 0.10G      | 2.51             |
+-----------------------+---------+-----------+------------+------------+------------------+

Record Count in 2019-11
+-----------------------+-------+----------+
|         TABLE         | COUNT | DB TOTAL |
+-----------------------+-------+----------+
| emails_text           | n/a   | 106742   |
| notes                 | 1507  | 175823   |
| pmse_bpm_flow         | 74840 | 183927   |
| leads_audit           | 8423  | 391023   |
| calls                 | 3473  | 95728    |
| emails_email_addr_rel | 8301  | 223908   |
| emails                | 3227  | 106735   |
| activities_users      | 87495 | 214900   |
| activities            | 40514 | 101826   |
| tasks                 | 1535  | 45073    |
+-----------------------+-------+----------+

etc.
```

Option to show x number of months
```
./vendor/bin/toothpaste local:analysis:record --instance=/var/www/html --months=7
```