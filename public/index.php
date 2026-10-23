<?php

use App\Kernel;

// Comme en production, les pages sont en UTC et la ligne de commande à l'heure de Paris
// (dev/differences.md, « Deux fuseaux horaires »).
date_default_timezone_set('UTC');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
