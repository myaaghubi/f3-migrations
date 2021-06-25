<?php

/**
 * @package F3 Migrations, MigrationCaseSample
 * @version 2.0.0
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2020, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;
// the class name can be duplicate
class MigrationCaseSample extends \DB\MIGRATIONS\MigrationCase {
  // this method will call on upgrade
  public function up($f3, $db, $schema) {
    // your cods here

    // e.g. https://github.com/ikkez/f3-schema-builder#create-tables
    // $table = $schema->createTable('products');
    // $table->addColumn('title')->type($schema::DT_VARCHAR128);
    // $table->addColumn('description')->type($schema::DT_TEXT);
    // $table->build();

    // return TRUE when the upgrade be successful
    return true;
  }

  // this method will call on downgrade
  public function down($f3, $db, $schema) {
    // your cods here
    // $schema->dropTable('products');

    // return TRUE when the downgrade be successful 
    return true;
  }
}
?>