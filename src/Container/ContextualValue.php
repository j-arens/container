<?php

declare(strict_types=1);

namespace O\Container;

use Closure;

class ContextualValue implements ContextualValueInterface
{
    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $param;

    /**
     * @var Closure
     */
    protected $updater;

    /**
     * ContextualValue constructor
     *
     * @param string $class
     * @param string $param
     * @param Closure $updater
     */
    public function __construct(
        string $class,
        string $param,
        Closure $updater
    ) {
        $this->class = $class;
        $this->param = $param;
        $this->updater = $updater;
    }

    /**
     * {@inheritdoc}
     */
    public function give($value): void
    {
        ($this->updater)($this->class, $this->param, $value);
    }
}
