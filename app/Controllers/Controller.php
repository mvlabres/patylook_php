<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use Patylook\Core\Request;
use Patylook\Dto\Principal;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\UnauthenticatedException;
use Patylook\Http\Responder;
use Patylook\Models\User;
use Patylook\Support\Str;

/** Common helpers of the controllers. Each controller method takes a Request and returns a Response. */
abstract class Controller
{
    public function __construct(protected readonly Responder $responder)
    {
    }

    protected function principal(Request $request): Principal
    {
        $principal = $request->attribute('principal');
        if (!$principal instanceof Principal) {
            throw new UnauthenticatedException();
        }
        return $principal;
    }

    /** The authenticated user making the request. */
    protected function user(Request $request): User
    {
        return $this->principal($request)->user;
    }

    /**
     * The positive integer in the route parameter.
     *
     * @throws NotFoundException when it is not one
     */
    protected function routeId(Request $request, string $name = 'id'): int
    {
        return $request->routeId($name) ?? throw new NotFoundException();
    }

    /** The requested list page, at least 1. */
    protected function pageNumber(Request $request): int
    {
        return max(1, Str::toInt($request->query('page')) ?? 1);
    }
}
