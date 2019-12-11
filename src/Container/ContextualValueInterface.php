<?php

declare(strict_types=1);

namespace O\Container;

interface ContextualValueInterface
{
    /**
     * sets the value to be used when resolving the contextual binding
     *
     * @param mixed $value
     * @return void
     */
    public function give($value): void;
}
