<?php

/**
 * @package F3 Migrations
 * @version 1.0.0
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2020, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\SQL;

class Migrations extends \Prefab {
  private $version = '1.0.0';
  private $enable;
  private $db;
  private $path;
  private $classPrefix = 'db_migrations_case';
  private $model;

    
  /**
   * Migrations constructor
   *
   * @param  object $db
   * @return void
   */
  function __construct(\DB\SQL $db) {
    $this->enable = false;

    $f3 = \Base::instance();
    if ($f3->get('DEBUG') >= 3) {
      $this->enable = true;
    }

    if (!$this->enable) {
      return;
    }

    $this->db = $db;

    if (!file_exists($this->path) || !is_dir($this->path)) {
      $cursor = new \ReflectionClass('db\cursor');
      $this->path = dirname($cursor->getFileName(), 1) . DIRECTORY_SEPARATOR . 'migrations';
      if (!file_exists($this->path)) {
        mkdir($this->path);
      }
    }

    $this->model = new \db\sql\MigrationsModel($db);

    $f3->route('GET /migrations', 'db\sql\Migrations->showHome');
    $f3->route('GET /migrations/result', 'db\sql\Migrations->showResult');
    $f3->route('GET /migrations/@action', 'db\sql\Migrations->doIt');
  }

  
  /**
   * show home screen
   *
   * @param  object $f3
   * @return void
   */
  function showHome($f3) {
    $actions = array(
      "Migrate" => "Use <code>migrate</code> to run all of the migrations you have created.",
      "Refresh" => "The <code>refresh</code> action will roll back all of your migrations and then run the <code>migrate</code>.",
      "Fresh" => "The <code>fresh</code> action will drop all tables from the database and then execute the <code>migrate</code>.",
      "Reset" => "The <code>reset</code> action will roll back all of migrations.",
      "Retry" => "The <code>retry</code> action will try to fix last failed action.",
    );

    $list = '';
    $base = $f3->get('BASE');
    foreach ($actions as $action => $desc) {
      $list .= "<dt><b>$action</b> (<a title='do it' href='$base/migrations/" . strtolower($action) . "'>" . strtolower($action) . "</a>)</dt>";
      $list .= "<dd>$desc</dd>";
    }
    $list = '<dl> ' . $list . ' </dl>';

    print $this->serve("Migrations", $list);
  }

    
  /**
   * show logs of the last action
   *
   * @param  object $f3
   * @return void
   */
  function showResult($f3) {
    if (empty($f3->get('SESSION.migrations.result'))) {
      $f3->reroute('/migrations');
    }
    $result = $f3->get('SESSION.migrations.result');
    
    $extra = '<br><a href="'.$f3->get('BASE').'/migrations">Go Back</a>';
    $content = implode('<br>', $result);
    if (strpos($content, 'failed')!==false) {
      print $this->serve('Result', null, $content, null, $extra);
    } else {
      print $this->serve('Result', null, null, $content, $extra);
    }
  }

  
  /**
   * do the requested action
   *
   * @param  object $f3
   * @return void
   */
  function doIt($f3) {
    $action = $f3->get('PARAMS.action');
    $f3->clear('SESSION.migrations.result');
    Migrations::logIt("Action: <b>$action</b>");

    $incomplete = $this->model->incompleteCases(1, true);
    if ($this->model->status<0) {
      $incomplete = $this->model->incompleteCases(1, false);
    }
    if (count($incomplete)>0 && $action!='retry' && $action!='fresh') {
      Migrations::logIt("You have a failed action! fix it and use <code>retry</code>.");
      Migrations::logIt("Details:");
      $version = $incomplete[0]->version;
      $stepId = $incomplete[0]->stepId;
      $datetime = date('Y-m-d H:i:s', $this->model->created_at);

      $status = 'error';
      if ($this->model->status>0) {
        $status = 'up()';
      } else if ($this->model->status<0) {
        $status = 'down()';
      }
      Migrations::logIt("version: <b>$version</b>, status: <b>$status</b>, stepId: <b>$stepId</b>, DateTime: <b>$datetime</b>");

      $f3->reroute('/migrations/result');
    }

    switch ($action) {
      case 'migrate':
        $this->migrate($f3);
        break;
      case 'refresh':
        $this->refresh($f3);
        break;
      case 'fresh':
        $this->fresh($f3);
        break;
      case 'reset':
        $this->reset($f3);
        break;
      case 'retry':
        $this->retry($f3);
        break;
      default:
        Migrations::logIt("Wrong Action!");
    }

    $f3->reroute('/migrations/result');
  }
  

  /**
   * upgrade to a higher version if exists
   *
   * @param  object $f3
   * @return void
   */
  function migrate($f3) {
    if ($this->upgrade()) {
      $this->applyCases($this->db);
    }
  }

    
  /**
   * rollback all upgrades and migrate again
   *
   * @param  object $f3
   * @return void
   */
  function refresh($f3) {
    if ($this->downgrade()) {
      $this->applyCases($this->db);
    }
    if ($this->upgrade()) {
      $this->applyCases($this->db);
    }
  }

    
  /**
   * drop all tables in the database and migrate again
   *
   * @param  object $f3
   * @return void
   */
  function fresh($f3) {
    $this->model->dropAll();
    $this->model->createTable();
    $this->upgrade();
    $this->applyCases($this->db);
  }

    
  /**
   * just rollback the migrations
   *
   * @param  object $f3
   * @return void
   */
  function reset($f3) {
    if ($this->downgrade()) {
      $this->applyCases($this->db);
    }
  }

    
  /**
   * retry on failed/incomplete cases registered in the migrations table
   *
   * @param  object $f3
   * @return void
   */
  function retry($f3) {
    if (!$this->model->failedCaseExists()) {
      Migrations::logIt("There is nothing to fix!");
      return;
    }
    $this->applyCases($this->db);
  }

  
  /**
   * register some cases(records) in the migrations table 
   *
   * @return bool
   */
  function upgrade() {
    $cases = $this->model->findCases(1, true);
    // current version of database stored from last migrations
    $versionCurrent = isset($cases[0]->version) ?$cases[0]->version: 0;

    $classes = $this->getClasses();
    
    if (count($classes)==0) {
      Migrations::logIt("There is nothing to do!");
      return false;
    }

    // get highest version of class if target version is not specified
    // sort array by version:desc
    uksort($classes, function ($a, $b) {
      return version_compare($a, $b);
    });
    $versionTarget = array_key_last($classes);

    // do we need to upgrade(1) or downgrade(-1) or just nothing to do
    $status = version_compare($versionTarget, $versionCurrent);

    if ($status == 0) {
      // there is nothing to do
      Migrations::logIt("Already migrated to <b>$versionTarget</b>!");
      return false;
    }

    $stepId = uniqid();
    foreach ($classes as $key=>$class) {
      $statusToFile = version_compare($versionTarget, $key);
      $statusToCurrent = version_compare($key, $versionCurrent);

      // all of migrations with higher version than current version of db
      if ($statusToCurrent == 1 && $statusToFile >= 0) {
        $this->model->addCase($key, 1, $stepId);
      }
    }
    return true;
  }

  
  /**
   * register some cases(records) in the migrations table 
   *
   * @return bool
   */
  function downgrade() {
    // load all migrated to rollback 
    $cases = $this->model->findCases(0, false);
    if (count($cases)==0) {
      Migrations::logIt("No any migration case exists to make rollback/reset!");
      return false;
    }
    
    $stepId = uniqid();
    foreach ($cases as $case) {
      $this->model->addCase($case->version, -1, $stepId);
    }
    return true;
  }

  
  /**
   * apply the cases registered in the migrations table
   *
   * @param  object $db
   * @return void
   */
  function applyCases($db) {
    // load all migrations need
    $incompletes = $this->model->incompleteCases(0, false);
    if ($this->model->status<0) {
      $incompletes = $this->model->incompleteCases(0, true);
    }
    $status = $incompletes[0]->status;

    $f3 = \Base::instance();
    
    // array of class as version => class
    $classes = $this->getClasses();

    // just triming the versions we don't need to
    foreach($incompletes as $incomplete){
      if (!isset($classes[$incomplete->version])) {
        Migrations::logIt("The file associated with version $incomplete->version is missing!");
        return;
      }
      $class = $classes[$incomplete->version];
      $methodName = $this->classPrefix . '_' . $this->getSafeVersionNumber($incomplete->version);

      try {
        eval(" \$this->$methodName=" . str_replace(['<?php', '?>'], '', $class));

        $schema = new \db\sql\Schema($db);
        if ($status>0) {
          $result = @$this->$methodName->up($f3, $this->db, $schema);
          Migrations::logIt("Upgrade to <b>$incomplete->version</b>: <b>" . ($result ? 'done' : 'failed')."</b>");
        } else if ($status<0) {
          $result = @$this->$methodName->down($f3, $this->db, $schema);
          Migrations::logIt("Downgrade from <b>$incomplete->version</b>: <b>" . ($result ? 'done' : 'failed')."</b>");
        }

        if (!$result) {
          break;
        }
        $incomplete->updateCase($incomplete->id, $status, $result);
      } catch (\ParseError $e) {
        Migrations::logIt("Migrations Error! ".$e->getMessage());
      }

      $incomplete->next();
    }
  }

    
  /**
   * get the files/classes in /migrations directory as $version=>$class
   *
   * @return array<string,string>
   */
  function getClasses() {
    $classes = array();
    if (file_exists($this->path) && is_dir($this->path)) {
      $directoryIterator = new \RecursiveDirectoryIterator($this->path);
      $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);
      $fileList = new \RegexIterator($iteratorIterator, '/migrations_((.*?)).php/');
      foreach ($fileList as $file) {
        $classVersion = $this->getFileVersionNumber($file);

        $fileContent = file_get_contents($file);

        if (preg_match('/class\s+(\w+)\s+extends/', $fileContent, $matches)) {
          $str = str_replace($matches[0], 'new class() extends', $fileContent) . ';';
          $classes[$classVersion] = $str;
        }
      }
    }
    return $classes;
  }


  /**
   * just convert dots to underscores
   *
   * @param  string $versionNumber
   * @return string
   */
  function getSafeVersionNumber($versionNumber) {
    return str_replace('.', '_', $versionNumber);
  }

    
  /**
   * get the version number, the version number could be a timestamp
   *
   * @param  string $path
   * @return string
   */
  function getFileVersionNumber($path) {
    $fileName = pathinfo($path)['filename'];
    $version = 0;
    preg_match('/\d+((\.\d+)*)?/', $fileName, $matches);
    if ($matches) {
      $version = $matches[0];
    }

    // return $this->getSafeVersionNumber($version);
    return $version;
  }

    
  /**
   * log the message
   *
   * @param  string $message
   * @param  bool $addToResult
   * @return void
   */
  static function logIt($message) {
    $logger = new \Log('migrations.log');
    $logger->write($message);

    $f3 = \Base::instance();
    if (empty($f3->get('SESSION.migrations.result'))) {
      $f3->set('SESSION.migrations.result', array());
    }
    $f3->push('SESSION.migrations.result', $message);
  }
  

  /**
   * make the output
   *
   * @param  string $title
   * @param  string $info
   * @param  string $error
   * @param  string $success
   * @param  string $extra
   * @return string
   */
  function serve($title, $info=null, $error=null, $success=null, $extra=null) {
    return '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>'.$title.'</title>
        <style>
        body {background: #f5f5f5;margin: 1em;}
        dt {margin-top: 0.7em;}
        dd {margin: 0;padding: 5px 0;}
        code {background: #cfcfcf;padding: 2px 5px;border-radius:3px;}
        .footer a {color:#000000;text-decoration:none}
        .error, .success {background: #ff8888;padding: 5px;border-radius:5px;}
        .success {background: #66cc66;}
        .error:empty, .success:empty{display:none}
        </style>
    </head>
    <body>
    <div class="row">
      <h1>'.$title.'</h1> 
      <div class="error">' . $error . '</div>
      <div class="success">' . $success . '</div>
      <div class="info">'.$info.'</div>
      '.$extra.'
      <hr>
      <div class="footer"><a href="https://github.com/myaghobi/f3-migrations">F3-Migrations '.$this->version.'</a></div>
    </div>
    </body>
    </html>
    ';
  }
}


/**
 * a model of migrations table
 */
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
    $this->schema = new \db\sql\Schema($db);
    $this->tableName = 'migrations';

    $this->createTable();

    parent::__construct($db, $this->tableName, null);
  }
  

  /**
   * add new case(record) in the migrations table
   *
   * @param  string|int $version
   * @param  int $status
   * @param  string $stepId
   * @return void
   */
  public function addCase($version, $status, $stepId) {
    $this->reset();
    $this->version = $version;
    $this->status = $status;
    $this->stepId = $stepId;
    $this->save();
  }

    
  /**
   * update the case(record)
   *
   * @param  int $id
   * @param  int $status
   * @param  bool $result
   * @return void
   */
  public function updateCase($id, $status, $result) {
    $this->load(array('id=?', $id));
    if ($status<0 and $result) {
      $this->erase(array('version=?', $this->version));
      return;
    }
    $this->result = $result;
    $this->save();
  }

    
  /**
   * find some cases(records)
   *
   * @param  int $limit
   * @param  bool $orderDesc
   * @return array<object>
   */
  public function findCases($limit = 0, $orderDesc = true) {
    $options = array("order" => "created_at, id");
    if ($orderDesc) {
      $options["order"] = "created_at desc, id desc";
    }
    if ($limit>0) {
      $options["limit"] = $limit;
    }
    return $this->find(null, $options);
  }

  
  /**
   * find incomplete cases(records)
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
    if ($limit>0) {
      $options["limit"] = $limit;
    }
    return $this->find(array('result=?', 0), $options);
  }

    
  /**
   * is any incomplete/failed case in the migrations table
   *
   * @return bool
   */
  public function failedCaseExists() {
    $this->incompleteCases(1);
    return count($this->incompleteCases(1))>0;
  }

    
  /**
   * drop all table of database
   *
   * @return void
   */
  public function dropAll() {
    Migrations::logIt("Drop all database tables.");
    $tables = $this->schema->getTables();
    foreach ($tables as $key => $table) {
      $this->schema->dropTable($table);
    }
  }

    
  /**
   * create the migrations table
   *
   * @return void
   */
  public function createTable() {
    $table = @$this->schema->createTable($this->tableName)->build();
    if ($table) {
      Migrations::logIt("Create the migrations table.");
      $table->addColumn('version')->type_varchar(14)->nullable(false)->defaults(0);
      $table->addColumn('status')->type($this->schema::DT_BOOL)->nullable(false)->defaults(0);
      $table->addColumn('result')->type($this->schema::DT_BOOL)->nullable(false)->defaults(0);
      $table->addColumn('stepId')->type_varchar(14)->nullable(false)->defaults(0);
      $table->addColumn('created_at')->type($this->schema::DT_DATETIME)->nullable(false)->defaults($this->schema::DF_CURRENT_TIMESTAMP);
      $table->primary('id');
      $table->build();
    }
  }
}


/**
 * MigrationsCase, the parent of migrations class
 */
class MigrationsCase {
  /**
   * this method calls to upgrade
   *
   * @param  object $f3
   * @param  object $db
   * @param  object $schema
   * @return bool
   */
  public function up($f3, $db, $schema) {
    // your cods here

    // return TRUE when the upgrade be successful
    return true;
  }

    
  /**
   * this method calls to downgrade
   *
   * @param  object $f3
   * @param  object $db
   * @param  object $schema
   * @return bool
   */
  public function down($f3, $db, $schema) {
    // your cods here

    // return TRUE when the downgrader be successful 
    return true;
  }
}
