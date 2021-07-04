<?php

/**
 * @package F3 Migrations
 * @version 2.0.1
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2021, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

use DB\MIGRATIONS\MigrationsModel;
use DB\MIGRATIONS\MigrationCaseItem;
use DB\MIGRATIONS\MigrationCaseSample;

class Migrations extends \Prefab {
  public static $version = '2.0.1';
  public static $path;
  public static $log;
  private $dbTimestamp;
  private $showTargets;
  private $showByVersion;
  private $casePrefix;
  private $model;
  private $db;
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

    if ($this->f3->get('migrations.ENABLE') === false) {
      return;
    }

    self::$log = $this->f3->get('migrations.LOG') ?: true;

    // relative to index.php
    self::$path = dirname($this->f3->get('SERVER.SCRIPT_FILENAME')) . '/' . 
    ($this->f3->get('migrations.PATH') ?: '../migrations') 
    . '/';

    $this->showTargets = $this->f3->get('migrations.SHOW_TARGETS') ?: true;

    // show targets by version number if the version number mentioned in the name of migration cases
    $this->showByVersion = $this->f3->get('migrations.SHOW_BY_VERSOIN') ?: true;
    $this->f3->set('showByVersion', $this->showByVersion);

    $this->casePrefix = $this->f3->get('migrations.CASE_PREFIX') ?: 'migration_case_';

    $this->db = $db;

    $this->model = new MigrationsModel($this->db);
    $lastCase = $this->model->getLastCase();

    $this->f3->set('db_current_details', $lastCase->timestamp." ".$lastCase->name." ".$lastCase->created_at);

    $this->dbTimestamp = $lastCase->timestamp;

    $this->f3->set('version', $this->version);

    $this->checkUI();

    $this->initRoutes();
  }


  /**
   * copy the template from ui dir into your UI dir if not exists
   *
   * @return void
   */
  function checkUI() {
    if (!is_dir($this->f3->UI . 'migrations')) {
      $this->copyDir(dirname(__FILE__,1).'/ui', $this->f3->UI . '/' . 'migrations');
    }
  }


  /**
   * initializing routes
   *
   * @return void
   */
  function initRoutes() {
    $this->f3->route(array(
      'GET /migrations'
    ), 'DB\MIGRATIONS\Migrations->showHome');

    $this->f3->route(array(
      'GET /migrations/@action',
      'GET /migrations/@action/@target'
    ), 'DB\MIGRATIONS\Migrations->doIt');

    // this route will help if you have stored the UI dir in non-web-accessible path
    // the route works if plugin works (DEBUG>=3), so there is no security or performance concern
    $this->f3->route('GET /migrations/theme/@type/@file',
      function($f3, $args) {
        $web = \Web::instance();
        $file = $f3->UI.'migrations/theme/'.$args['type'].'/'.$args['file'];
        $mime = $web->mime($file);

        header('Content-Type: '.$mime);
        echo $f3->read($file);
      }
    );
  }



  /**
   * show home screen
   *
   * @param  object $f3
   * @return void
   */
  function showHome($f3) {
    $base = $f3->get('BASE');

    //show upgrade action from older version to current
    $upgradeAvailable = false;

    $items_ = $this->getOldMigrationCaseItems();
    if ($items_ && count($items_)>=0) {
      $upgradeAvailable = true;
    }

    // available migration items to upgrade
    $upgradeItems = $this->upgradeItems();

    // available migration case items to downgrade
    $downgradeItems = $this->downgradeItems();

    if (count($downgradeItems) > 0) {
      // each MigrationCase is responsible to upgrade to itself and downgrade from itself
      // if the timestamp is x we need to downgrade all cases with higher version that x
      // so for downgrade, we need to keep the x therefor there is nothing to do with the case x
      unset($downgradeItems[0]);

      $carryCase = new MigrationCaseItem();
      $carryCase->timestamp = 0;
      $downgradeItems[] = $carryCase;
    }


    $this->f3->set('path', self::$path);
    $this->f3->set('upgradeAvailable', $upgradeAvailable);
    $this->f3->set('upgradeItems', $upgradeItems);
    $this->f3->set('downgradeItems', $downgradeItems);
    $this->f3->set('dbTimestamp', $this->dbTimestamp);
    $this->f3->set('version', self::$version);

    $this->serve("home.htm");
  }


  /**
   * serve the output
   *
   * @param string $file
   * @return void
   */
  function serve($file) {    
    $template = \Template::instance()->render('migrations/'.$file);

    if (!$this->f3->get('CLI')) {
      print $template;
      return;
    }

    if (!class_exists('\Html2Text\Html2Text')) {
      print '\Html2Text\Html2Text is missing!';
      return;
    }

    $html2text = new \Html2Text\Html2Text($template, array('width'=>0));
    print $html2text->getText();
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
    self::logIt("Action: <b>$action</b> $target", false, true);

    $incomplete = $this->model->incompleteCases(1, false);
    if (count($incomplete) > 0 && $action != 'retry' && $action != 'fresh' && $action != 'make') {
      self::logIt("You have a failed case! fix it and use <code>retry</code>.", true);

      if ($incomplete[0]->status < 0) {
        $incomplete = $this->model->incompleteCases(1, false);
      }
      $timestamp = $incomplete[0]->timestamp;
      $stepId = $incomplete[0]->step_id;
      $datetime = date('Y-m-d H:i:s', $this->model->created_at);

      $status = 'error';
      if ($incomplete[0]->status > 0) {
        $status = 'up()';
      } else if ($incomplete[0]->status < 0) {
        $status = 'down()';
      }
      self::logIt("Details => case timestamp: <b>$timestamp</b>, status: <b>$status</b>, stepId: <b>$stepId</b>, DateTime: <b>$datetime</b>", true);

      $this->serve("result.htm");
      return;
    }

    switch ($action) {
      case 'migrate':
      $this->migrateAction($target);
      break;
      case 'rollback':
      $this->rollbackAction($target);
      break;
      case 'refresh':
      $this->refreshAction();
      break;
      case 'fresh':
      $this->freshAction();
      break;
      case 'reset':
      $this->downgradeAction();
      break;
      case 'retry':
      $this->retryAction();
      break;
      case 'makepath':
      $this->makePathAction();
      break;
      case 'makecase':
      $this->makeCaseAction($target);
      break;
      case 'upgrademc':
      $this->upgradeMCAction($target);
      break;
      default:
      self::logIt("Wrong Action!");
    }

    $this->serve("result.htm");
  }


  /**
   * make the directory of migration cases if not exists
   *
   * @return void
   */
  function makePathAction() {
    if (is_dir(self::$path)) {
      Migrations::logIt("The <code>PATH</code> already exists.", false);
    } else if (@mkdir(self::$path)) {
      Migrations::logIt("The <code>PATH</code> created.", false);
    } else {
      Migrations::logIt("Failed to create the <code>PATH</code> folder!", true);
    }
  }


  /**
   * make a migration case,
   * it will create a case by timestamp if the version(according to php version) not specified
   *
   * @param  string $name
   * @return void
   */
  function makeCaseAction($name = "") {
    if (empty($name)) {
      Migrations::logIt("The case name is required!", true);
      return;
    }

    $name = preg_replace('/[^a-zA-Z0-9_.]/', '', $name);
    $name = strtolower($name);

    if (!file_exists(self::$path)) {
      Migrations::logIt("The <code>PATH</code> of the cases does not exists!", true);
      return;
    }

    $className = str_replace('.', "_", $name);

    // timestamp in ms
    $timestamp = round(microtime(true) * 1000);
    $fileName = self::$path . $this->casePrefix . $name . '_' . $timestamp . '.php';

    // if (file_exists($fileName)) {
    //   Migrations::logIt("A case with entered version already exists!", true);
    //   return;
    // }

    $func = new \ReflectionClass(new MigrationCaseSample());
    $content = file_get_contents($func->getFileName());

    $content = str_replace("namespace " . __NAMESPACE__ . ";", "", $content);
    $content = str_replace('MigrationCaseSample', $className, $content);

    if (file_put_contents($fileName, $content) === false)
      Migrations::logIt("Failed to create new case!", true);
    else
      Migrations::logIt("The new case has been created.", false);
  }


  /**
   * upgrade to a higher version
   *
   * @param  object $f3
   * @param  int $target
   * @return void
   */
  function migrateAction($target) {
    if ($this->upgrade($target)) {
      $this->applyCases();
    }
  }


  /**
   * downgrade as one step back or downgrade to a lower version if the target specified
   *
   * @param  int $target
   * @return void
   */
  function rollbackAction($target = null) {
    if ($target === null) {
      // get registered cases from db order by created_at desc, id desc
      $cases = $this->model->findCases();
      $stepId = $cases[0]->step_id?:"";
      foreach($cases as $case) {
        $target = $case->timestamp;

        // we need one more case(outside of stepId scope) as carry because there is an offset on the downgrade as it's concept 
        // the concept is 
        // each MigrationCase is responsible to upgrade to itself and downgrade from itself
        // if the timestamp is x we need to downgrade all cases with higher version that x
        // so for downgrade, we need to keep the x and there is nothing to do with the case x
        if ($stepId!=$case->step_id) {
          break;
        }
      }
      // consider if not having carry case
      if ($target===null || $stepId==$case->step_id) 
        $target = 0; // zero is acceptable as a carry case
    }
    if ($target === null) {
      self::logIt("Target not specified!", true);
      return;
    }

    if ($this->downgrade($target)) {
      $this->applyCases();
    }
  }


  /**
   * register some records in the migrations table to downgrade
   *
   * @param  int $target
   * @return void
   */
  function downgradeAction($target = null) {
    if ($this->downgrade($target)) {
      $this->applyCases();
    }
  }


  /**
   * downgrade all of the registered upgrades and migrate(upgrade) them again
   *
   * @return void
   */
  function refreshAction() {
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
   * @return void
   */
  function freshAction() {
    $this->model->dropAll();
    $this->model->createTable();
    $this->dbTimestamp = $this->model->timestamp();
    $this->upgrade();
    $this->applyCases();
  }


  /**
   * retry on failed/incomplete cases registered in the migrations table
   *
   * @return void
   */
  function retryAction() {
    if (!$this->model->failedCaseExists()) {
      self::logIt("There is nothing to fix!");
      return;
    }
    $this->applyCases();
  }


  /**
   * upgrade migration cases from older version to current version
   *
   * @return void
   */
  function upgradeMCAction() {
    $items = $this->getOldMigrationCaseItems();
    if (!$items || count($items)==0) {
      self::logIt("There is no case matched old migration cases pattern.", true);
      return;
    }

    // sort array by php version number
    // equals to usort($a, 'version_compare');
    usort($items, function ($a, $b) {
      return version_compare($a->version, $b->version);
    });

    // current timestamp in ms
    $tsCurrent = round(microtime(true) * 1000);
    $result = array();
    foreach ($items as $item) {
      // change namespace
      $item->content = str_replace('DB\SQL\MigrationCase', 'DB\MIGRATIONS\MigrationCase', $item->content);

      // change class name
      if (preg_match('/class\s+(\w+)\s+extends/', $item->content, $matches)) {
        $item->content = str_replace($matches[0], 'class case_'.$this->getSafeVersionNumber($item->version).' extends', $item->content);
      }

      // save changes
      file_put_contents($item->file, $item->content);

      // rename the file
      rename (
        self::$path.'/migration_case_'.$item->version.'.php', 
        self::$path.'/'.$this->casePrefix.$item->version.'_'.($tsCurrent++).'.php'
      );
    }

    self::logIt("Upgrade done.", false);
  }


  /**
   * upgrade to $targetTimestamp by registering some records in the migrations table 
   *
   * @param  int $targetTimestamp
   * @return bool
   */
  function upgrade($targetTimestamp = null) {
    $items = $this->upgradeItems($targetTimestamp);

    if ($targetTimestamp == null) {
      // find item with highest timestamp, items array sorted by timestamp so choose the last one
      $targetTimestamp = end($items)->timestamp;
    } else if (count($this->getMigrationCaseItems($targetTimestamp)) == 0) {
      self::logIt("Target file '<b>ts: $targetTimestamp</b>' not found!");
      return false;
    }

    if ($targetTimestamp == $this->dbTimestamp) {
      self::logIt("Already migrated to '<b>$targetTimestamp</b>'!", true);
    } else if ($targetTimestamp <= $this->dbTimestamp) {
      self::logIt("Choose a case with a higher timestamp than '<b>$this->dbTimestamp</b>' or use rollback!");
    } else if (count($items) == 0) {
      self::logIt("There is nothing to do!");
    } else {
      $stepId = uniqid();
      foreach ($items as $item) {
        // all of migrations with higher timestamp than current timestamp of db
        if ($targetTimestamp >= $item->timestamp) {
          $this->model->addCase($item->timestamp, $item->name.$item->version, 1, $stepId);
        }
      }
      return true;
    }
    return false;
  }


  /**
   * get available MigrationCaseItems to upgrade, sorted by timestamp
   *
   * @param  int $targetTimestamp
   * @return MigrationCaseItem[]
   */
  function upgradeItems($targetTimestamp = null) {
    $items = $this->getMigrationCaseItems(null);
    // sort array by timestamp
    usort($items, function ($a, $b) {
      return $a->timestamp>=$b->timestamp;
    });

    $result = array();
    foreach ($items as $item) {
      // all items with higher timestamp than the current timestamp of db
      if ($item->timestamp> $this->dbTimestamp && (!$targetTimestamp ||$targetTimestamp >= $item->timestamp)) {
        $item_ = new MigrationCaseItem();
        $item_->findByTimestamp($item->timestamp);
        $result[] = $item_;
      }
    }

    return $result;
  }


  /**
   * downgrade to $targetTimestamp by registering some records in the migrations table 
   *
   * @param  int $targetTimestamp
   * @return bool
   */
  function downgrade($targetTimestamp = null) {
    // get lowest version if target version is not specified, timestamp is positive non-zero number
    if ($targetTimestamp == null) {
      $targetTimestamp = 0;
    }
    if (!$this->model->findCase($targetTimestamp) && $targetTimestamp > 0) {
      self::logIt("Target file '<b>ts: $targetTimestamp</b>' not found!");
    } else if ($targetTimestamp< 0) {
      self::logIt("The target case timestamp is invalid!");
    } else if ($targetTimestamp== $this->dbTimestamp && $targetTimestamp > 0) {
      self::logIt("The db timestamp already is '<b>$targetTimestamp</b>'!");
    } else if ($targetTimestamp> $this->dbTimestamp) {
      self::logIt("Choose an older migration case or use migrate!");
    } else if (count($items = $this->downgradeItems($targetTimestamp)) == 0) {
      self::logIt("There is nothing to do!");
    } else {
      $stepId = uniqid();
      foreach ($items as $item) {
        $this->model->addCase($item->timestamp,$item->name.$item->version, -1, $stepId);
      }
      return true;
    }
    return false;
  }


  /**
   * get available MigrationCaseItems to downgrade
   *
   * @param  int $targetTimestamp
   * @return MigrationCaseItem[]
   */
  function downgradeItems($targetTimestamp = null) {
    $result = array();
    // do we need to downgrade
    if ($targetTimestamp>= $this->dbTimestamp) {
      return $result;
    }

    $cases = $this->model->findCases(0, true);
    foreach ($cases as $case) {
      // all versions lower than the current version
      if ($case->timestamp<= $this->dbTimestamp && (!$targetTimestamp || $targetTimestamp< $case->timestamp)) {
        $item = new MigrationCaseItem();
        $item->findByTimestamp($case->timestamp);
        if ($item->valid) {
          $result[] = $item;
        }
      }
    }
    return $result;
  }


  /**
   * run the migration cases according to the registered cases in the migrations table
   *
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
    $items = $this->getMigrationCaseItems();

    // just ignoring the versions we don't need to
    foreach ($incompletes as $incomplete) {
      if (!isset($items[$incomplete->timestamp])) {
        self::logIt("The file associated with the migration case timestamp $incomplete->timestamp is missing!", true);
        return;
      }
      $item = $items[$incomplete->timestamp];
      $methodName = $this->casePrefix . $item->name.$this->getSafeVersionNumber($item->version).$item->timestamp;

      eval(" \$this->$methodName=" . str_replace(['<?php', '?>'], '', $item->content));

      $schema = new \DB\SQL\Schema($this->db);
      if ($status > 0) {
        $result = @$this->$methodName->up($this->f3, $this->db, $schema);
      } else if ($status < 0) {
        $result = @$this->$methodName->down($this->f3, $this->db, $schema);
      }

      self::logIt(($status > 0 ? 'Upgrade to' : 'Downgrade from') . " <b>$incomplete->timestamp</b>: <b>" . ($result ? 'done' : 'failed') . '</b>', !$result);

      if (!$result) {
        break;
      }

      $this->model->updateCase($incomplete->id, $status, $result);

      $this->dbTimestamp = $this->model->timestamp();
    }
  }
  

  /**
   * get all migration cases as MigrationCaseItem, get one if $timestamp specified
   *
   * @param  int $timestamp
   * @return MigrationCaseItem[]
   */
  function getMigrationCaseItems($timestamp = null) {
    $classes = array();
    if (file_exists(self::$path) && is_dir(self::$path)) {
      $directoryIterator = new \RecursiveDirectoryIterator(self::$path);
      $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);
      $fileList = new \RegexIterator($iteratorIterator, '/' . $this->casePrefix . '(.*?)_' . ($timestamp?:'(\d+)') . '.php/');
      foreach ($fileList as $file) {
        $item = new MigrationCaseItem($file);
        $classes[$item->timestamp] = $item;
      }
    }
    return $classes;
  }
  

  /**
   * find all old migration cases as MigrationCaseItem
   *
   * @return MigrationCaseItem[]
   */
  function getOldMigrationCaseItems() {
    $classes = array();
    if (file_exists(self::$path) && is_dir(self::$path)) {
      $directoryIterator = new \RecursiveDirectoryIterator(self::$path);
      $iteratorIterator = new \RecursiveIteratorIterator($directoryIterator);
      $fileList = new \RegexIterator($iteratorIterator, '/migration_case_(\d+((\.\d+)*)).php/');

      foreach ($fileList as $file) {
        preg_match('/migration_case_(\d+((\.\d+)*)).php/', $file, $matches);

        $item = new MigrationCaseItem();
        $item->file = $file;
        $item->name = $matches[1];
        $item->version = $matches[1];
        $item->timestamp = $matches[1];
        $item->content = file_get_contents($file);

        $classes[$item->version] = $item;
      }
    }
    return $classes;
  }


  /**
   * convert dots to underscores
   *
   * @param  string $versionNumber
   * @return string
   */
  function getSafeVersionNumber($versionNumber) {
    return str_replace('.', '_', $versionNumber);
  }


  /**
   * make sure the version number is right, timestamp is acceptable as a version number
   *
   * @param  string $versionNumber
   * @return bool
   */
  function isVersionCorrect($versionNumber) {
    preg_match('/(\d+((\.\d+)*))?/', $versionNumber, $matches);
    if ($matches[1] == $versionNumber) {
      return true;
    }

    return false;
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
    preg_match('/' . $this->casePrefix . '(\d+((\.\d+)*))?/', $fileName, $matches);
    if ($matches) {
      $version = $matches[1];
    }

    return $version;
  }


  /**
   * log the message
   *
   * @param  string $message
   * @param  bool $failedMessage
   * @param  bool $resetResult
   * @return void
   */
  static function logIt($message, $failedMessage = false, $resetResult = false) {
    $f3 = \Base::instance();
    if ($resetResult) {
      $f3->set('lastLog', array());
      $f3->set('lastLogError', false);
    }

    $f3->push('lastLog', $message);
    if ($failedMessage) {
      $f3->set('lastLogError', $failedMessage);
    }

    if (self::$log) {
      $logger = new \Log('migrations.log');
      $logger->write($f3->scrub($message));
    }
  }
  

  /**
   * copy folder
   *
   * @param  string $from
   * @param  string $to
   * @return void
   */
  function copyDir($from, $to) {
    // open the source directory
    $dir = opendir($from);

    // Make the destination directory if not exist
    @mkdir($to);

    // Loop through the files in source directory
    while ($file = readdir($dir)) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($from . DIRECTORY_SEPARATOR . $file)) {
          // for sub directory 
          $this->copyDir($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
        } else {
          copy($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
        }
      }
    }

    closedir($dir);
  }

}
