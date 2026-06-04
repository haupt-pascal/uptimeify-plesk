<?php

// Entry point for the extension UI. Plesk links the "Open" button here and
// pm_Application dispatches to plib/controllers (IndexController by default).

declare(strict_types=1);

$application = new pm_Application();
$application->run();
