# Sofa/EloquentTestsuite

#### help yourself UNIT test Eloquent models

[![Downloads](https://poser.pugx.org/sofa/eloquent-testsuite/downloads)](https://packagist.org/packages/sofa/eloquent-testsuite) [![stable](https://poser.pugx.org/sofa/eloquent-testsuite/v/stable.svg)](https://packagist.org/packages/sofa/eloquent-testsuite)


## Usage

Add `EloquentTestsuite` trait to your PHPUnit Test:


### Testing Relations

```php
class SomeModelTest extends \PHPUnit\Framework\TestCase
{
    use EloquentSuite;

    /** @test */
    public function user_belongs_to_organization()
    {
        $user = $this->createRelationMock(User::class, 'belongsTo', Organization::class);
        $this->assertRelation('belongsTo', $user->organization());
    }

    /** @test */
    public function user_has_many_customers()
    {
        // $relation is a Mockery mock
        [$user, $relation] = $this->createRelationChainMock(User::class, 'hasMany', Customer::class);

        $relation->shouldReceive('active')->once()->andReturnSelf();
        $relation->shouldReceive('latest')->once()->andReturnSelf();

        $this->assertRelation('hasMany', $user->customers());
    }
}
```

### Testing Scope Definitions

Testing scope definitions provide a shorthand assertion to verify that a given scope
exists on the model, and it performs the expected filtering on the model results.

The available assertion methods are:

```php
assertScopeFilters(Model $model, string $scope, string $column, string $value)
assertScopeFiltersNull(Model $model, string $scope, string $column)
assertScopeFiltersNotNull(Model $model, string $scope, string $column)
assertScopeFiltersIn(Model $model, string $scope, string $column, array $values)
assertScopeFiltersNotIn(Model $model, string $scope, string $column, array $values)
```

```php
class SomeModelTest extends \PHPUnit\Framework\TestCase
{
    use EloquentSuite;

    public function testScopePublished()
    {
        $article = new Article();
        $this->assertScopeFilters($article, 'published', 'status', 1);
    }

    public function testScopeAvailable()
    {
        $article = new Article();
        $this->assertScopeFiltersNull($article, 'available', 'deleted_at');
    }
    
    public function testScopeDeleted()
    {
        $article = new Article();
        $this->assertScopeFiltersNotNull($article, 'deleted', 'deleted_at');
    }
}
```

For more advanced assertions you can just use `EloquentSuite` to create the query
and then do custom assertions on the method and parameters. For instance take
this scope definition that does a raw where query.

```php
    public function scopeValidOnDate($query, $date)
    {
        return $query->whereRaw('? between valid_from and valid_to', [$date]);
    }
```

You can unit test this definition using the following syntax:

```php 
    public function testScopeValidOnDate()
    {
        $assertion = $this->stringContains('valid_from');
        $params = ['2020-10-10'];
        $query = $this->createQueryMock('whereRaw', $assertion, $params);
        $ticket = new Ticket();
        $ticket->scopeValidOnDate($query, '2020-10-10');
    }
```


## Installation

Add package to your project:

```
path/to/your/app$ composer require sofa/eloquent-testsuite
```

#### Contribution

All contributions are welcome, PRs must be **PSR-2 compliant**.
