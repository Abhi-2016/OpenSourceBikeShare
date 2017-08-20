<?php
namespace Test\Feature;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\WhereIsBike;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function free_notification_contains_valid_data()
    {
        $emptyStands = factory(Stand::class, 2)->create();
        $standsWithBike = factory(Stand::class, 3)
            ->create()->each(function ($stand){
                $stand->bikes()->save(factory(Bike::class)->make());
            });
        $standsWithTwoBikes = factory(Stand::class, 4)
            ->create()
            ->each(function ($stand){
                factory(Bike::class, 2)
                    ->make()
                    ->each(function ($bike) use ($stand){
                    $stand->bikes()->save($bike);
                });
            });

        $notificationText = app(Free::class)->text();

        foreach ($emptyStands as $stand){
            self::assertContains($stand->name, $notificationText);
            // it doesn't contain
            self::assertNotContains($stand->name . ':', $notificationText);
        }

        foreach ($standsWithBike as $stand){
            self::assertContains($stand->name . ":1", $notificationText);
        }

        foreach ($standsWithTwoBikes as $stand){
            self::assertContains($stand->name . ":2", $notificationText);
        }
    }

    /** @test */
    public function where_notification_contains_stand_name_if_bike_is_free()
    {
        $stand = create(Stand::class);
        $bike = $stand->bikes()->save(make(Bike::class));

        $notifText = (new WhereIsBike($bike))->text();

        self::assertContains((string) $bike->bike_num, $notifText);
        self::assertContains($stand->name, $notifText);
    }

    /** @test */
    public function where_notification_contains_user_name_and_phone_if_bike_is_occupied()
    {
        $user = create(User::class, ['limit' => 1, 'credit'=>100000]);
        $stand = create(Stand::class);
        $bike = $stand->bikes()->save(make(Bike::class));

        app(RentService::class)->rentBike($user, $bike);
        $notifText = (new WhereIsBike($bike))->text();

        self::assertContains((string) $bike->bike_num, $notifText);
        self::assertContains($user->name, $notifText);
        self::assertContains($user->phone_number, $notifText);
    }
}
