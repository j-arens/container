<?php

use O\Container\Container;
use O\Container\ContainerException;

describe('Container', function () {
    beforeEach(function () {
        $this->container = new Container();
    });

    describe('getting and setting a container instance', function () {
        it('throws an error if trying to get a container instance before one has been set', function () {
            $fn = function () {
                $this->container::getInstance();
            };
            expect($fn)->toThrow(new ContainerException('container instance not set'));
        });

        it('sets and gets a static container instance', function () {
            $this->container::setInstance($this->container);
            expect($this->container::getInstance())->toBe($this->container);
        });
    });

    describe('->create', function () {
        it('instantiates a class with no dependencies', function () {
            class Foo {}
            $result = $this->container->create(Foo::class);
            expect($result)->toBeAnInstanceOf(Foo::class);
        });

        it('instantiates a class with class dependencies', function () {
            class Bar {}
            class Baz {
                public $bar;
                public function __construct(Bar $bar)
                {
                    $this->bar = $bar;
                }
            }
            $result = $this->container->create(Baz::class);
            expect($result)->toBeAnInstanceOf(Baz::class);
            expect($result->bar)->toBeAnInstanceOf(Bar::class);
        });
    });

    describe('->bind', function () {
        it('binds a target to an implementation', function () {
            interface StorageInterface {}
            class S3 implements StorageInterface {}
            class StorageFacade
            {
                public $driver;
                public function __construct(StorageInterface $driver)
                {
                    $this->driver = $driver;
                }
            }
            $this->container->bind('StorageInterface', S3::class);
            $result = $this->container->create(StorageFacade::class);
            expect($result)->toBeAnInstanceOf(StorageFacade::class);
            expect($result->driver)->toBeAnInstanceOf(S3::class);
        });

        it('accepts a function to use as a creator', function () {
            class FooBar {
                public $num;
                public function __construct(int $num)
                {
                    $this->num = $num;
                }
            }
            class BarBaz {
                public $foobar;
                public function __construct(FooBar $foobar) {
                    $this->foobar = $foobar;
                }
            }
            $this->container->bind(FooBar::class, function (Container $cont) {
                return new FooBar(1);
            });
            $result = $this->container->create(BarBaz::class);
            expect($result)->toBeAnInstanceOf(BarBaz::class);
            expect($result->foobar)->toBeAnInstanceOf(FooBar::class);
            expect($result->foobar->num)->toBe(1);
        });
    });

    describe('->singleton', function () {
        it('creates a creator function that only ever creates a single instance', function () {
            class SingletonDep {}
            class Singleton {
                public $dep;
                public function __construct(SingletonDep $dep)
                {
                    $this->dep = $dep;
                }
            }
            class SingletonConsumerOne
            {
                public $single;
                public function __construct(Singleton $single)
                {
                    $this->single = $single;
                }
            }
            class SingletonConsumerTwo
            {
                public $single;
                public function __construct(Singleton $single)
                {
                    $this->single = $single;
                }
            }
            $this->container->singleton(Singleton::class);
            $s1 = $this->container->create(SingletonConsumerOne::class);
            $s2 = $this->container->create(SingletonConsumerTwo::class);
            expect($s1->single)->toBe($s2->single);
        });

        it('accepts a creator function to use when creating a singleton instance', function () {
            class Single {
                public $num;
                public function __construct(int $num)
                {
                    $this->num = $num;
                }
            }
            class SingleUser
            {
                public $single;
                public function __construct(Single $single)
                {
                    $this->single = $single;
                }
            }
            $this->container->singleton(Single::class, function (Container $cont) {
                return new Single(1);
            });
            $result = $this->container->create(SingleUser::class);
            expect($result->single->num)->toBe(1);
        });
    });

    describe('->when', function () {
        it('binds an interface to an implementation', function () {
            interface FoodInterface {}
            class Spaghetti implements FoodInterface {}
            class MyDinner
            {
                public $dinner;
                public function __construct(FoodInterface $dinner)
                {
                    $this->dinner = $dinner;
                }
            }
            $this->container
                ->when(MyDinner::class)
                ->needs(FoodInterface::class)
                ->give(Spaghetti::class);
            $result = $this->container->create(MyDinner::class);
            expect($result->dinner)->toBeAnInstanceOf(Spaghetti::class);
        });

        it('allows users to provide a creator function to be called when interface binding is resolved', function () {
            interface AnimalInterface {}
            class Monkey implements AnimalInterface {}
            class MyPet
            {
                public $pet;
                public function __construct(AnimalInterface $pet)
                {
                    $this->pet = $pet;
                }
            }
            $this->container
                ->when(MyPet::class)
                ->needs(AnimalInterface::class)
                ->give(function (Container $container) {
                    return $container->create(Monkey::class);
                });
            $result = $this->container->create(MyPet::class);
            expect($result->pet)->toBeAnInstanceOf(Monkey::class);
        });

        it('binds primitive parameters', function () {
            class PrimBinding
            {
                public $myString;
                public function __construct(string $myString)
                {
                    $this->myString = $myString;
                }
            }
            $this->container
                ->when(PrimBinding::class)
                ->needs('$myString')
                ->give('rofl');
            $result = $this->container->create(PrimBinding::class);
            expect($result->myString)->toBe('rofl');
        });

        it('allows users to provide a creator function to be called when primitive binding is resolved', function () {
            class ItemsFactory
            {
                public function getItems(): array
                {
                    return ['a' => 1, 'b' => 2];
                }
            }
            class FooWidget
            {
                public $items;
                public function __construct(array $items)
                {
                    $this->items = $items;
                }
            }
            $this->container
                ->when(FooWidget::class)
                ->needs('$items')
                ->give(function (Container $container) {
                    $factory = $container->create(ItemsFactory::class);
                    return $factory->getItems();
                });
            $result = $this->container->create(FooWidget::class);
            expect($result->items)->toBe(['a' => 1, 'b' => 2]);
        });
    });
});
