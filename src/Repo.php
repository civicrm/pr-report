<?php
namespace Civi\PrReport;

class Repo {

  /**
   * @var Config
   */
  private $config;

  /**
   * @var string
   *   Printable label for this repo
   */
  public $title;

  /**
   * @var string
   *   GitHub user or organization name.
   */
  public $owner;

  /**
   * @var string
   *   Github repo name.
   */
  public $repo;

  /**
   * @var string
   *   Branch name. We'll check this branch for open PRs and recent changes.
   */
  public $branch;

  /**
   * @var string
   *   Branch/tag/commitish. The last release against which we should compare.
   *   Any past PRs already handled by $last should be ignored.
   */
  public $last;

  /**
   * @var string
   *   Absolute path to the local cache dir for this particular repo.
   */
  public $localDir;

  /**
   * Source constructor.
   * @param Config $config
   * @param array $repoConfig
   */
  public function __construct($config, $repoConfig) {
    $this->config = $config;
    $this->title = @$repoConfig['title'];
    $this->owner = @$repoConfig['owner'];
    $this->repo = @$repoConfig['repo'];
    $this->branch = @$repoConfig['head'];
    $this->last = @$repoConfig['last'];
    $this->localDir = implode(DIRECTORY_SEPARATOR, array(
      $config->cacheDir,
      $this->owner,
      $this->repo,
      $this->branch,
    ));
  }

  public function validate() {
    return
      !empty($this->title)
      && !empty($this->owner)
      && !empty($this->repo)
      && !empty($this->branch)
      && !empty($this->last)
      // eh, whatever: && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $this->title)
      && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $this->owner)
      && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $this->repo)
      && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $this->branch)
      && preg_match('/^[a-zA-Z0-9\-_\.]+$/', $this->last);
  }

  /**
   * @throws \RuntimeException
   */
  public function checkout() {
    if (!is_dir($this->localDir)) {
      $this->passthru('mkdir', '-p', dirname($this->localDir));
      $url = 'https://github.com/' . $this->owner . '/' . $this->repo;
      $this->passthru('git', 'clone', $url, '-b', $this->branch, $this->localDir);
    }
    else {
      $oldDir = getcwd();
      chdir($this->localDir);
      $this->passthru('git', 'checkout', $this->branch);
      $this->passthru('git', 'pull', 'origin', $this->branch);
      chdir($oldDir);
    }
    return TRUE;
  }

  /**
   * @param Filter $filter
   * @return array
   */
  public function findPullRequests(Filter $filter) {
    $repo = $this;
    $client = $this->config->client;

    $pulls = array();
    if ($filter->includeOpen){
      $openPrs = $client->pulls->listPullRequests($repo->owner, $repo->repo, 'open', NULL, $repo->branch);
      $pulls = array_merge($pulls, $openPrs);
    }

    if ($filter->includeRecentlyMerged) {
      $closedPrs = $client->pulls->listPullRequests($repo->owner, $repo->repo, 'closed', NULL, $repo->branch);
      $pulls = array_merge($pulls, array_filter($closedPrs,
        function (\GithubPull $pull) use ($repo) {
          // Merged at all?
          if (!$pull->getMergedAt()) {
            return FALSE;
          }
          // Merged into a previous release?
          return !$repo->checkTagContains($repo->last, $pull->getHead()->getSha());
        }
      ));
    }

    return $pulls;
  }

  /**
   * @param string $tag
   * @param string $sha
   * @return bool
   */
  public function checkTagContains($tag, $sha) {
    $oldDir = getcwd();
    chdir($this->localDir);
    $cmd = 'git tag --contains ' . escapeshellarg($sha);
    exec($cmd, $output, $returnVal);
    chdir($oldDir);
    if ($returnVal) {
      // if (preg_grep('/no such commit/', $output)) {return FALSE;}
      // else { throw ...}
      throw new \RuntimeException("Command failed: $cmd\n" . implode("\n", $output));
    }
    return in_array($tag, $output);
  }

  /**
   * Call an external command, passing through any IO.
   * Automatically escape and combine arguments.
   * Throw a runtime exception if the command fails.
   *
   * Ex: $this->passthru('ls', '-lR', '/home');
   */
  protected function passthru() {
    $args = func_get_args();
    $cmd = implode(' ', array_map('escapeshellarg', $args));
    // echo "[[$cmd]]\n";
    passthru($cmd, $returnVal);
    if ($returnVal !== 0) {
      throw new \RuntimeException("Command failed: $cmd");
    }
  }

}