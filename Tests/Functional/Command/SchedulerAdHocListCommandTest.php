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

use GarvinHicking\AdHocTasks\Command\SchedulerAdHocListCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Tests\Functional\Command\AbstractCommandTestCase;

final class SchedulerAdHocListCommandTest extends AbstractCommandTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler'];
    protected array $testExtensionsToLoad = ['garvinhicking/adhoc-tasks'];

    private SchedulerAdHocListCommand $subject;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(SchedulerAdHocListCommand::class);
        $application = new Application();
        $application->add($this->subject);

        $command = $application->find('scheduler:adhoc:list');
        // @todo Needs conversion/adaptatation?
        $this->commandTester = new CommandTester($command);
    }

    #[Test]
    public function AdHocListCommandFindsCoreNativeTasks(): void
    {
        $result = $this->commandTester->execute([]);

        self::assertEquals(0, $result['status']);
        self::assertStringContainsString(' native tasks listed.', $result['stdout']);
        self::assertStringNotContainsString('0 native tasks listed.', $result['stdout']);
    }
}
