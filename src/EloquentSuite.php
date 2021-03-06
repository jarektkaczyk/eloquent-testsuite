<?php

namespace Sofa\EloquentTestsuite;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Database\Query\Builder;
use Mockery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

/**
 * @mixin TestCase
 */
trait EloquentSuite
{
    use MocksMixins;

    protected static $eloquent_relations = [
        'hasOne' => Relations\HasOne::class,
        'hasMany' => Relations\HasMany::class,
        'morphTo' => Relations\MorphTo::class,
        'morphOne' => Relations\MorphOne::class,
        'belongsTo' => Relations\BelongsTo::class,
        'morphMany' => Relations\MorphMany::class,
        'morphToMany' => Relations\MorphToMany::class,
        'morphedByMany' => Relations\MorphToMany::class,
        'belongsToMany' => Relations\BelongsToMany::class,
        'hasOneThrough' => Relations\HasOneThrough::class,
        'hasManyThrough' => Relations\HasManyThrough::class,
    ];

    /**
     * Prepare partial mock of Eloquent model with specified relation.
     * It sets expectation and returns the mock. Assertion should be made next:
     *
     * ```php
     * // MyModel - very simple relation, no customization, no query methods chained:
     * public function someRelation()
     * {
     *     return $this->belongsTo(RelatedModel::class);
     * }
     * // test
     * $model = $this->createRelationMock(MyModel::class, 'belongsTo', RelatedModel::class);
     * $this->assertRelation('belongsTo', $model->someRelation());
     *
     *
     * // MyModel - customization on the relation:
     * public function someRelation()
     * {
     *     return $this->belongsTo(RelatedModel::class, 'custom_fk', 'custom_pk');
     * }
     * // test
     * $model = $this->createRelationMock(MyModel::class, 'belongsTo', RelatedModel::class, 'custom_fk', 'custom_pk');
     * $this->assertRelation('belongsTo', $model->someRelation());
     * ```
     *
     * @param  string $model        Classname of the model under test
     * @param  string $relation     Relation method (belongsTo, hasMany etc)
     * @param  string|null $related Classname of the related model
     * @param  ...string $params    Optional params for the relation
     * @return \PHPUnit\Framework\MockObject\MockObject[]
     */
    public function createRelationMock(string $model, string $relation, string $related = null, ...$params) : MockObject
    {
        return $this->createRelationChainMock($model, $relation, $related, ...$params)[0];
    }

    /**
     * Prepare partial mock of Eloquent model with specified relation.
     * It sets expectation and returns the mock. Actual relation method should be called next.
     * Returns array [$model, $relation_query] to assert against chained method on the relation.
     *
     * ```php
     * // MyModel - chained query methods:
     * public function someRelation()
     * {
     *     return $this->belongsTo(RelatedModel::class)->where('active', true)->latest();
     * }
     * // test
     *
     * // $model will be PHPUnit's own \PHPUnit\Framework\MockObject\MockObject which suits our requirement for this test.
     * // $relation will be \Mockery\MockInterface as this is what works best to make assertions on eloquent magic.
     * [$model, $relation] = $this->createRelationChainMock(MyModel::class, 'belongsTo', RelatedModel::class);
     *
     * $relation->shouldReceive('where')->once()->with('active', true)->andReturnSelf();
     * $relation->shouldReceive('latest')->once()->andReturnSelf();
     *
     * $this->assertRelation('belongsTo', $model->someRelation());
     * ```
     *
     * @param  string $model        Classname of the model under test
     * @param  string $relation     Relation method (belongsTo, hasMany etc)
     * @param  string|null $related Classname of the related model
     * @param  ...string $params    Optional params for the relation
     * @return \PHPUnit\Framework\MockObject\MockObject[]|\Mockery\MockInterface[]
     */
    public function createRelationChainMock(string $model, string $relation, string $related = null, ...$params) : array
    {
        if (!array_key_exists($relation, self::$eloquent_relations)) {
            $this->fail('Unknown relation provided: ' . $relation);
        }

        // Here we create partial mock of the model in order to fake only the built-in relation
        // method. This will let us test our own code in the model, but won't touch internal
        // implementation of the framework. The latter slows down and makes tests brittle.
        $model_mock = $this->createPartialMock($model, [$relation]);
        $relation_mock = Mockery::mock(self::$eloquent_relations[$relation]);

        // We don't expect any custom params for the relation. Eloquent will use defaults.
        if (empty($params)) {
            $model_mock->expects($this->once())
                ->method($relation)
                ->with($related, $this->isEmpty()) // don't expect any parameters
                ->willReturn($relation_mock);
        }

        // Custom relation params were passed, so we set expectation against them:
        else {
            $model_mock->expects($this->once())
                ->method($relation)
                ->with($related, ...$params)
                ->willReturn($relation_mock);
        }

        return [$model_mock, $relation_mock];
    }

