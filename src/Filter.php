<?php
namespace Civi\PrReport;

class Filter {

  /**
   * @var string
   *  String, 'open' or 'closed'.
   */
  public $state;

  /**
   * @var boolean
   *  TRUE to only include items recently-merged. FALSE to disregard this filter option.
   */
  public $recentlyMerged;

  /**
   * @var int
   * The number of records to fetch in each page-request.
   * Note: At time of writing, Github.com limits this to 100.
   */
  public $pageSize = 100;

  /**
   * @var int
   * The maximum number of page-requests to submit to github.com.
   */
  public $maxPages = 5;

  public function __construct($config, $filterConfig) {
    $this->config = $config;
    foreach ($filterConfig as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function validate() {
    return in_array($this->state, array('open', 'closed'));
  }

  public function findPullRequests(\GitHubClient $client, Repo $repo, $pageSize = 100, $maxPages = 15) {
    $filter = $this;
    $page = 0; // Current page#.

    $result = array();
    /** @var \GithubPull $pulls */

    do {
      $client->setPage(++$page);
      $client->setPageSize($pageSize);
      echo "{$repo->repo} get pg={$page} ps=$pageSize\n";
      $pulls = $client->pulls->listPullRequests($repo->owner, $repo->repo, $filter->state, NULL, $repo->baseBranch);
      $resultCount = count($pulls);

      if ($filter->recentlyMerged) {
        $pulls = array_filter($pulls,
          function (\GithubPull $pull) use ($repo) {
            // Merged at all?
            if (!$pull->getMergedAt()) {
              return FALSE;
            }
            // Merged into a previous release?
            return !$repo->checkTagContains($repo->lastTag, $pull->getHead()->getSha());
          }
        );
      }

      $result = array_merge($result, $pulls);
    } while ($resultCount === $pageSize && $page < $maxPages);

    return $result;
  }

}
