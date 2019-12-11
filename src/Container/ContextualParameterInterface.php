<?php

declare(strict_types=1);

namespace O\Container;

interface ContextualParameterInterface
{
    /**
     * sets the parameter name for the contextual binding
     *
     * @param string $param
     * @return ContextualValueInterface
     */
    public function needs(string $param): ContextualValueInterface;
}
