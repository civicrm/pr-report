<?php
namespace Civi\PrReport\Command;

use Civi\PrReport\Config;
use Civi\PrReport\Filter;
use Civi\PrReport\Repo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class RunCommand extends Command {
  protected function configure() {
    $this->setName('pr-report')
      ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Configuration file')
      ->addOption('cred', NULL, InputOption::VALUE_REQUIRED, 'Credentials file')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output directory')
      ->addOption('csv', NULL, InputOption::VALUE_REQUIRED, 'Output CSV file')
      ->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Output JSON file')
      ->addOption('html', NULL, InputOption::VALUE_REQUIRED, 'Output HTML file')
      ->addOption('no-checkout', NULL, InputOption::VALUE_NONE, 'Skip git checkout');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!$input->getOption('csv') && !$input->getOption('html') && !$input->getOption('json')) {
      $output->writeln("<error>Expected at least one of these options: \"--csv=[file]\", \"--html=[file]\", or \"--json=[file]\"</error>");
      return 1;
    }

    $cred = $this->parseCredentials($input, $output);
    if ($cred === NULL) {
      return 1;
    }

    /** @var Config $config */
    $config = $this->parseConfig($input);
    $repos = $config->createRepos();
    $filters = $config->createFilters();
    $issueParser = $config->createIssueParser();

    $rows = array();
    foreach ($repos as $repo) {
      /** @var Repo $repo */
      if (!$input->getOption('no-checkout')) {
        $repo->checkout();
      }

      foreach ($filters as $filter) {
        $client = new \GitHubClient();
        $client->setCredentials($cred['username'], $cred['password']);

        /** @var Filter $filter */
        foreach ($filter->findPullRequests($client, $repo) as $pull) {
          /** @var \GithubPull $pull */
          $rows[] = array(
            'id' => "{$repo->id} #{$pull->getNumber()}",
            'url' => $pull->getHtmlUrl(),
            'title' => $pull->getTitle(),
            'author' => $pull->getUser()->getLogin(),
            'state' => $pull->getState(),
            'merged' => $pull->getMergedAt() ? 1 : 0,
            'issues' => $issueParser->parse($pull->getTitle() . ' ' . $pull->getBody()),
          );
        }
      }
    }
    if ($input->getOption('dir')) {
      $this->writeCsv($input->getOption('dir') . '/report.csv', $rows);
      $this->writeHtml($input->getOption('dir') . '/report.html', $rows);
      $this->writeJson($input->getOption('dir') . '/report.json', $rows);
    }
    if ($input->getOption('json')) {
      $this->writeJson($input->getOption('json'), $rows);
    }

    if ($input->getOption('csv')) {
      $this->writeCsv($input->getOption('csv'), $rows);
    }

    if ($input->getOption('html')) {
      $this->writeHtml($input->getOption('html'), $rows);
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return null
   */
  protected function parseCredentials(InputInterface $input, OutputInterface $output) {
    $cred = NULL;
    if ($input->hasOption('cred') && $input->getOption('cred')) {
      $allCred = json_decode(file_get_contents($input->getOption('cred')), 1);
      if (empty($allCred) || !is_array($allCred['github']) || !isset($allCred['github']['username']) || !isset($allCred['github']['password'])) {
        $output->writeln("<error>Credential file is empty or malformed: " . $input->getOption('cred') . " </error>");
      }
      $cred = $allCred['github'];
      return $cred;
    }
    else {
      $helper = $this->getHelper('question');

      $output->writeln("<info>To query Github API without hitting rate limits, please enter your Github credentials.</info>");

      $userQuestion = new Question('Github user: ');
      $cred['username'] = $helper->ask($input, $output, $userQuestion);

      $passQuestion = new Question('Github password: ');
      $passQuestion->setHidden(TRUE);
      $passQuestion->setHiddenFallback(FALSE);
      $cred['password'] = $helper->ask($input, $output, $passQuestion);
      return $cred;
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return mixed
   */
  protected function parseConfig(InputInterface $input) {
    if (!$input->hasOption('file') || !file_exists($input->getOption('file'))) {
      throw new \RuntimeException("Failed to read file: " . $input->getOption('file'));
    }

    $configArr = json_decode(file_get_contents($input->getOption('file')), 1);
    if (empty($configArr) || !is_array($configArr['repos'])) {
      throw new \RuntimeException("Config file is empty or malformed: " . $input->getOption('file'));
    }

    $config = new Config();
    foreach ($configArr as $k => $v) {
      $config->{$k} = $v;
    }

    return $config;
  }

  /**
   * @param $file
   * @param $rows
   */
  protected function writeJson($file, $rows) {
    $opt
      = (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0)
    | (defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0);
    file_put_contents($file, json_encode($rows, $opt));
  }

  /**
   * @param $rows
   */
  protected function writeCsv($file, $rows) {
    $fh = fopen($file, "w");
    if (!$fh) {
      throw new \RuntimeException("Failed to open $file");
    }
    fputcsv($fh, array(
      'id',
      'url',
      'title',
      'author',
      'state',
      'merged',
      'issue-codes',
      'issue-urls',
    ));
    foreach ($rows as $row) {
      fputcsv($fh, array(
        $row['id'],
        $row['url'],
        $row['title'],
        $row['author'],
        $row['state'],
        $row['merged'],
        implode(' ', array_keys($row['issues'])),
        implode(' ', $row['issues']),
      ));
    }
    fclose($fh);
  }

  /**
   * @param $file
   * @param $rows
   */
  protected function writeHtml($file, $rows) {
    $buf = '';
    $buf .= "<html><body><table>\n";
    $buf .= "<thead>\n";
    $buf .= "<tr><td>ID</td><td>Title</td><td>Author</td><td>State</td><td>Issue</td></tr>\n";
    $buf .= "</thead><tbody>\n";
    foreach ($rows as $row) {
      $issueLinks = array();
      foreach ($row['issues'] as $issue => $url) {
        $issueLinks[] = sprintf("<a href=\"%s\">%s</a>", $url, $issue);
      }
      $buf .= (sprintf("<tr><td><a href=\"%s\">%s</a></td><td><a href=\"%s\">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>",
        $row['url'], $row['id'],
        $row['url'], $row['title'],
        $row['author'],
        $row['state'] . ($row['merged'] == 1 ? ' (merged)' : ''),
        '' . implode(', ', $issueLinks)
      ));
    }
    $buf .= "</tbody>\n";
    $buf .= "</table></body></html>\n";
    file_put_contents($file, $buf);
  }

}
