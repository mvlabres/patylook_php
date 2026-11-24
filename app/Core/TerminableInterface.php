<?php

declare(strict_types=1);

namespace Patylook\Core;

/** Work that can wait until the response has been sent to the client. */
interface TerminableInterface
{
    public function terminate(): void;
}
