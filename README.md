# F3-Migrations

F3-Migrations is a database helper plugin for the [Fat-Free Framework](http://github.com/bcosca/fatfree).
It's something like version control for the sql databases. Every time you have to make some changes manually in your database, you can make a `MigrationCase`, and the plugin will handle that.

Tested on `php 8.4`, `fatfree 3.9`

- [F3-Migrations](#f3-migrations)
  - [Installation](#instantiation)
  - [Operation and basic usage](#operation-and-basic-usage)
    - [Instantiate](#instantiation)
    - [First migration](#first-migration)
    - [Config](#config)
    - [Logging](#logging)
    - [CLI mode](#cli-mode)
  - [Upgrade](#upgrade)
  - [License](#license)

## Installation

If you use composer, run the below code:

```
composer require `myaghobi/f3-migrations`
```

For manual installation:

1. Copy the content of `lib/` folder into your `lib/` folder.
2. Install [Schema Builder](https://github.com/ikkez/f3-schema-builder) as mentioned in its documentation.
3. Install [Html2Text](https://github.com/mtibben/html2text), by placing the `html2text.php` inside of a folder named `html2text` in your `lib/`.

## Operation and basic usage

The plugin provides a simple interface, consists of 4 routes that will auto add to the app:

- `GET /migrations` displays the web interface
- `GET /migrations/@action` triggers an action
- `GET /migrations/@action/@target` specific target version for the action
- `GET /migrations/theme/@type/@file` to retrive css/js files if you have stored the UI dir in non-web-accessible path (recommended)

Also, it will create a table in your database named `migrations` to handle migrations.

### Instantiation

Instantiate the `Migrations` class before calling `f3->run()`. The plugin operates if `DEBUG >= 3`; otherwise, it is disabled for security reasons and to minimize resource usage. 

To work with `Migrations`, you must have an active SQL connection.

```php
// require('vendor/autoload.php');
// $f3=Base::instance();
$f3=require('lib/base.php');
...
// Acording to f3-schema-builder
// MySQL, SQLite, PostgreSQL & SQL Server are supported
$db = new \DB\SQL("mysql:host=localhost;port=3306;dbname={$DBName}", $user, $pass);
...
\DB\MIGRATIONS\Migrations::instance($db);
$f3->run();
```

### First migration

1. *Verify Cases Directory:* Ensure that the path to your cases directory exists and is secure.
2. *Access Migrations(cli mode):* Run `php index.php /migrations`
2. *Access Migrations(web interface):* Open `yourAppPublicUrl/migrations` in your browser.
3. *Create Migration Case:* Use the `makecase` action to create your first migration case.
4. *Run Migration:* Execute the `migrate` action.

### Config

This plugin can be configured through its configuration file.

```ini
[migrations]
ENABLE=true
; By default it is enable for the DEBUG>=3
ENABLE_DEBUG_LEVEL=3
; PATH is relative to your index.php file
PATH=../app/migrations
SHOW_BY_VERSOIN=true
LOG=true
```

The configuration above is the default. You can ignore or remove any options that you don't need to modify.

### Logging

You can find the action logs in `migrations.log`, which is located in the [LOGS](http://fatfreeframework.com/quick-reference#LOGS) folder.

## Upgrade

1. _Update the Plugin:_ Use Composer or perform a manual update.
2. _Check Migration Path:_ Ensure that the path for migration cases is relative to `index.php` and that it exists.
3. _Backup:_ Create a backup of your database and migration cases.
4. _Run Upgrade Action:_ Execute the `upgrademc` action to update the old migration cases.
5. Finally execute the `fresh` action.

## License

You are allowed to use this plugin under the terms of the GNU General Public License version 3 or later.

Copyright (C) 2024 Mohammad Yaaghubi
