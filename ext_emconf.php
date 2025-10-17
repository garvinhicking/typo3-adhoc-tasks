<?php

declare(strict_types=1);

$EM_CONF['adhoc_tasks'] = [
    'title' => 'TYPO3 Scheduler AdHoc Tasks',
    'description' => 'Allows to run any TYPO3 (v14) Scheduler Task on the command line, with custom configuration.',
    'category' => 'be',
    'author' => 'Garvin Hicking',
    'author_company' => '',
    'author_email' => 'garvin@hick.ing',
    'state' => 'stable',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
