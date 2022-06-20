<?php

namespace Aurora\AAuth\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationNodeAuthException extends Exception
{
    /**
     * Report the exception.
     *
     * @return void
     */
    public function report(): void
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param Request $request
     * @return Response|bool
     */
    public function render(Request $request): Response|bool
    {
        // return response(...);
        return false;
    }
}
