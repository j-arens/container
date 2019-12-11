<?php

declare(strict_types=1);

namespace O\Container;

interface ContainerInterface
{
    /**
     * creates a new class of the given name
     *
     * @param string $name
     * @return mixed
     */
    public function create(string $name);

    /**
     * bind a class/interface to a specific implementation
     *
     * @param string $name
     * @param mixed $implementation
     * @return void
     */
    public function bind(string $name, $implementation): void;

    /**
     * marks the given class as a signleton and stores its creator fn
     * passes a Container instance to the creator fn when its called
     *
     * @param string $name
     * @param callable $creator
     * @return void
     */
    public function singleton(string $name, callable $creator): void;

    /**
     * creates a contextual parameter binding
     *
     * @param string $name
     * @return ContextualParameterInterface
     */
    public function when(string $name): ContextualParameterInterface;
}
