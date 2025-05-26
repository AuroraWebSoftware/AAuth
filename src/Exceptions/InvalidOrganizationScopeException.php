<?php

namespace AuroraWebSoftware\AAuth\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvalidOrganizationScopeException extends Exception
{
    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        //
        return null;
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response|bool
     */
    public function render(Request $request): Response|bool
    {
        // return response(...);
        return false;
    }
}
