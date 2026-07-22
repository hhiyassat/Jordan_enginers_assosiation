<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\StorageServiceProvider;

return [
    AppServiceProvider::class,
    StorageServiceProvider::class,
    // Workstream 7: last so config('modules.enabled') is available.
    // Registering each enabled module's own service provider is
    // what wires their routes / migrations / commands into the app.
    ModulesServiceProvider::class,
];
