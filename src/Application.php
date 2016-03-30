<?php
namespace Civi\PrReport;

use Civi\PrReport\Command\RunCommand;
use Symfony\Component\Console\Input\InputInterface;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    $application = new Application('cv', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
  }

  /**
   * Gets the name of the command based on input.
   *
   * @param InputInterface $input The input interface
   *
   * @return string The command name
   */
  protected function getCommandName(InputInterface $input) {
    return 'pr-report';
  }

  /**
   * Gets the default commands that should always be available.
   *
   * @return array An array of default Command instances
   */
  protected function getDefaultCommands() {
    // Keep the core default commands to have the HelpCommand
    // which is used when using the --help option
    $defaultCommands = parent::getDefaultCommands();

    $defaultCommands[] = new RunCommand();

    return $defaultCommands;
  }

  /**
   * Overridden so that the application doesn't expect the command
   * name to be the first argument.
   */
  public function getDefinition() {
    $inputDefinition = parent::getDefinition();
    // clear out the normal first argument, which is the command name
    $inputDefinition->setArguments();

    return $inputDefinition;
  }

}
