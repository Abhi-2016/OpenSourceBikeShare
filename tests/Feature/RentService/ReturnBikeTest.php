<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\RentMethod;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\ReturnMethod;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\RentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ReturnBikeTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @var RentService
     */
    private $rentService;

    /**
     * @var AppConfig
     */
    private $appConfig;

    protected function setUp()
    {
        parent::setUp();
        $this->rentService = app(RentService::class);
        $this->appConfig = app(AppConfig::class);
    }

    /** @test */
    public function returning_non_occupied_bike_throws_exception()
    {
        // Arrange
        $user = create(User::class);
        list($stand, $bike) = standWithBike();

        // Assert
        $this->expectException(BikeNotRentedException::class);

        // Act
        $this->rentService->returnBike($user, $bike, $stand, ReturnMethod::WEB);
    }

    /** @test */
    public function returning_bike_rented_by_other_user_throws_exception()
    {
        // Arrange
        $userWithoutBike = create(User::class);
        $userWithBike = userWithResources();
        list($stand, $bike) = standWithBike();
        $standTo = create(Stand::class);
        $this->rentService->rentBike($userWithBike, $bike, RentMethod::WEB);

        // Assert
        $this->expectException(BikeRentedByOtherUserException::class);

        // Act
        $this->rentService->returnBike($userWithoutBike, $bike, $standTo, ReturnMethod::WEB);
    }

    /** @test */
    public function rent_and_return_bike_ok()
    {
        // Arrange
        $user = userWithResources();
        list($stand, $bike) = standWithBike();
        $standTo = create(Stand::class);

        // Act
        $rent = $this->rentService->rentBike($user, $bike, RentMethod::WEB);

        // Assert
        self::assertEquals(BikeStatus::OCCUPIED, $bike->status);
        self::assertEquals($user->id, $rent->user->id);
        self::assertEquals($bike->id, $rent->bike->id);
        self::assertEquals(RentStatus::OPEN, $rent->status);

        // Act
        $rentAfterReturn = $this->rentService->returnBike($user, $bike, $standTo, ReturnMethod::WEB);

        // Assert
        self::assertEquals(BikeStatus::FREE, $bike->status);
        self::assertEquals($standTo->id, $bike->stand->id);
        self::assertNull($bike->user);
        self::assertEquals(RentStatus::CLOSE, $rentAfterReturn->status);
    }
}
