<?php

declare(strict_types=1);

namespace O\Container;

use Closure;

class ContextualParameter implements ContextualParameterInterface
{
    /**
     * @var string
     */
    protected $class;

    /**
     * @var Closure
     */
    protected $updater;

    /**
     * ContextualParameter constructor
     *
     * @param string $class
     * @param Closure $updater
     */
    public function __construct(string $class, Closure $updater)
    {
        $this->class = $class;
        $this->updater = $updater;
    }

    /**
     * {@inheritdoc}
     */
    public function needs(string $param): ContextualValueInterface
    {
        return new ContextualValue(
            $this->class,
            $param,
            $this->updater
        );
    }
}
