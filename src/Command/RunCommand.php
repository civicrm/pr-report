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
      ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Configuration file)')
      ->addOption('cred', NULL, InputOption::VALUE_REQUIRED, 'Credentials file)')
      ->addOption('no-checkout', NULL, InputOption::VALUE_NONE, 'Skip git checkout');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $cred = $this->parseCredentials($input, $output);
    if ($cred === NULL) {
      return 1;
    }

    /** @var Config $config */
    $config = $this->parseConfig($input);
    $config->client = new \GitHubClient();
    $config->client->setCredentials($cred['username'], $cred['password']);

    $repos = $config->createRepos();
    $filters = $config->createFilters();

    $rows = array();
    foreach ($repos as $repo) {
      /** @var Repo $repo */
      if (!$input->getOption('no-checkout')) {
        $repo->checkout();
      }

      foreach ($filters as $filter) {
        /** @var Filter $filter */
        foreach ($filter->findPullRequests($config->client, $repo) as $pull) {
          /** @var \GithubPull $pull */
          $rows[] = array(
            'id' => "{$repo->id} #{$pull->getNumber()}",
            'state' => $pull->getState(),
            'merged' => $pull->getMergedAt() ? 1 : 0,
            'title' => $pull->getTitle(),
            'url' => $pull->getHtmlUrl(),
          );
        }
      }
    }

    print_r($rows);
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

}