    /**
     * Assert that Eloquent relation is defined as expected on the model.
     *
     * @param  string $relation
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $actual
     * @param  string $message
     * @return void
     */
    public static function assertRelation(string $relation, Relations\Relation $actual, $message = '')
    {
        if (array_key_exists($relation, self::$eloquent_relations)) {
            $relation = self::$eloquent_relations[$relation];
        }

        if (!in_array($relation, self::$eloquent_relations)) {
            self::fail('Unknown relation provided: ' . $relation);
        }

        self::assertInstanceOf(
            $relation,
            $actual,
            $message ?: 'Possible reasons:' .
                        ' relation not defined on the model,' .
                        ' unexpected query methods chained on relation object,' .
                        ' missing return statement.'
        );
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $column
     * @param string $value
     * @throws RuntimeException
     */
    public static function assertScopeFilters(Model $model, string $scope, string $column, string $value): void
    {
        self::runScopeAssertion($model, $scope, 'where', $column, $value);
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $column
     * @throws RuntimeException
     */
    public static function assertScopeFiltersNull(Model $model, string $scope, string $column): void
    {
        self::runScopeAssertion($model, $scope, 'whereNull', $column, null);
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $column
     * @throws RuntimeException
     */
    public static function assertScopeFiltersNotNull(Model $model, string $scope, string $column): void
    {
        self::runScopeAssertion($model, $scope, 'whereNotNull', $column, null);
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $column
     * @param array $values
     * @throws RuntimeException
     */
    public static function assertScopeFiltersIn(Model $model, string $scope, string $column, array $values): void
    {
        self::runScopeAssertion($model, $scope, 'whereIn', $column, $values);
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $column
     * @param array $values
     * @throws RuntimeException
     */
    public static function assertScopeFiltersNotIn(Model $model, string $scope, string $column, array $values): void
    {
        self::runScopeAssertion($model, $scope, 'whereNotIn', $column, $values);
    }

    /**
     * @param Model $model
     * @param string $scope
     * @param string $filterMethod
     * @param string $column
     * @param $values
     * @throws RuntimeException
     */
    public static function runScopeAssertion(Model $model, string $scope, string $filterMethod, string $column, $values): void
    {
        if (!is_a(static::class, TestCase::class, true)) {
            throw new RuntimeException('Calling class must be an instance of ' . TestCase::class);
        }
        $parameters = array_filter([$column, $values]);
        $test = new static();
        $query = $test->createQueryMock($filterMethod, ...$parameters);
        $scopeMethod = 'scope' . ucfirst($scope);
        $model->$scopeMethod($query);
    }

    /**
     * @param string $filterMethod
     * @param mixed ...$parameters
     * @return Builder
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\MockObject\RuntimeException
     * @throws ReflectionException
     */
    public function createQueryMock(string $filterMethod, ...$parameters): Builder
    {
        $query = $this->createMock(Builder::class);
        $query->expects(self::once())
            ->method($filterMethod)
            ->with(...$parameters)
            ->willReturnSelf();

        return $query;
    }
}
