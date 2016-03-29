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

  public function __construct($config, $filterConfig) {
    $this->config = $config;
    foreach ($filterConfig as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function validate() {
    return in_array($this->state, array('open', 'closed'));
  }

  public function findPullRequests(\GitHubClient $client, Repo $repo) {
    $filter = $this;

    $pulls = $client->pulls->listPullRequests($repo->owner, $repo->repo, $filter->state, NULL, $repo->branch);

    if ($filter->recentlyMerged) {
      $pulls = array_filter($pulls,
        function (\GithubPull $pull) use ($repo) {
          // Merged at all?
          if (!$pull->getMergedAt()) {
            return FALSE;
          }
          // Merged into a previous release?
          return !$repo->checkTagContains($repo->last, $pull->getHead()->getSha());
        }
      );
    }

    return $pulls;
  }

}