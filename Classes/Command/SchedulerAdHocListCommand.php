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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Service\TaskService;

/**
 * CLI command for EXT:scheduler to list ad-hoc tasks
 */
class SchedulerAdHocListCommand extends Command
{
    protected SymfonyStyle $io;

    public function __construct(
        protected readonly Context $context,
        protected readonly TaskService $taskService,
        protected readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {
        parent::__construct();
    }

    public function configure()
    {
        $this->setHelp('List all Scheduler ad-hoc tasks that can be run without being scheduled.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \InvalidArgumentException('This command accepts only an instance of "ConsoleOutputInterface".', 1759609449);
        }

        // Make sure the _cli_ user is loaded
        Bootstrap::initializeBackendAuthentication();
        $this->io = new SymfonyStyle($input, $output);
        $languageService = $this->getLanguageService();

        $tableHeader = [
            $languageService->sL('LLL:EXT:scheduler/Resources/Private/Language/locallang_tca.xlf:tx_scheduler_task.tasktype'),
            $languageService->sL('LLL:EXT:scheduler/Resources/Private/Language/locallang.xlf:label.description'),
            $languageService->sL('LLL:EXT:scheduler/Resources/Private/Language/locallang_tca.xlf:tx_scheduler_task.parameters'),
        ];

        $tableSection = $output->section();
        $tableBuffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $table = new Table($tableBuffer);
        $table->setHeaders($tableHeader);
        $rows = $this->getTableRows();
        $table->setRows($rows);
        $table->setColumnMaxWidth(1, 50);
        $table->setColumnMaxWidth(2, 50);
        $table->render();

        $bufferedData = $tableBuffer->fetch();
        $tableSection->overwrite($bufferedData);

        return Command::SUCCESS;
    }

    private function getTableRows(): array
    {
        $tasks = $this->taskService->getAllTaskTypes(true);

        $rows = [];
        $counter = 0;

        // Here we get the TCA types for each parameter. The Core's TaskService does not
        // transfer this, so we query the TCA Schema API ourself.
        $schema = $this->tcaSchemaFactory->get('tx_scheduler_task');
        $parameterTcaMapping = [];
        foreach ($schema->getField('tasktype')->getConfiguration()['items'] ?? [] as $item) {
            if (is_array($item) && $item['value'] !== 'div') {
                $taskType = $item['value'];
                $parameterTcaMapping[$taskType] = [];
                if ($schema->hasSubSchema($taskType)) {
                    $subSchema = $schema->getSubSchema($taskType);
                    $additionalFields = $subSchema->getFields();
                    foreach ($additionalFields as $field) {
                        $parameterTcaMapping[$taskType][$field->getName()] = $field->getType();
                    }
                }
            }
        }

        foreach ($tasks as $task) {
            if (!isset($task['isNativeTask']) || !$task['isNativeTask']) {
                continue;
            }

            $rows[] = [
                $task['taskType'],
                '<fg=yellow>' . $task['category'] . '</fg=yellow>' . "\n" . $task['description'],
                $this->getArgumentDefinitions($task['additionalFields'], $parameterTcaMapping[$task['taskType']]),
            ];
            $rows[] = [new TableSeparator(['colspan' => 3])];
            $counter++;
        }

        $tasksFound = sprintf('%d native tasks listed.', $counter);
        $rows[] = [new TableCell('<options=bold>' . $tasksFound . '</>', ['colspan' => 3])];

        return $rows;
    }

    private function getArgumentDefinitions(array $additionalFields, array $additionalFieldTypes): string
    {
        if ($additionalFields === []) {
            return '---';
        }
        $output = [];

        foreach ($additionalFields as $field) {
            $output[] = $field . ' <fg=yellow>[' . ($additionalFieldTypes[$field] ?? '---') . ']</fg=yellow>';
        }
        return implode("\n", $output);
    }

    private function getLanguageService(): LanguageService
    {
        return GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }
}
