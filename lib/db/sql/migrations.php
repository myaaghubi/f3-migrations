<?php

/**
 * @package F3 Migrations
 * @version 1.1.0
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2020, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\SQL;

class Migrations extends \Prefab {
  private $version = '1.1.0';
  private $db;
  private $path;
  private $classPrefix = 'migration_case';
  private $model;
  private $f3;


  /**
   * Migrations constructor
   *
   * @param  object $db
   * @return void
   */
  function __construct(\DB\SQL $db) {
    $this->f3 = \Base::instance();
    
    if ($this->f3->get('DEBUG') < 3) {
      return;
    }

    if ($this->f3->get('migrations.ENABLE')===false) {
      return;
    }

    $path = $this->f3->get('migrations.PATH')?:'db'.DIRECTORY_SEPARATOR.'migrations';
    // this line will help us to make a relative path
    $baseClass = new \ReflectionClass('Base');
    $this->path = dirname($baseClass->getFileName()) . DIRECTORY_SEPARATOR . $path;

    $this->db = $db;
    $this->model = new \db\sql\MigrationsModel($this->db);

    $this->f3->route('GET /migrations', 'db\sql\Migrations->showHome');
    $this->f3->route(array(
      'GET /migrations/@action', 
      'GET /migrations/@action/@target'
    ), 'db\sql\Migrations->doIt');
  }


  /**
   * show home screen
   *
   * @param  object $f3
   * @return void
   */
  function showHome($f3) {
    $versionCurrent = $this->currentDBVersion();

    $base = $f3->get('BASE');

    // going to make a list of available versions to upgrade
    $upgradeCases = $this->upgradeCases();
    foreach ($upgradeCases as &$case) {
      $case = "<a title='Migrate to $case' href='$base/migrations/migrate/$case'>$case</a>";
    }
    $upgradesList = implode(', ', $upgradeCases);

    // going to make a list of available versions to downgrade
    $downgradeCases = $this->downgradeCases();
    if (count($downgradeCases) > 0) {
      // each MigrationCase is responsible to upgrade to itself and downgrade from itself
      // for downgrade, if the target version is x we need to just downgrade all cases with higher version that x
      // so for downgrade, we have nothing to do with the case version x
      unset($downgradeCases[0]);
      $downgradeCases[] = 0;
    }
    foreach ($downgradeCases as &$case) {
      $case = "<a title='Rollback from $versionCurrent to $case' href='$base/migrations/rollback/$case'>$case</a>";
    }
    $downgradeList = implode(', ', $downgradeCases);


    $actions = array(
      "migrate" => array(
        "name" => "Migrate",
        "title" => "Migrate to highest case",
        "desc" => "Use <code>migrate</code> to run all of the migrations you have created.",
        "extra" => "Targets: " . ($upgradesList ?: 'none'),
      ),
      "rollback" => array(
        "name" => "Rollback",
        "title" => "Rollback all cases",
        "desc" => "Use <code>rollback</code> to get back on your migrations.",
        "extra" => "Targets: " . ($downgradeList ?: 'none'),
      ),
      "refresh" => array(
        "name" => "Refresh",
        "title" => "Rollback & Migrate",
        "desc" => "The <code>refresh</code> action will rollback all of your migrations and then run the <code>migrate</code>.",
        "extra" => "",
      ),
      "fresh" => array(
        "name" => "Fresh",
        "title" => "Drop all & Migrate",
        "desc" => "The <code>fresh</code> action will drop all tables from the database and then execute the <code>migrate</code>.",
        "extra" => "",
      ),
      "reset" => array(
        "name" => "Reset",
        "title" => "An alias of rollback",
        "desc" => "The <code>reset</code> action will rollback all of migrations.",
        "extra" => "",
      ),
      "retry" => array(
        "name" => "Retry",
        "title" => "Repeat last action",
        "desc" => "The <code>retry</code> action will try to fix last failed action.",
        "extra" => "",
      )
    );

    $list = '';
    foreach ($actions as $action => $array) {
      $list .= "<dt><b>$array[name]</b> (<a title='$array[title]' href='$base/migrations/$action'>$action</a>)</dt>";
      $list .= "<dd>$array[desc]<br><small>$array[extra]</small></dd>";
    }
    $list = "<dl>$list</dl><small>DB Version: $versionCurrent</small>";

    $error = !is_dir($this->path)?'The <code>PATH</code> is not valid!':'';
    $error = !file_exists($this->path)?'The <code>PATH</code> is not exists!':'';

    print $this->serve("Migrations", $list, $error);
  }


  /**
   * show logs of the last action
   *
   * @param  object $f3
   * @return void
   */
  function showResult($f3) {
    $log = $f3->get('lastLog');
    $logError = $f3->get('lastLogError');
    if (empty($log)) {
      $log = 'There is no log to show!';
    } else if (is_array($log)) {
      $log = implode('<br>', $log);
    }

    $extra = '<br><a href="' . $f3->get('BASE') . '/migrations">Go Back</a>';
    if ($logError) {
      print $this->serve('Result', null, $log, null, $extra);
    } else {
      print $this->serve('Result', null, null, $log, $extra);
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
    $target = $f3->get('PARAMS.target');
    Migrations::logIt("Action: <b>$action</b>", false, true);

    $incomplete = $this->model->incompleteCases(1, false);
    if (count($incomplete) > 0 && $action != 'retry' && $action != 'fresh') {
      Migrations::logIt("You have a failed case! fix it and use <code>retry</code>.", true);

      if ($incomplete[0]->status < 0) {
        $incomplete = $this->model->incompleteCases(1, false);
      }
      $version = $incomplete[0]->version;
      $stepId = $incomplete[0]->stepId;
      $datetime = date('Y-m-d H:i:s', $this->model->created_at);

      $method = 'error';
      if ($incomplete[0]->status > 0) {
        $method = 'up()';
      } else if ($incomplete[0]->status < 0) {
        $method = 'down()';
      }
      Migrations::logIt("Details => version: <b>$version</b>, method: <b>$method</b>, stepId: <b>$stepId</b>, DateTime: <b>$datetime</b>", true);

      $this->showResult($f3);
      return;
    }

    switch ($action) {
      case 'migrate':
        $this->migrate($f3, $target);
        break;
      case 'rollback':
        $this->rollback($f3, $target);
        break;
      case 'refresh':
        $this->refresh($f3);
        break;
      case 'fresh':
        $this->fresh($f3);
        break;
      case 'reset':
        $this->rollback($f3);
        break;
      case 'retry':
        $this->retry($f3);
        break;
      default:
        Migrations::logIt("Wrong Action!");
    }

    $this->showResult($f3);
  }


  /**
   * upgrade to a higher version
   *
   * @param  object $f3
   * @param  int $target
   * @return void
   */
  function migrate($f3, $target) {
    if ($this->upgrade($target)) {
      $this->applyCases();
    }
  }


  /**
   * downgrade to a lower version
   *
   * @param  object $f3
   * @param  int $target
   * @return void
   */
  function rollback($f3, $target=null) {
    if ($this->downgrade($target)) {
      $this->applyCases();
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
      $this->applyCases();
    }
    if ($this->upgrade()) {
      $this->applyCases();
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
    $this->applyCases();
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
    $this->applyCases();
  }


  /**
   * get current version of database according to last migration
   *
   * @return string
   */
  function currentDBVersion() {
    $lastCase = $this->model->findCases(1, true);
    $versionCurrent = isset($lastCase[0]->version) ? $lastCase[0]->version : '0';

    return $versionCurrent;
  }


  /**
   * register some cases(records) in the migrations table 
   *
   * @return bool
   */
  function upgrade($versionTarget = null) {
    $cases = $this->upgradeCases($versionTarget);

    if (count($cases) == 0) {
      // there is nothing to do
      Migrations::logIt($versionTarget?"Already migrated to <b>$versionTarget</b>!":"There is nothing to do.");
      return false;
    }

    $stepId = uniqid();
    foreach ($cases as $version) {
      // all of migrations with higher version than current version of db
      if (version_compare($versionTarget, $version) >= 0) {
        $this->model->addCase($version, 1, $stepId);
      }
    }
    return true;
  }


  /**
   * get available cases to upgrade
   *
   * @param  string $versionTarget
   * @return string[]
   */
  function upgradeCases(&$versionTarget = null) {
    $versionCurrent = $this->currentDBVersion();

    $versions = $this->getMigrationCases(true);
    // sort array by version
    usort($versions, function ($a, $b) {
      return version_compare($a, $b);
    });

    // get highest version if target version is not specified
    if ($versionTarget == null) {
      $versionTarget = end($versions);
    }

    $result = array();

    $status = version_compare($versionTarget, $versionCurrent);

    // do we need to upgrade
    if ($status <= 0) {
      return $result;
    }

    foreach ($versions as $version) {
      $statusToFile = version_compare($versionTarget, $version);
      $statusToCurrent = version_compare($version, $versionCurrent);

      // all higher versions than current version of db
      if ($statusToCurrent == 1 && $statusToFile >= 0) {
        $result[] = $version;
      }
    }
    return $result;
  }


  /**
   * register some cases(records) in the migrations table 
   *
   * @return bool
   */
  function downgrade($versionTarget = null) {
    // load all migrated to rollback 
    $cases = $this->downgradeCases($versionTarget);
    if (count($cases) == 0) {
      Migrations::logIt("No any migration case available to make rollback/reset!");
      return false;
    }


    $stepId = uniqid();
    foreach ($cases as $version) {
      $this->model->addCase($version, -1, $stepId);
    }
    return true;
  }


  /**
   * get available cases to downgrade
   *
   * @param  string $versionTarget
   * @return string[]
   */
  function downgradeCases(&$versionTarget = null) {
    $versionCurrent = $this->currentDBVersion();

    // get lowes version if target version is not specified
    if ($versionTarget == null) {
      $versionTarget = 0;
    }


    $status = version_compare($versionTarget, $versionCurrent);

    $result = array();
    // do we need to downgrade
    if ($status >= 0) {
      return $result;
    }

    $cases = $this->model->findCases(0, true);
    foreach ($cases as $case) {
      $statusToCase = version_compare($versionTarget, $case->version);
      $statusToCurrent = version_compare($case->version, $versionCurrent);

      // all lower versions than current version
      if ($statusToCurrent <= 0 && $statusToCase < 0) {
        $result[] = $case->version;
      }
    }
    return $result;
  }

  /**
   * apply the cases registered in the migrations table
   *
   * @param  object $db
   * @return void
   */
  function applyCases() {
    // load all incomplete/failed migrations need
    $incompletes = $this->model->incompleteCases(0, false);
    if (count($incompletes) == 0) {
      return;
    }
    $status = $incompletes[0]->status;

    // array of class as version => class
    $classes = $this->getMigrationCases();

    // just triming the versions we don't need to
    foreach ($incompletes as $incomplete) {
      $this->f3->get('benchmark')->checkPoint('loop');
      if (!isset($classes[$incomplete->version])) {
        Migrations::logIt("The file associated with the migration case version $incomplete->version is missing!", true);
        return;
      }
      $class = $classes[$incomplete->version];
      $methodName = $this->classPrefix . '_' . $this->getSafeVersionNumber($incomplete->version);
      
      eval(" \$this->$methodName=" . str_replace(['<?php', '?>'], '', $class));
      
      $schema = new \db\sql\Schema($this->db);
      if ($status > 0) {
        $result = @$this->$methodName->up($this->f3, $this->db, $schema);
      } else if ($status < 0) {
        $result = @$this->$methodName->down($this->f3, $this->db, $schema);
      }

      Migrations::logIt(($status>0?'Upgrade':'Downgrade')." from <b>$incomplete->version</b>: <b>" . ($result ? 'done' : 'failed') . '</b>', !$result);

      if (!$result) {
        break;
      }

      $this->f3->get('benchmark')->checkPoint('update');
      $this->model->updateCase($incomplete->id, $status, $result);
    }
  }


  /**
   * get the files/classes in /migrations directory as $version=>$class
   *
   * @return array<string,string>
   */
  function getMigrationCases($loadJustVersions = false) {
    $classes = array();
    if (file_exists($this->path) && is_dir($this->path)) {
      $directoryIterator = new \RecursiveDirectoryIterator($this->path);
      $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);
      $fileList = new \RegexIterator($iteratorIterator, '/migration_case_((.*?)).php/');
      foreach ($fileList as $file) {
        $classVersion = $this->getFileVersionNumber($file);
        if ($loadJustVersions) {
          $classes[] = $classVersion;
          continue;
        }

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
    preg_match('/migration_case_(\d+((\.\d+)*))?/', $fileName, $matches);
    if ($matches) {
      $version = $matches[1];
    }

    return $version;
  }


  /**
   * log the message
   *
   * @param  string $message
   * @param  bool $resetResult
   * @return void
   */
  static function logIt($message, $error=false, $resetResult = false) {
    $f3 = \Base::instance();
    if ($resetResult) {
      $f3->set('lastLog', array());
      $f3->set('lastLogError', false);
    }

    $f3->push('lastLog', $message);
    if ($error) {
      $f3->set('lastLogError', $error);
    }

    
    if ($f3->get('migrations.LOG')!==false) {
      $logger = new \Log('migrations.log');
      $logger->write($f3->scrub($message));
    }
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
  function serve($title, $info = null, $error = null, $success = null, $extra = null) {
    $body = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>' . $title . '</title>
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
      <h1>' . $title . '</h1> 
      <div class="error">' . $error . '</div>
      <div class="success">' . $success . '</div>
      <div class="info">' . $info . '</div>
      ' . $extra . '
      <hr>
      <div class="footer"><a href="https://github.com/myaghobi/f3-migrations">Fat-Free Migrations ' . $this->version . '</a></div>
    </div>
    </body>
    </html>
    ';
    return $body;
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
    if ($status < 0 and $result) {
      $this->erase(array('version=?', $this->version));
      return;
    }
    $this->result = $result;
    $this->update();
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
    if ($limit > 0) {
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
    if ($limit > 0) {
      $options["limit"] = $limit;
    }
    return @$this->find(array('result=?', 0), $options);
  }


  /**
   * is any incomplete/failed case in the migrations table
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
 * use as the parent of migration cases
 */
class MigrationCase {
  /**
   * this method will call on upgrade
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
   * this method will call on upgrade
   *
   * @param  object $f3
   * @param  object $db
   * @param  object $schema
   * @return bool
   */
  public function down($f3, $db, $schema) {
    // your cods here

    // return TRUE when the downgrade be successful 
    return true;
  }
}
