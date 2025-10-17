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

namespace GarvinHicking\AdHocTasks\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Exception\InvalidTaskException;
use TYPO3\CMS\Scheduler\Service\TaskService;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;

/**
 * Deserializes ad-hoc task.
 */
#[Autoconfigure(public: true)]
class AdHocTaskSerializer
{
    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly TaskService $taskService,
    ) {}

    /**
     * Similar to TYPO3\CMS\Scheduler\Task\TaskSerializer->deserialize(), however this is meant for ad-hoc tasks which do not require
     * execution details, since they are not based on a database row (via the SchedulerAdHocExecuteCommand for example).
     * @param string $taskType FQDN or identifier
     * @param string $taskConfiguration Configuration as serialized JSON string
     * @see TYPO3\CMS\Scheduler\Task\TaskSerializer
     */
    public function deserializeFromAdHocTask(string $taskType, string $taskConfiguration): AbstractTask
    {
        if ($this->taskService->isTaskTypeRegistered($taskType)) {
            $taskInformation = $this->taskService->getTaskDetailsFromTaskType($taskType);
            $className = $taskInformation['className'];
            try {
                $taskObject = $this->container->get($className);
            } catch (ServiceNotFoundException) {
                $taskObject = GeneralUtility::makeInstance($className);
            }
        } else {
            throw new InvalidTaskException('Task type ' . $taskType . ' not found. Probably not registered?', 1759306813);
        }

        if (!$taskObject instanceof AbstractTask) {
            throw new InvalidTaskException('The deserialized task in not an instance of AbstractTask', 1759306814);
        }
        if ($taskObject instanceof ExecuteSchedulableCommandTask) {
            $taskObject->setTaskType($taskType);
        }

        $taskParameters = [];
        if (strlen($taskConfiguration) > 0) {
            $taskParameters = json_decode($taskConfiguration, true);
            if ($taskParameters === null) {
                $paramList = implode(', ', array_keys($taskObject->getTaskParameters()));
                throw new InvalidTaskException('JSON configuration of Task "' . $taskType . '" could not be decoded. Valid input parameters: ' . $paramList, 1759306815);
            }
        }

        if ($taskInformation['isNativeTask'] ?? false) {
            // If there are native registered fields, they take precedence over the values.
            foreach ($taskInformation['additionalFields'] ?? [] as $additionalFieldName) {
                $taskParameters[$additionalFieldName] = $taskParameters[$additionalFieldName] ?? null;
            }
        }
        $taskObject->setTaskParameters($taskParameters);

        return $taskObject;
    }
}
