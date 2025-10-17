<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GarvinHicking\AdHocTasks\Command;

use GarvinHicking\AdHocTasks\Service\AdHocTaskSerializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Scheduler\FailedExecutionException;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;

/**
 * CLI command for EXT:scheduler to execute tasks
 */
class SchedulerAdHocExecuteCommand extends Command
{
    protected SymfonyStyle $io;

    public function __construct(
        protected readonly AdHocTaskSerializer $adHocTaskSerializer,
        protected readonly Scheduler $scheduler,
    ) {
        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setHelp('Execute given Scheduler ad-hoc task that is not scheduled. Dynamic options can be specified as JSON, see "--config" option.')
            ->addOption(
                'task',
                't',
                InputOption::VALUE_REQUIRED,
                'Execute a specific ad-hock task type (FQCN like "TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask", or shorthand identifiers if existing). See "scheduler:adhoc:list" for a list of available types.',
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Set the dynamic parameters of an adhoc-task. Must be specified as a JSON string to allow for arrays and multi-lines. Example:'
                . "\n" . '<info>--config=\'{"arg1":"value1","arg2":"value2","arrayArg":["a","b","c"]}\'</info>'
                . "\n" . '<info>Hint: </info> Use "?" or any non-JSON string to see the list of available parameters for a task type.',
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure the _cli_ user is loaded
        Bootstrap::initializeBackendAuthentication();

        if ((string)$input->getOption('task') === '') {
            $output->writeln('<error>You must provide a task type.</error>');
            return Command::FAILURE;
        }
        // Example:
        // vendor/bin/typo3 scheduler:adhoc:execute --task 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask' --config='{"selected_tables": "be_dashboards,be_groups"}'
        return $this->executeAdHocTask(
            (string)$input->getOption('task'),
            $input->getOption('config') ?? '',
            $output
        );
    }

    /**
     * @param string $taskType FQDN or identifier
     * @param string $taskConfiguration Configuration in serialized JSON format
     */
    private function executeAdHocTask(string $taskType, string $taskConfiguration, OutputInterface $output): int
    {
        $output->writeln(sprintf('Running ad-hoc task: <info>%s</info>', $taskType));
        $task = $this->adHocTaskSerializer->deserializeFromAdHocTask($taskType, $taskConfiguration);

        if ($task->getTaskClassName() === ExecuteSchedulableCommandTask::class) {
            $output->writeln('<info> --> Symfony Command hint:</info> This ad-hoc task is a Symfony Command. You can run it directly, with easy parameter formatting. No need to run this through ad-hoc emulation.');
        }

        try {
            // Execute task
            $successfullyExecuted = $task->execute();
            if (!$successfullyExecuted) {
                throw new FailedExecutionException('Ad-hoc task failed to execute successfully. Task type: ' . $task->getTaskType() . '.', 1759306865);
            }
            $output->writeln(sprintf('Ad-hoc task <info>%s</info> finished.', $taskType));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            // Log failed execution
            $output->writeln('<error>Task failed to execute successfully.</error>');
            $output->writeln('<info>Code:</info> <error>' . $e->getCode() . '</error>');
            $output->writeln('<info>Message:</info> <error>' . $e->getMessage() . '</error>');
            $output->writeln('<info>File:</info> <error>' . $e->getFile() . '</error>');
            $output->writeln('<info>Line:</info> <error>' . $e->getLine() . '</error>');
        }

        return Command::FAILURE;
    }
}
