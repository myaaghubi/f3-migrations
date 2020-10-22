# F3-Migrations
F3-Migrations is a database helper plugin for the [Fat-Free Framework](http://github.com/bcosca/fatfree).
It's something like version control for the sql databases. Every time you have to make some changes manually in your database, you can make a `MigrationCase` class in the `migrations` directory, and the `Migrations` will handle that.


- [F3-Migrations](#f3-migrations)
  - [Installation](#installation)
  - [Operation and basic usage](#operation-and-basic-usage)
    - [Instantiate](#instantiate)
    - [First migration case](#first-migration-case)
    - [Migrate](#migrate)
  - [Migration cases](#migration-cases)
    - [Filename](#filename)
    - [Content](#content)
  - [Logging](#logging)
  - [Web interface](#web-interface)
  - [License](#license)

## Installation

1- Copy the content of `lib/` folder into your `lib/` folder.

2- `Migrations` use [Schema Builder](https://github.com/ikkez/f3-schema-builder), so you need to install it too.


## Operation and basic usage

The plugin provides a simple web interface. The interface consists of 3 routes that will auto add to your app:

* `GET /migrations` displays the web interface
* `GET /migrations/result` shows the result
* `GET /migrations/@action` triggers an action

Also, it will create a table in your database and a folder in `lib/db/` names `migrations` to handle migrations.

### Instantiate

Instantiate the `Migrations` class before `f3->run()`. The plugin works if `DEBUG>=3`, otherwise, it goes disable to get no resource usage. Also to work with `Migrations` you need an active SQL connection:

```php
$f3=require('lib/base.php');
...
// Acording to f3-schema-builder
// MySQL, SQLite, PostgreSQL & SQL Server are supported
$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname='.$DBName, $user, $pass);
...
Migrations::instance($db);
$f3->run();
```

### First migration case

Make your first migration case by creating a file named `migration_case_1.0.0.php` in `lib/db/migrations` contains a class extended of `\db\sql\MigrationCase`.

### Migrate

Call `yourAppPublicUrl/migrations` in browser and use `migrate`.


## Migration cases

### Filename

The version of files is a positive(non-zero) and not duplicated number with a max length of 14, such as a timestamp or version number(separated with dots).
Also, you can add your description after the vesrion `migration_case_{version}_{description}.php`.

Some correct examples:
```
migration_case_1.0.0.php
migration_case_1.0.0_producst_table.php
migration_case_1603283078427_producst_table2.php
```
Some incorrect examples:
```
migration_producst_table_1.0.0.php
migration_case_1_0_0.php
migration_case_1.0 0.php
migration_case1.0.0.php
```

### Content

An example of the content for a migration case:
```php
<?php
// the class name can be duplicate
class CreateProductsTable extends \db\sql\MigrationCase {
    // this method will call on upgrade
    public function up($f3, $db, $schema) {
        // your cods here
        // https://github.com/ikkez/f3-schema-builder#create-tables
        $table = $schema->createTable('products');
        $table->addColumn('title')->type($schema::DT_VARCHAR128);
        $table->addColumn('description')->type($schema::DT_TEXT);
        $table->build();

        // return TRUE when the upgrade be successful
        return true;
    }

    // this method will call on downgrade
    public function down($f3, $db, $schema) {
        // your cods here
        $schema->dropTable('products');

        // return TRUE when the downgrade be successful 
        return true;
    }
}
?>
```

## Logging

You can find the logs of actions in `migrations.log` located in the [LOGS](http://fatfreeframework.com/quick-reference#LOGS) folder.

## Web interface

Web routes are available in `DEBUG>=3`, which means on release mode an HTTP request to routes throws a 404 error.

## License

You are allowed to use this plugin under the terms of the GNU General Public License version 3 or later.

Copyright (C) 2020 Mohammad Yaghobi