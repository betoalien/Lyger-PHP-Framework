<?php

declare(strict_types=1);

namespace Lyger\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console Kernel - Handles CLI commands
 */
class Kernel
{
    protected array $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        // Register built-in commands
        $this->commands[] = MakeControllerCommand::class;
        $this->commands[] = MakeModelCommand::class;
        $this->commands[] = MakeMigrationCommand::class;
        $this->commands[] = MakeDashCommand::class;
    }

    public function register(string $command): void
    {
        $this->commands[] = $command;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        $application = new \Symfony\Component\Console\Application('Lyger CLI', '1.0.0');

        foreach ($this->commands as $command) {
            $application->add(new $command());
        }

        return $application->run($input, $output);
    }
}

/**
 * Base Command - Foundation for CLI commands
 */
abstract class Command extends \Symfony\Component\Console\Command\Command
{
    protected function configure(): void
    {
        $this->setName($this->getCommandName())
            ->setDescription($this->getDescription())
            ->setHelp($this->getHelp());
    }

    abstract protected function getCommandName(): string;
    abstract protected function getDescription(): string;
    abstract protected function getHelp(): string;
    abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info('Lyger Framework v0.1');
        return $this->executeCommand($input, $output);
    }

    protected function info(string $message): void
    {
        $this->output->writeln("<info>{$message}</info>");
    }

    protected function error(string $message): void
    {
        $this->output->writeln("<error>{$error}</error>");
    }

    protected function line(string $message): void
    {
        $this->output->writeln($message);
    }

    protected function table(array $headers, array $rows): void
    {
        $table = new \Symfony\Component\Console\Helper\Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();
    }
}
