<?php

declare(strict_types=1);

namespace Patylook\Contracts;

/** Creates the first administrator when the user table is empty. */
interface AdminBootstrapInterface
{
    /**
     * Reports whether a user was created.
     *
     * @throws \Patylook\Exceptions\ValidationException when the given values are not acceptable
     */
    public function bootstrap(string $username, string $name, string $password): bool;
}
