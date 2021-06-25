<?php

/**
 * @package F3 Migrations, MigrationCaseItem
 * @version 2.0.0
 * @link http://github.com/myaghobi/F3-Migrations Github
 * @author Mohammad Yaghobi <m.yaghobi.abc@gmail.com>
 * @copyright Copyright (c) 2020, Mohammad Yaghobi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3
 */

namespace DB\MIGRATIONS;

use DB\MIGRATIONS\Migrations;

class MigrationCaseItem {
	public $file;
	public $name;
	public $version;
	public $timestamp;
	public $content;
  public $valid;

  private $casePrefix;
  private $f3;


  /**
   * constructor
   *
   * @param  string $path
   * @return void
   */
  function __construct($path=null) {
    $this->f3 = \Base::instance();

    $this->casePrefix = $this->f3->get('migrations.CASE_PREFIX') ?: 'migration_case_';
    
    $this->valid = false;
  	$this->parsByFile($path);
  }


  /**
   * pars attrs with path
   *
   * @param  string $path
   * @return void
   */
  function parsByFile($path=null) {
    $this->valid = false;

    if (!$path || !file_exists($path)) {
      return;
    }

    $this->file = $path;

  	$fileName = pathinfo($path)['basename'];
  	preg_match('/' . $this->casePrefix . '(.*?)(\d+(\.\d+)*)?_(\d+).php/', $fileName, $matches);
  	if ($matches) {
  		$this->name = $matches[1];
  		$this->version = $matches[2];
  		$this->timestamp = $matches[4];
  	}

  	$fileContent = file_get_contents($path);

  	if (preg_match('/class\s+(\w+)\s+extends/', $fileContent, $matches)) {
  		$str = str_replace($matches[0], 'new class() extends', $fileContent) . ';';
  		$this->content = $str;
  	}

    $this->valid = true;
  }


  /**
   * find a migration case by timestamp
   *
   * @param  string $path
   * @return void
   */
  function findByTimestamp($timestamp) {
    $this->valid = false;

  	$result = glob(Migrations::$path.$this->casePrefix.'*_'.$timestamp.'.php');
    if (count($result)==1) {
      $this->parsByFile($result[0]);
    }
  }
}

?>