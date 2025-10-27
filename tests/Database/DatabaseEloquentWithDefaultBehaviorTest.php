<?php

namespace Illuminate\Tests\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Exceptions\MissingForeignKeyException;

class DatabaseEloquentWithDefaultBehaviorTest extends TestCase
{
    /**
     * Setup the database schema.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->bootEloquent();
        $db->setAsGlobal();

        $dispatcher = new \Illuminate\Events\Dispatcher();
        Model::setEventDispatcher($dispatcher);

        $this->createSchema();
    }

    protected function createSchema(): void
    {
        $this->schema()->create('businesses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('wallets', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('holder_id')->nullable();
            $table->string('holder_type')->nullable();
            $table->integer('balance')->default(0);
            $table->timestamps();
        });

        $this->schema()->create('profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $this->schema()->create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $this->schema()->create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $this->schema()->create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('commentable_id');
            $table->string('commentable_type');
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->schema()->drop('businesses');
        $this->schema()->drop('wallets');
        $this->schema()->drop('profiles');
        $this->schema()->drop('users');
        $this->schema()->drop('orders');
        $this->schema()->drop('customers');
        $this->schema()->drop('comments');

        parent::tearDown();
    }

    public function testWithDefaultReturnsUnsavedModelInstance()
    {
        $business = Business::create(['name' => 'Acme Inc.']);

        $wallet = $business->wallet;

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertFalse($wallet->exists);
        $this->assertSame(0, $wallet->balance);
    }

    public function testSavingUnsavedDefaultModelWithNullableKeysSucceeds()
    {
        $business = new Business(['name' => 'Acme Inc.']);
        $business->save();

        $wallet = $business->wallet;
        $wallet->holder_id = null;
        $wallet->holder_type = null;

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertTrue($wallet->isDefaultInstance);

        $result = $wallet->save();
        $this->assertTrue($result);
    }

    public function testDefaultModelCanBeSavedIfForeignKeysAreSet()
    {
        $business = Business::create(['name' => 'Acme Inc.']);

        $wallet = $business->wallet;
        $wallet->holder_id = $business->id;
        $wallet->holder_type = Business::class;

        $this->assertTrue($wallet->save());
        $this->assertTrue($wallet->exists);
    }

    public function testNormalSavedModelHasNoIssues()
    {
        $business = Business::create(['name' => 'Acme Inc.']);

        $wallet = new Wallet([
            'balance' => 50,
            'holder_id' => $business->id,
            'holder_type' => Business::class,
        ]);

        $wallet->save();

        $this->assertTrue($wallet->exists);
        $this->assertEquals(50, $wallet->balance);
    }

    public function testWithoutAutoEagerLoadingWithDefaultDoesNotTriggerSaveIssue()
    {
        $business = BusinessWithoutAutoLoad::create(['name' => 'Manual Corp']);

        $wallet = $business->wallet;

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertFalse($wallet->exists);
        $this->assertSame(0, $wallet->balance);

        $wallet->save();
    }

    public function testBelongsToWithDefaultSucceedsWithNullableKeys()
    {
        $profile = new Profile();
        $profile->save();

        $user = $profile->user;

        $this->assertInstanceOf(TestUserModel::class, $user);
        $this->assertFalse($user->exists);

        $result = $user->save();
        $this->assertTrue($result);
    }

    public function testTouchingDefaultModelDoesNotThrowException()
    {
        $business = Business::create(['name' => 'Touchables']);

        $wallet = $business->wallet;

        $this->assertFalse($wallet->exists);
        $this->assertTrue($wallet->touch());
    }

    public function testNullableForeignKeysDoNotThrowException()
    {
        $business = new Business();
        $business->name = 'Test Business';
        $business->save();

        $wallet = $business->wallet;

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertTrue($wallet->isDefaultInstance);

        $result = $wallet->save();
        $this->assertTrue($result);
    }

    public function testNonNullableForeignKeysThrowException()
    {
        $customer = new Customer(['name' => 'Test Customer']);
        $customer->save();

        $order = $customer->order;

        $this->assertInstanceOf(Order::class, $order);
        $this->assertTrue($order->isDefaultInstance);

        $order->customer_id = null;

        $this->expectException(MissingForeignKeyException::class);
        $order->save();
    }

    public function testNonNullableForeignKeyCanBeSavedWhenSet()
    {
        $customer = new Customer();
        $customer->name = 'Test Customer';
        $customer->save();

        $order = $customer->order;

        $order->customer_id = $customer->id;

        $result = $order->save();
        $this->assertTrue($result);
    }

    public function testMorphOneWithoutWithDefaultReturnsNull()
    {
        $business = BusinessWithoutDefault::create(['name' => 'Legacy Ltd.']);

        $this->assertNull($business->wallet);
        $this->assertNull(optional($business->wallet)->balance);
    }

    public function testNonNullablePolymorphicForeignKeysThrowException()
    {
        $business = new Business();
        $business->name = 'Test Business';
        $business->save();

        $comment = $business->comment;

        $comment->commentable_id = null;
        $comment->commentable_type = null;

        $this->expectException(MissingForeignKeyException::class);
        $comment->save();
    }

    public function testNonNullablePolymorphicForeignKeysCanBeSavedWhenSet()
    {
        $business = new Business();
        $business->name = 'Test Business';
        $business->save();

        $comment = $business->comment;

        $this->assertEquals($business->id, $comment->commentable_id);
        $this->assertEquals(Business::class, $comment->commentable_type);

        $result = $comment->save();
        $this->assertTrue($result);
    }

    public function testMissingForeignKeyExceptionProvidesDetailedInformation()
    {
        $customer = new Customer(['name' => 'Test Customer']);
        $customer->save();

        $order = $customer->order;
        $order->customer_id = null;

        try {
            $order->save();
            $this->fail('Expected MissingForeignKeyException was not thrown');
        } catch (MissingForeignKeyException $e) {
            $this->assertInstanceOf(Order::class, $e->getModel());
            $this->assertContains('customer_id', $e->getMissingKeys());
            $this->assertStringContainsString('customer_id', $e->getMessage());
        }
    }

    public function testModelWithoutValidatesDefaultInstancesTraitWorksAsBefore()
    {
        $customer = new CustomerWithoutValidationTrait(['name' => 'Test Customer']);
        $customer->save();

        $order = $customer->order;

        $this->assertInstanceOf(OrderWithoutValidationTrait::class, $order);

        $this->assertEquals('pending', $order->status);

        $order->customer_id = null;

        $this->expectException(\Illuminate\Database\QueryException::class);
        $order->save();
    }

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection($connection = 'default')
    {
        return Eloquent::getConnectionResolver()->connection($connection);
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class Business extends Model
{
    protected $table = 'businesses';
    protected $guarded = [];

    public static function booted()
    {
        static::automaticallyEagerLoadRelationships();
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder')->withDefault([
            'balance' => 0,
        ]);
    }

    public function comment(): MorphOne
    {
        return $this->morphOne(Comment::class, 'commentable')->withDefault([
            'content' => 'Default comment',
        ]);
    }
}

class BusinessWithoutAutoLoad extends Model
{
    protected $table = 'businesses';
    protected $guarded = [];

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder')->withDefault([
            'balance' => 0,
        ]);
    }
}

class BusinessWithoutDefault extends Model
{
    protected $table = 'businesses';
    protected $guarded = [];

    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'holder');
    }
}

class Wallet extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\ValidatesDefaultInstances;

    protected $table = 'wallets';
    protected $guarded = [];

    public function holder()
    {
        return $this->morphTo();
    }
}

class Profile extends Model
{
    protected $table = 'profiles';
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUserModel::class, 'user_id')->withDefault([
            'name' => 'Guest',
        ]);
    }
}

class TestUserModel extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\ValidatesDefaultInstances;

    protected $table = 'users';
    protected $guarded = [];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

class Customer extends Model
{
    protected $table = 'customers';
    protected $guarded = [];

    public static function booted()
    {
        static::automaticallyEagerLoadRelationships();
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'customer_id')->withDefault([
            'status' => 'pending',
        ]);
    }
}

class Order extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\ValidatesDefaultInstances;

    protected $table = 'orders';
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}

class Comment extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\ValidatesDefaultInstances;

    protected $table = 'comments';
    protected $guarded = [];

    public function commentable()
    {
        return $this->morphTo();
    }
}

class OrderWithoutValidationTrait extends Model
{
    protected $table = 'orders';
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault([
            'name' => 'Default Customer',
        ]);
    }
}

class CustomerWithoutValidationTrait extends Model
{
    protected $table = 'customers';
    protected $guarded = [];

    public static function booted()
    {
        static::automaticallyEagerLoadRelationships();
    }

    public function order(): HasOne
    {
        return $this->hasOne(OrderWithoutValidationTrait::class, 'customer_id')->withDefault([
            'status' => 'pending',
        ]);
    }
}
