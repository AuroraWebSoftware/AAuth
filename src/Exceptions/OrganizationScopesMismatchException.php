<?php

namespace AuroraWebSoftware\AAuth\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationScopesMismatchException extends Exception
{
    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response
     */
    public function render(Request $request): Response|bool
    {
        // return response(...);
        return false;
    }
}
