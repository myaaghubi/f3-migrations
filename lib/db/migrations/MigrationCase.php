<?php

/**
 * @package F3 Migrations, MigrationCase
 * @link http://github.com/myaaghubi/F3-Migrations Github
 * @author Mohammad Yaaghubi <m.yaaghubi.abc@gmail.com>
 * @copyright Copyright (c) 2024, Mohammad Yaaghubi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

class MigrationCase
{
    /**
     * this method will call on upgrade
     *
     * @param  object $f3
     * @param  object $db
     * @param  object $schema
     * @return bool
     */
    public function up($f3, $db, $schema)
    {
        // your cods here

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
    public function down($f3, $db, $schema)
    {
        // your cods here

        // return TRUE when the downgrade be successful 
        return true;
    }
}
