<?php

/**
 * @package F3 Migrations, MigrationCaseItem
 * @link http://github.com/myaaghubi/F3-Migrations Github
 * @author Mohammad Yaaghubi <m.yaaghubi.abc@gmail.com>
 * @copyright Copyright (c) 2025, Mohammad Yaaghubi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

// Class names can be duplicated
class MigrationCaseSample extends \DB\MIGRATIONS\MigrationCase
{
    public static string $tableName = '%table_name%';

    // this method will call on upgrade
    public static function up($f3, $db, $schema)
    {
        // your cods here

        // e.g. https://github.com/ikkez/f3-schema-builder#create-tables
        $table = $schema->createTable(self::$tableName);
        // $table->addColumn('title')->type($schema::DT_VARCHAR128);
        // $table->addColumn('description')->type($schema::DT_TEXT);
        $table->build();

        // or 
        // $db->exec("
        // CREATE TABLE :tableName (
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     title VARCHAR(100),
        //     description VARCHAR(100),
        //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        //     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        // );
        // ", ['tableName' => $this->tableName]);

        // return TRUE when the upgrade be successful
        return true;
    }

    // this method will call on downgrade
    public static function down($f3, $db, $schema)
    {
        // your cods here
        $schema->dropTable(self::$tableName);

        // return TRUE when the downgrade be successful 
        return true;
    }
}
