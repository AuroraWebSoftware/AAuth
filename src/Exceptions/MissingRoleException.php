<?php

namespace AuroraWebSoftware\AAuth\Exceptions;

use Exception;

class MissingRoleException extends Exception
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
     * @param  string|null  $message
     * @param  array  $guards
     * @param  string|null  $redirectTo
     * @return void
     */
    public function __construct($message = null, array $guards = [], $redirectTo = null)
    {
        $message = $message ?? $this->getDefaultMessage();
        parent::__construct($message);

        $this->guards = $guards;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Get default translated message
     *
     * @return string
     */
    protected function getDefaultMessage(): string
    {
        if (! function_exists('trans')) {
            return 'Current Role Missing.';
        }

        $translated = trans('aauth::exceptions.missing_role');

        // If translation not found, Laravel returns the key itself
        if ($translated === 'aauth::exceptions.missing_role') {
            return 'Current Role Missing.';
        }

        return $translated;
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
