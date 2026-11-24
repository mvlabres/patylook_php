<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\Dashboard;

interface DashboardServiceInterface
{
    public function load(): Dashboard;
}
