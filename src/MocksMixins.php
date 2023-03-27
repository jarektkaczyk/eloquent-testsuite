<?php

namespace Sofa\EloquentTestsuite;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Mockery\MockInterface;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\Generator;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionFunction;

/**
 * This trait simplifies writing expectations against Eloquent models & queries by enabling
 * mock to receive any magic method from provided mixins on top of its own, original methods.
 *
 * @method MockBuilder getMockBuilder($class)
 */
trait MocksMixins
{
    /**
     * Creates PHPUnit mock that mimics EloquentBuilder by allowing methods from QueryBuilder as well.
     * By default PHPUnit mocks don't allow that, for EloquentBuilder is not extending QueryBuilder,
     * and thus PHPUnit fails with error because the methods we try to mock or assert don't exist.
     *
     * @return MockObject|EloquentBuilder|QueryBuilder
     */
    protected function mockEloquentBuilder(): MockObject
    {
        return $this->createMixinMock(EloquentBuilder::class, QueryBuilder::class);
    }

    /**
     * PHPUnit mocks are strict and don't work well with Laravel magic via __call/__callStatic by default.
     * This method allows creating a mock that is not accepting just anything, but won't complain about
     * those methods not defined on the mocked class directly, eg. `whereColumn` on Eloquent Builder.
     *
     * @param string $mocked_class
     * @param string ...$mixins
     * @return MockObject
     */
    protected function createMixinMock(string $mocked_class, string ...$mixins): MockObject
    {
        $mockBuilder = $this->getMockBuilder($mocked_class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->allowMockingUnknownTypes();

        $mixinMockableMethods = $this->getMixinMockableMethods(array_merge([$mocked_class], $mixins));
        $onlyExistMethods = array_intersect(get_class_methods($mocked_class), $mixinMockableMethods);
        $otherMethods = array_diff($mixinMockableMethods, $onlyExistMethods);

        return $mockBuilder->onlyMethods($onlyExistMethods)
            ->addMethods($otherMethods)
            ->getMock();
    }


    protected function getMixinMockableMethods($classnames): array
    {
        $methods = array_map(function ($classname) {
            $defined_methods = (new Generator)->getClassMethods($classname);

            if (is_subclass_of($classname, Model::class)) {
                $defined_methods = array_map(function ($method) {
                    return preg_match('/^scope(\w+)$/', $method) ? lcfirst(substr($method, 5)) : $method;
                }, $defined_methods);
            }

            return $defined_methods;
        }, $classnames);

        return array_filter(array_unique(array_merge(...$methods)), function ($method) {
            return !str_starts_with($method, '__');
        });
    }

    /**
     * Shorthand for testing Query/Eloquent Builder callbacks, eg.:
     *
     * // SomeClass
     * public function something($query)
     * {
     *     $query->whereHas('relation', function ($q) {
     *         $q->where('this')->orHas('that');
     *     })
     * }
     *
     * // arrange & assert/expect [PHPUnit]
     * $query_mock = $this->createMock(Eloquent\Builder::class);
     * $query_mock->expects($this->once())->method('whereHas')->with('relation', $this->assertQueryCallback(function ($q) {
     *     $q_mock->expects($this->once())->method('where')->with('this')->willReturnSelf();
     *     $q_mock->expects($this->once())->method('orHas')->with('that')->willReturnSelf();
     * ));
     *
     * // One can request a Mockery mock instead of the defaul, PHPUnit's MockObject - simply typehint Mockery\MockInterface:
     * // arrange & assert/expect [Mockery]
     * $query_mock = Mockery::mock(Eloquent\Builder::class);
     * $query_mock->shouldReceive('whereHas')->once()->with('relation', $this->assertQueryCallback(function (MockInterface $q) {
     *     $q_mock->shouldReceive('where')->once()->with('this')->andReturnSelf();
     *     $q_mock->shouldReceive('orHas')->once()->with('that')->andReturnSelf();
     * ));
     *
     * // act
     * $some_class->something($query);
     *
     * @param Closure $expectations
     *
     * @return Callback|\Mockery\Matcher\Closure
     */
    protected function assertQueryCallback(Closure $expectations): \Mockery\Matcher\Closure|Callback
    {
        $query = $this->buildExpectedQueryMock($expectations);
        call_user_func($expectations, $query);

        $assertion = function ($tested_callback) use ($query) {
            call_user_func($tested_callback, $query);

            return true;
        };

        // Mockery is supported, but might not be actually required in the project,
        // so we have to rely on raw string classnames rather than Mockery::class.
        return is_a($query, 'Mockery\\MockInterface')
            ? call_user_func('Mockery::on', $assertion)
            : $this->callback($assertion);
    }

    /**
     * @param Closure $expectations
     * @return MockInterface|MockObject
     */
    protected function buildExpectedQueryMock(Closure $expectations): MockInterface|MockObject
    {
        $ref = new ReflectionFunction($expectations);

        $expected_mock = $ref->getParameters()[0] ?? null;

        return $expected_mock !== null && $expected_mock->getType()->getName() === 'Mockery\\MockInterface'
            ? call_user_func('Mockery::mock')
            : $this->createMixinMock(EloquentBuilder::class, QueryBuilder::class);
    }
}
