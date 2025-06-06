<?php

/**
 * @package F3 Migrations, MigrationCase
 * @link http://github.com/myaaghubi/F3-Migrations Github
 * @author Mohammad Yaaghubi <m.yaaghubi.abc@gmail.com>
 * @copyright Copyright (c) 2025, Mohammad Yaaghubi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

class MigrationCase extends \stdClass
{
    public static string $tableName = '';

    /**
     * this method will call on upgrade
     *
     * @param  object $f3
     * @param  object $db
     * @param  object $schema
     * @return bool
     */
    public static function up($f3, $db, $schema)
    {
        // your cods here
        // e.g. https://github.com/ikkez/f3-schema-builder#create-tables
        // $table = $schema->createTable(self::$tableName);
        // $table->addColumn('title')->type($schema::DT_VARCHAR128);
        // $table->addColumn('description')->type($schema::DT_TEXT);
        // $table->build();

        // return TRUE when the upgrade be successful
        return true;
    }


    /**
     * this method will call on upgrade
     *
     * @param  object $f3
     * @param  object $db
     * @param  object $schema
     * @return bool
     */
    public static function down($f3, $db, $schema)
    {
        // your cods here
        // $schema->dropTable(self::$tableName);

        // return TRUE when the downgrade be successful 
        return true;
    }
}
