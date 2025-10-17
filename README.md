# typo3-adhoc-tasks

Allows to run any TYPO3 (v14) Scheduler Task on the command line, with custom configuration

This was originally proposed as https://review.typo3.org/c/Packages/TYPO3.CMS/+/90852.

## Installation

This package can be installed (in TYPO3 v14) as a composer package:

```
composer req "garvinhicking/adhoc-tasks"
```

See packagist: https://packagist.org/packages/garvinhicking/adhoc-tasks

No "classic mode" support is planned for this extension.

## Description

With the reworked scheduler data storage format of TYPO3 v14 (https://forge.typo3.org/issues/106532)
this opened up the possibility to have "ad-hoc" tasks.

Previously, the TYPO3 scheduler tasks that were extended from `AbstractTask`
did not allow to be run without specific configuration. They needed to
be stored as a task in the database, containing serialized parameters/arguments.

This meant, that these tasks could only be run from `typo3/bin/typo3 scheduler:execute`
if they had a corresponding serialized task database entry, and could not be
run with different configuration on the CLI, or at all.

To bypass this, the recommendation was to create all tasks as Symfony Commands,
which also allowed CLI execution. This is still a recommendation for today.

However, several TYPO3 core tasks still exist (like for garbage collection,
file reference integrity and others) and these are now executable as ad-hoc tasks,
without the need to refactor every `AbstractTask` into a Symfony Command.

Ad-hoc tasks can be executed like:

```
    bin/typo3 scheduler:adhoc:execute
      --task 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask'
      --config='{"selected_tables": "be_dashboards,be_groups"}'
```

The two new options `--task` and `--config` specify the unique
ID of a task to execute (currently a fully-qualified classname, in the future probably
shorthand IDs), and passes the arguments as a JSON string.

The choice to put a serialized JSON string as arguments has several
advantages:

*  It can be the same JSON string as stored in the `tx_scheduler_task.parameters`
   database column.
*  It allows to specify newlines and array-types on the CLI
*  It prevents the need to define, pass-through and validate arbitrary CLI arguments

> **HINT**:
> The list of available parameters of a given task type can be investigated
> via:
> ```
>        bin/typo3 scheduler:adhoc:execute
>          --task 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask'
>          --config='?'
> ```

A second command `bin/typo3 scheduler:adhoc:list` shows a list of all ad-hoc
tasks that are available, with a list of their parameters.

## Impact

All TYPO3 Core tasks and custom `AbstractTask` implementations utilizing
the "native task" TCA format can now be run from the CLI and no longer
need "dummy" entries in the Scheduler.

This helps for debugging tasks as well as one-off runs of a specific task.


## TODO

Tests were adapted from the TYPO3 Core patch. They do not (yet) work standalone
due to difference in how Symfony CLI commands can be tested.
