<?php
namespace Civi\PrReport;

class Config {
  /** @var array */
  public $repos;

  /** @var string */
  public $cacheDir;

  /** @var boolean */
  public $includeOpen;

  /** @var boolean */
  public $includeRecentlyMerged;

  /** @var \GithubClient */
  public $client;

  public function __construct() {
    $this->cacheDir = sys_get_temp_dir() . '/pr-report';
  }

  /**
   * @return array
   *   Array<Repo>.
   */
  public function createRepos() {
    $repos = array();
    foreach ($this->repos as $key => $repoConfig) {
      $repo = new Repo($this, $repoConfig);
      if (!$repo->validate()) {
        throw new \RuntimeException("Invalid Repo ($key): " . print_r($repoConfig, 1));
      }
      $repos[] = $repo;
    }
    return $repos;
  }

}