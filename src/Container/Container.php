<?php

declare(strict_types=1);

namespace O\Container;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Closure;
use Exception;

class Container implements ContainerInterface
{
    /**
     * @var ContainerInterface
     */
    private static $instance;

    /**
     * @var ReflectionClass[]
     */
    private static $reflections = [];

    /**
     * @var callable[]
     */
    protected $creators = [];

    /**
     * @var array
     */
    protected $ctxBindings = [];

    /**
     * sets a static container instance
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setInstance(ContainerInterface $container): void
    {
        Container::$instance = $container;
    }

    /**
     * gets a static container instance
     *
     * @return ContainerInterface
     */
    public static function getInstance(): ContainerInterface
    {
        if (!(Container::$instance instanceof ContainerInterface)) {
            throw new ContainerException('container instance not set');
        }
        return Container::$instance;
    }

    /**
     * caches a reflection class instance
     *
     * @param ReflectionClass $ref
     * @return void
     */
    public static function setReflection(ReflectionClass $ref): void
    {
        Container::$reflections[$ref->getName()] = $ref;
    }

    /**
     * gets and caches reflection class instances
     *
     * @param string $class
     * @return ReflectionClass
     */
    public static function getReflection(string $class): ReflectionClass
    {
        if (!isset(Container::$reflections[$class])) {
            $ref = new ReflectionClass($class);
            Container::setReflection($ref);
        }
        return Container::$reflections[$class];
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name)
    {
        if (!isset($this->creators[$name])) {
            $this->creators[$name] = $this->makeCreator($name);
        }
        return $this->creators[$name]();
    }
    /**
     * {@inheritdoc}
     */
    public function bind(string $name, $implementation): void
    {
        $this->creators[$name] = function () use ($implementation) {
            if (is_callable($implementation)) {
                return $implementation($this);
            }
            return $this->create($implementation);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $name, callable $creator = null): void
    {
        $this->creators[$name] = function () use ($name, $creator) {
            static $instance;
            if (is_null($instance)) {
                if ($creator) {
                    $instance = $creator($this);
                } else {
                    // calling ->create here would cause an infinite recursion
                    $instance = $this->makeCreator($name)();
                }
            }
            return $instance;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function when(string $name): ContextualParameterInterface
    {
        return new ContextualParameter($name, function (string $class, string $param, $value) {
            $this->ctxBindings[$class][$param] = $value;
        });
    }

    /**
     * creates a closure that will instantiate and return the given class
     *
     * @param string $name
     * @return Closure
     */
    protected function makeCreator(string $name): Closure
    {
        $ref = self::getReflection($name);
        $cstr = $ref->getConstructor();
        if (is_null($cstr)) {
            return function () use ($ref) {
                return $ref->newInstance();
            };
        }
        $deps = $this->getDependencies($name, $cstr);
        return function () use ($ref, $deps) {
            return $ref->newInstance(...$deps);
        };
    }

    /**
     * collects the dependencies from a classe's constructor
     *
     * @param string $class
     * @param ReflectionMethod $cstr
     * @return array
     */
    protected function getDependencies(string $class, ReflectionMethod $cstr): array
    {
        return array_map(function (ReflectionParameter $param) use ($class) {
            try {
                return $this->resolveClassParameter($class, $param);
            } catch (Exception $e) {
                // tried to resolve a class/interface that does not exist
                // theres nothing else we can do from here
                if (preg_match('/does not exist/', $e->getMessage())) {
                    throw $e;
                }
                return $this->resolvePrimitiveParameter($class, $param);
            }
        }, $cstr->getParameters());
    }

    /**
     * attempts to resolve class/interface parameters
     *
     * @param string $dependant
     * @param ReflectionParameter $param
     * @return mixed
     */
    protected function resolveClassParameter(string $dependant, ReflectionParameter $param)
    {
        // getClass method will throw an exception if the parameter is typehinted
        // as a class/interface but does not exist, or it may return null for any
        // other type of parameter
        $class = $param->getClass();
        if (is_null($class)) {
            throw new ContainerException(
                "could not resolve class parameter {$param->getName()} for dependant $dependant"
            );
        }
        $classname = $class->getName();
        // check for contextually bound class/interface parameter
        if (isset($this->ctxBindings[$dependant][$classname])) {
            $concrete = $this->ctxBindings[$dependant][$classname];
            return is_callable($concrete) ? $concrete($this) : $this->create($concrete);
        }
        // return new class instance resolved through the container
        return $this->create($classname);
    }

    /**
     * attempts to resolve primitive parameters
     *
     * @param string $dependant
     * @param ReflectionParameter $param
     * @return mixed
     */
    protected function resolvePrimitiveParameter(string $dependant, ReflectionParameter $param)
    {
        $pname = '$' . $param->getName();
        if (isset($this->ctxBindings[$dependant][$pname])) {
            $value = $this->ctxBindings[$dependant][$pname];
            return is_callable($value) ? $value($this) : $value;
        }
        // unresolveable parameter
        throw new ContainerException(
            "could not resolve parameter $pname for dependant $dependant"
        );
    }
}
