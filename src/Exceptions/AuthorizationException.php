<?php

namespace Aurora\AAuth\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    /**
     * All of the guards that were checked.
     *
     * @var array
     */
    protected $guards;

    /**
     * The path the user should be redirected to.
     *
     * @var string|null
     */
    protected $redirectTo;

    /**
     * Create a new authentication exception.
     *
     * @param string $message
     * @param  array  $guards
     * @param  string|null  $redirectTo
     * @return void
     */
    public function __construct(string $message = 'Authorization Error', array $guards = [], $redirectTo = null)
    {
        parent::__construct($message);

        $this->guards = $guards;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Get the guards that were checked.
     *
     * @return array
     */
    public function guards()
    {
        return $this->guards;
    }

    /**
     * Get the path the user should be redirected to.
     *
     * @return string|null
     */
    public function redirectTo()
    {
        return $this->redirectTo;
    }
}
