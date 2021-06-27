# F3-Migrations
F3-Migrations is a database helper plugin for the [Fat-Free Framework](http://github.com/bcosca/fatfree).
It's something like version control for the sql databases. Every time you have to make some changes manually in your database, you can make a `MigrationCase`, and the plugin will handle that.


- [F3-Migrations](#f3-migrations)
  - [Installation](#installation)
  - [Operation and basic usage](#operation-and-basic-usage)
    - [Instantiate](#instantiate)
    - [First migration](#first-migration)
    - [Config](#config)
    - [Logging](#logging)
    - [CLI mode](#cli-mode)
  - [Upgrade](#upgrade)
  - [License](#license)

## Installation

If you use composer, run the below code:

```
composer require myaghobi/f3-migrations
```
For manual installation:
1. Copy the content of `lib/` folder into your `lib/` folder. 
2. Install [Schema Builder](https://github.com/ikkez/f3-schema-builder) as mentioned in its documentation.
3. Install [Html2Text](https://github.com/mtibben/html2text), by placing the `html2text.php` inside of a folder named `html2text` in your `lib/`.


## Operation and basic usage

The plugin provides a simple web interface, consists of 4 routes that will auto add to your app:

* `GET /migrations` displays the web interface
* `GET /migrations/@action` triggers an action
* `GET /migrations/@action/@target` specific target version for the action
* `GET /migrations/theme/@type/@file` to retrive css/js files if you have stored the UI dir in non-web-accessible path

Also, it will create a table in your database named `migrations` to handle migrations.

### Instantiate

Instantiate the `Migrations` class before `f3->run()`. The plugin works if `DEBUG>=3`, otherwise, it goes disable because of security issues and to get no resource usage. 
To work with `Migrations` you need an active SQL connection:

```php
// require('vendor/autoload.php');
// $f3=Base::instance();
$f3=require('lib/base.php');
...
// Acording to f3-schema-builder
// MySQL, SQLite, PostgreSQL & SQL Server are supported
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname='.$DBName, $user, $pass);
...
\DB\MIGRATIONS\Migrations::instance($db);
$f3->run();
```

### First migration

1. Make sure the path of your cases directory be exists and secure.
2. Call `yourAppPublicUrl/migrations` in browser. 
3. Use `makecase` action to make your first migration case.
4. Call `migrate` action.


### Config
This plugin is configurable via config file:
``` ini
[migrations]
ENABLE=true
; PATH relative to `index.php`
PATH=../migrations
SHOW_BY_VERSOIN=true
CASE_PREFIX=migration_case_
LOG=true
```
The above config is the default, you can ignore/remove each one you don't need to change.

### Logging

You can find the logs of actions in `migrations.log` located in the [LOGS](http://fatfreeframework.com/quick-reference#LOGS) folder.

### CLI mode

Just run the below code:
```
php index.php /migrations
```

## Upgrade

There is no auto-upgrade mode yet to upgrade from older version, so you need to do it manually. You can recreate the migration cases or for each case:

1. Change case file name to migration_case_{file_name_and_or_versin_number}_{timestamp}.php. 
2. Change case content to be extend of `\DB\MIGRATIONS\MigrationCase` instead of `\DB\SQL\MigrationCase`. 

Finally call `refresh` action.

## License

You are allowed to use this plugin under the terms of the GNU General Public License version 3 or later.

Copyright (C) 2021 Mohammad Yaghobi