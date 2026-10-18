<?php

namespace App\Exceptions;

/**
 * Thrown by StockGuard when a sale or purchase would break stock or serial integrity.
 * Carries one message per problem so the form can list them all.
 */
class StockGuardException extends \RuntimeException
{
    /** @var string[] */
    protected array $problems;

    public function __construct(array $problems)
    {
        $this->problems = array_values($problems);
        parent::__construct(implode(' ', $this->problems));
    }

    /** @return string[] */
    public function problems(): array
    {
        return $this->problems;
    }
}
