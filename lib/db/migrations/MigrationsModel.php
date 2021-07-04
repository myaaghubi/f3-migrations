<?php

/**
 * @package F3 Migrations, MigrationsModel
 * @version 2.0.1
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2021, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

class MigrationsModel extends \DB\SQL\Mapper {
  private $schema;
  private $tableName;

  /**
   * Migrations constructor
   *
   * @param  object $db
   * @return void
   */
  public function __construct(\DB\SQL $db) {
    $this->tableName = 'migrations';

    if (!class_exists('\DB\SQL\Schema')) {
      print '\DB\SQL\Schema is missing!';
      return;
    }
    $this->schema = new \DB\SQL\Schema($db);

    $this->createTable();

    $this->upgradeTable();

    parent::__construct($db, $this->tableName, null);
  }


  /**
   * get current timestamp of database according to last succesful migration
   *
   * @return string
   */
  function timestamp() {
    $options = array(
      'order' => 'created_at desc, id desc',
      'limit' => 1
    );

    $lastCase = $this->find(array('result>?', 0), $options);

    return $lastCase[0]->timestamp ? : '0';
  }


  /**
   * get last succesful registered case
   *
   * @return string
   */
  function getLastCase() {
    $options = array(
      'order' => 'created_at desc, id desc',
      'limit' => 1
    );

    $lastCase = $this->find(array('result>?', 0), $options);

    return $lastCase[0];
  }


  /**
   * add a new case(record) in the migrations table
   * status > -1: downgrade, 1: upgrade
   *
   * @param  string|int $timestamp
   * @param  string $name
   * @param  int $status
   * @param  string $stepId
   * @return void
   */
  public function addCase($timestamp, $name, $status, $stepId) {
    $this->reset();
    $this->timestamp = $timestamp;
    $this->name = $name;
    $this->status = $status;
    $this->step_id = $stepId;
    $this->save();
  }


  /**
   * update a case(record)
   *
   * @param  int $id
   * @param  int $status
   * @param  bool $result
   * @return void
   */
  public function updateCase($id, $status, $result) {
    $this->load(array('id=?', $id));
    if ($status < 0 and $result) {
      $this->erase(array('timestamp=?', $this->timestamp));
      return;
    }
    $this->result = $result;
    $this->update();
  }


  /**
   * find specific registered case(record) by timestamp
   *
   * @param  int $timestamp
   * @return array<object>
   */
  public function findCase($timestamp = null) {
    $options["limit"] = 1;
    return $this->findone(array("timestamp=?", $timestamp), $options);
  }


  /**
   * find all registered cases(records)
   *
   * @param  int $limit
   * @param  bool $orderDesc
   * @return array<object>
   */
  public function findCases($limit = 0, $orderDesc = true) {
    $options["order"] = "created_at desc, id desc";
    if (!$orderDesc) {
      $options = array("order" => "created_at, id");
    }
    if ($limit > 0) {
      $options["limit"] = $limit;
    }
    return $this->find(null, $options);
  }


  /**
   * find the last registered case(record)
   *
   * @return string
   */
  public function findLastCase() {
    $options["order"] = "created_at desc, id desc";
    $options["limit"] = 1;
    return $this->findone(null, $options);
  }


  /**
   * find all registered cases(records) by specified stepId
   *
   * @param  string $stepId
   * @param  int $limit
   * @param  bool $orderDesc
   * @return array<object>
   */
  public function findCasesByStepId($stepId, $limit=0, $orderDesc = true) {
    $options = array("order" => "created_at, id");
    if ($orderDesc) {
      $options["order"] = "created_at desc, id desc";
    }
    if ($limit > 0) {
      $options["limit"] = $limit;
    }
    return $this->find(array("stepId=?", $stepId), $options);
  }


  /**
   * find incomplete registered cases(records)
   *
   * @param  int $limit
   * @param  bool $orderDesc
   * @return array<object>
   */
  public function incompleteCases($limit = 0, $orderDesc = true) {
    $options = array("order" => "created_at, id");
    if ($orderDesc) {
      $options["order"] = "created_at desc, id desc";
    }
    if ($limit > 0) {
      $options["limit"] = $limit;
    }
    return @$this->find(array('result=?', 0), $options);
  }


  /**
   * is any incomplete/failed registered case in the migrations table
   *
   * @return bool
   */
  public function failedCaseExists() {
    return count($this->incompleteCases(1)) > 0;
  }


  /**
   * drop all table of database
   *
   * @return void
   */
  public function dropAll() {
    $tables = $this->schema->getTables();
    foreach ($tables as $key => $table) {
      $this->schema->dropTable($table);
    }
    Migrations::logIt("All tables dropped.");
  }


  /**
   * create the migrations table
   *
   * @return void
   */
  public function createTable() {
    $table = @$this->schema->createTable($this->tableName)->build();

    if ($table) {
      $table->addColumn('timestamp')->type_varchar(14)->nullable(false)->defaults(0);
      $table->addColumn('name')->type_varchar(100)->nullable(true);
      $table->addColumn('status')->type($this->schema::DT_BOOL)->nullable(false)->defaults(0);
      $table->addColumn('result')->type($this->schema::DT_BOOL)->nullable(false)->defaults(0);
      $table->addColumn('step_id')->type_varchar(14)->nullable(false)->defaults(0);
      $table->addColumn('created_at')->type($this->schema::DT_DATETIME)->nullable(false)->defaults($this->schema::DF_CURRENT_TIMESTAMP);
      $table->primary('id');
      $table->build();
      Migrations::logIt("Migrations table has been created.");
    }
  }


  /**
   * upgrade the migrations table
   *
   * @return void
   */
  public function upgradeTable() {
    $table = @$this->schema->alterTable($this->tableName);

    if ($table && in_array('version', $table->getCols())) {
      $table->renameColumn('version','timestamp');
      $table->renameColumn('stepId','step_id');
      $table->build();
      $table->addColumn('name')->type_varchar(100)->nullable(true)->after('timestamp');
      $table->build();
      Migrations::logIt("Migrations table has been upgraded.");
    }
  }
}

?>