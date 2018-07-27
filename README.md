# Sofa/EloquentTestsuite

#### help yourself UNIT test Eloquent models

[![Downloads](https://poser.pugx.org/sofa/eloquent-testsuite/downloads)](https://packagist.org/packages/sofa/eloquent-testsuite) [![stable](https://poser.pugx.org/sofa/eloquent-testsuite/v/stable.svg)](https://packagist.org/packages/sofa/eloquent-testsuite)


## Usage

Add `EloquentTestsuite` trait to your PHPUnit Test:

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


## Installation

Add package to your project:

```
path/to/your/app$ composer require sofa/eloquent-testsuite
```

#### Contribution

All contributions are welcome, PRs must be **PSR-2 compliant**.
