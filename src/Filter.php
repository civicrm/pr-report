<?php
namespace Civi\PrReport;

class Filter {

  /** @var boolean */
  public $includeOpen;

  /** @var boolean */
  public $includeRecentlyMerged;

  public function __construct($config, $filterConfig) {
    $this->config = $config;
    foreach ($filterConfig as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function validate() {
    return TRUE;
  }

}