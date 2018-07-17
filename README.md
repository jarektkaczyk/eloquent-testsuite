# Sofa/EloquentTestsuite

#### help yourself UNIT test Eloquent models

[![Downloads](https://poser.pugx.org/sofa/eloquent-testsuite/downloads)](https://packagist.org/packages/sofa/eloquent-testsuite) [![stable](https://poser.pugx.org/sofa/eloquent-testsuite/v/stable.svg)](https://packagist.org/packages/sofa/eloquent-testsuite)


## Usage
After you [installed](#installation) the package you can customize the thresholds publish configuration by calling:

```
$ php artisan vendor:publish --provider="Sofa\DbQueriesAlert\ServiceProvider"
```

and edit it in `config/db_queries_alert.php`:
```php
return [
    'error' => 100,
    'warning' => 50,
    'info' => 20,
];
```


Now you're good to go. The package will call `Log::error` (or `warning|info`) whenever your app hits given threshold. Catch this error in the monitoring service you're using for the application (or simply check your local `storage/logs/laravel[-YYYY-MM-DD].log` file).


## Installation

1. Add package to your project:
    ```
    path/to/your/app$ composer require sofa/eloquent-testsuite
    ```

2. Add `EloquentTestsuite` trait to your PHPUnit Test:
    ```php
    // app/Http/Kernel.php
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


#### Contribution

All contributions are welcome, PRs must be **PSR-2 compliant**.
