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

namespace GarvinHicking\AdHocTasks\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Tests\Functional\Command\AbstractCommandTestCase;

final class SchedulerAdHocExecuteCommandTest extends AbstractCommandTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'recycler'];
    protected array $testExtensionsToLoad = ['garvinhicking/adhoc-tasks'];

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function AdHocExecuteCommandFailsOnMissingTasktype(): void
    {
        // @todo Needs conversion/adaptatation?
        // @todo Does not seem to work in non-core mode?!
        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute',
        );

        self::assertEquals(1, $result['status']);
        self::assertStringContainsString('You must provide a task type.', $result['stdout']);
    }

    #[Test]
    public function AdHocExecuteCommandFailsOnInvalidTasktype(): void
    {
        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task thisIsNotTheTaskYoureLookingFor',
        );

        self::assertEquals(255, $result['status']);
        self::assertStringContainsString('Task type thisIsNotTheTaskYoureLookingFor not found.', $result['stderr']);
        self::assertStringNotContainsString('Symfony Command hint', $result['stdout']);
    }

    #[Test]
    public function AdHocExecuteCommandExecutesTaskWithoutParameters(): void
    {
        $xsdFile = $this->instancePath . '/typo3temp/var/transient/schema_TYPO3_CMS_Core_ViewHelpers.xsd';
        if (file_exists($xsdFile)) {
            unlink($xsdFile);
        }
        self::assertFileDoesNotExist($xsdFile);

        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task "fluid:schema:generate"',
        );

        self::assertEquals(0, $result['status']);
        self::assertFileExists($xsdFile);
        self::assertStringContainsString('Running ad-hoc task: fluid:schema:generate', $result['stdout']);
        self::assertStringContainsString('Ad-hoc task fluid:schema:generate finished.', $result['stdout']);
        self::assertStringContainsString('Symfony Command hint', $result['stdout']);
    }

    #[Test]
    public function AdHocExecuteCommandFailsTaskWithInvalidParameters(): void
    {
        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task "TYPO3\CMS\Recycler\Task\CleanerTask" --config "?"',
        );

        self::assertEquals(255, $result['status']);
        self::assertStringContainsString('JSON configuration of Task "TYPO3\CMS\Recycler\Task\CleanerTask" could not', $result['stderr']);
        self::assertStringContainsString('be decoded. Valid input parameters: selected_tables, number_of_days', $result['stderr']);
    }

    #[Test]
    public function AdHocExecuteCommandWorksAndIgnoresUnknownParameters(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerBase.csv');

        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task "TYPO3\CMS\Recycler\Task\CleanerTask" --config \'{"arg1":"value1","arg2":"value2","arrayArg":["a","b","c"]}\'',
        );

        self::assertEquals(0, $result['status']);
        self::assertStringContainsString('Running ad-hoc task: TYPO3\CMS\Recycler\Task\CleanerTask', $result['stdout']);
        self::assertStringContainsString('Ad-hoc task TYPO3\CMS\Recycler\Task\CleanerTask finished.', $result['stdout']);
        self::assertStringNotContainsString('Symfony Command hint', $result['stdout']);

        // No tables configured, cleaner should NOT have done anything to this table.
        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerBase.csv');
    }

    #[Test]
    public function AdHocExecuteCommandExecutesTaskWithValidParameters(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerBase.csv');

        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task "TYPO3\CMS\Recycler\Task\CleanerTask" --config \'{"selected_tables":["fe_users"],"number_of_days":"1"}\'',
        );

        self::assertEquals(0, $result['status']);
        self::assertStringContainsString('Running ad-hoc task: TYPO3\CMS\Recycler\Task\CleanerTask', $result['stdout']);
        self::assertStringContainsString('Ad-hoc task TYPO3\CMS\Recycler\Task\CleanerTask finished.', $result['stdout']);
        self::assertStringNotContainsString('Symfony Command hint', $result['stdout']);

        // One record deleted in "fe_users".
        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerApplied.csv');
    }

    #[Test]
    public function AdHocExecuteCommandFailsTaskWithParametersOfWrongValues(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerBase.csv');

        $result = $this->executeConsoleCommand(
            'scheduler:adhoc:execute --task "TYPO3\CMS\Recycler\Task\CleanerTask" --config \'{"selected_tables":"47","number_of_days":["1"]}\'',
        );

        self::assertEquals(1, $result['status']);

        self::assertStringContainsString('Code: 1759306865', $result['stdout']);
        self::assertStringContainsString('Message: Ad-hoc task failed to execute successfully. Task type: TYPO3\CMS\Recycler\Task\CleanerTask.', $result['stdout']);
        self::assertStringContainsString('Task failed to execute successfully.', $result['stdout']);
        self::assertStringNotContainsString('Symfony Command hint', $result['stdout']);

        // Nothing changed
        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/SchedulerAdHocCleanerBase.csv');
    }
}
