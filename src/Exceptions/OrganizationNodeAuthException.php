<?php

namespace AuroraWebSoftware\AAuth\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationNodeAuthException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        //
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response|bool
    {
        // return response(...);
        return false;
    }
}
