<?php
namespace BikeShare\Http\Services;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Note\Note;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\User\User;
use BikeShare\Domain\User\UsersRepository;
use Carbon\Carbon;

class RentService
{

    protected $oldCode;

    protected $standFrom;

    protected $standTo;

    protected $user;

    public $bike;

    public $rent;

    public $note;


    public function rentBike($user, Bike $bike)
    {
        $this->user = $user;
        $this->bike = $bike;
        $this->oldCode = $this->bike->current_code;
        $this->standFrom = $this->bike->stand;
        $this->bike->status = BikeStatus::OCCUPIED;
        $this->bike->current_code = Bike::generateBikeCode();
        $this->bike->stack_position = null;
        $this->bike->stand()->dissociate();
        $this->bike->user()->associate($user);
        $this->bike->save();

        return $this;
    }


    public function returnBike($user, Stand $stand, Rent $rent)
    {
        $this->user = $user;
        $this->rent = $rent;
        $this->bike = $rent->bike;
        $this->standTo = $stand;
        $this->bike->stack_position = $this->standTo->getTopPosition() + 1;
        $this->bike->status = BikeStatus::FREE;
        $this->bike->stand()->associate($stand);
        $this->bike->save();

        return $this;
    }


    public function createRentLog()
    {
        $this->rent = new Rent();
        $this->rent->status = RentStatus::OPEN;
        $this->rent->user()->associate($this->user);
        $this->rent->bike()->associate($this->bike);
        $this->rent->standFrom()->associate($this->standFrom);
        $this->rent->started_at = Carbon::now();
        $this->rent->old_code = $this->oldCode;
        $this->rent->new_code = $this->bike->current_code;
        $this->rent->save();

        return $this;
    }


    public function closeRentLog()
    {
        $this->rent->ended_at = Carbon::now();
        $this->rent->duration = $this->rent->ended_at->diffInSeconds($this->rent->started_at);
        $this->rent->status = RentStatus::CLOSE;
        $this->rent->standTo()->associate($this->standTo);
        $this->rent->save();

        return $this;
    }


    public function updateCredit()
    {
        if (! app(AppConfig::class)->isCreditEnabled()) {
            return $this;
        }

        $realRentCredit = 0;
        $timeDiff = $this->rent->started_at->diffInMinutes($this->rent->ended_at);

        $freeTime = app(AppConfig::class)->getFreeTime();
        $rentCredit = app(AppConfig::class)->getRentCredit();
        if ($timeDiff > $freeTime) {
            $realRentCredit += $rentCredit;
        }

        // after first paid period, i.e. free_time * 2; if price_cycle enabled
        $priceCycle = app(AppConfig::class)->getPriceCycle();
        if ($priceCycle) {
            if ($timeDiff > $freeTime * 2) {
                $tempTimeDiff = $timeDiff - ($freeTime * 2);

                if ($priceCycle == 1) {             // flat price per cycle
                    $cycles = ceil($tempTimeDiff / app(AppConfig::class)->getFlatPriceCycle());
                    $realRentCredit += ($rentCredit * $cycles);
                } elseif ($priceCycle == 2) {       // double price per cycle
                    $cycles = ceil($tempTimeDiff / app(AppConfig::class)->getDoublePriceCycle());
                    $tmpCreditRent = $rentCredit;

                    for ($i = 1; $i <= $cycles; $i++) {
                        $multiplier = $i;
                        $doublePriceCycleCap = app(AppConfig::class)->getDoublePriceCycleCap();

                        if ($multiplier > $doublePriceCycleCap) {
                            $multiplier = $doublePriceCycleCap;
                        }
                        // exception for rent=1, otherwise square won't work:
                        if ($tmpCreditRent == 1) {
                            $tmpCreditRent = 2;
                        }
                        $realRentCredit += pow($tmpCreditRent, $multiplier);
                    }
                }
            }
        }

        if ($timeDiff > app(AppConfig::class)->getWatchersLongRental() * 60) {
            $realRentCredit += app(AppConfig::class)->getCreditLongRental();
        }

        $this->rent->credit = $realRentCredit;
        $this->rent->save();
        $this->user->credit -= $realRentCredit;
        $this->user->save();

        return $this;
    }


    public function addNote(Bike $bike, $note)
    {
        $this->note = $bike->notes()->create([
            'note' => $note,
            'user_id' => $this->user->id
        ]);

        // TODO notify Admins (email and sms if enabled)

        return $this;
    }


    public function checkTopOfStack($bike)
    {
        return $bike->stand->getTopPosition() == $bike->stack_position;
    }

    public function checkLongRent()
    {
        $rents = app(RentsRepository::class)->findWhere(['status' => RentStatus::OPEN]);

        foreach ($rents as $rent) {
            if ($rent->started_at->addHours(app(AppConfig::class)->getWatchersLongRental())->isPast()) {
                if (app(AppConfig::class)->isNotifyUser()) {
                    // TODO send notification (sms, email ?) to user about long rental
                }
            }
        }

        // TODO send notification report to admins about all long rentals
    }


    public function checkManyRents(User $user = null)
    {
        $timeToMany = app(AppConfig::class)->getTimeToMany();
        $numberToMany = app(AppConfig::class)->getNumberToMany();
        if ($user) {
            $users = collect($user);
        } else {
            $users = app(UsersRepository::class)->findWhere([
                ['limit', '!=', 0]
            ]);
        }

        foreach ($users as $user) {
            $rents = $user->rents()->where('started_at', '>', Carbon::now()->subHour($timeToMany))->get();

            if (count($rents) >= ($user->limit + $numberToMany)) {
                // TODO prepare data for report
            }
        }

        // TODO notify admins (create report) about too many rentals of users
    }
}
