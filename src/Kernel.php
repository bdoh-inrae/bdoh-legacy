<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Pages and job queue in UTC, like the measures, whatever php.ini says (dev/differences.md, « Deux fuseaux horaires »)
        date_default_timezone_set('UTC');

        parent::boot();
    }
}
