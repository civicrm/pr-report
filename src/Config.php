<?php
namespace Civi\PrReport;

class Config {
  /**
   * @var array
   */
  public $repos;

  /**
   * @var array
   */
  public $filters;

  /**
   * @var string
   */
  public $cacheDir;

  /**
   * @var \GithubClient
   */
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

  /**
   * @return array
   *   Array<Filter>.
   */
  public function createFilters() {
    $filters = array();
    foreach ($this->filters as $key => $filterConfig) {
      $filter = new Filter($this, $filterConfig);
      if (!$filter->validate()) {
        throw new \RuntimeException("Invalid Filter ($key): " . print_r($filterConfig, 1));
      }
      $filters[] = $filter;
    }
    return $filters;
  }

}