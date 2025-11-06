<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        // Set timezone from environment variable, default to Europe/Paris
        // Symfony loads .env into $_ENV, so check there first, then getenv() for system env
        $timezone = $_ENV['TZ'] ?? getenv('TZ') ?: 'Europe/Paris';
        date_default_timezone_set($timezone);
    }
}
