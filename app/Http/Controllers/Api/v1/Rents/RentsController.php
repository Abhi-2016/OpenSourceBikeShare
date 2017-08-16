<?php

namespace BikeShare\Http\Controllers\Api\v1\Rents;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\Events\BikeWasReturned;
use BikeShare\Domain\Rent\Events\RentWasClosed;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Rent\Requests\CreateRentRequest;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\RentExceptionType as ER;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\NoteCreated;
use Illuminate\Http\Request;
use Notification;

class RentsController extends Controller
{
    protected $rentRepo;

    public function __construct(RentsRepository $repository)
    {
        $this->rentRepo = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $rents = $this->rentRepo->all();

        return $this->response->collection($rents, new RentTransformer());
    }

    public function active()
    {
        $rents = $this->rentRepo->findWhere(['status' => RentStatus::OPEN]);

        return $this->response->collection($rents, new RentTransformer());
    }

    public function history()
    {
        $rents = $this->rentRepo->findWhere(['status' => RentStatus::CLOSE]);

        return $this->response->collection($rents, new RentTransformer());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateRentRequest|Request $request
     *
     * @param RentService $rentService
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateRentRequest $request, RentService $rentService)
    {
        if (! $bike = app(BikesRepository::class)->findByUuid($request->get('bike'))) {
            $this->response->errorNotFound('Bike not found!');
        }

        $rent = null;
        try {
            // TODO check too many, i don't understand yet
            $rent = $rentService->rentBike($this->user, $bike);
        } catch (RentException $e){
            switch ($e->type){
                case ER::BIKE_NOT_FREE:
                    $this->response->errorNotFound('Bike is not free!');
                    break;
                case ER::MAXIMUM_NUMBER_OF_RENTS:
                    $this->response->errorBadRequest('You reached the maximum number of rents!');
                    break;
                case ER::BIKE_NOT_ON_TOP:
                    $this->response->errorBadRequest('Bike is not on the top!');
                    break;
                case ER::LOW_CREDIT:
                    $this->response->errorBadRequest('You do not have required credit for rent bike!');
                    break;
                default:
                    // unknown type, rethrow
                    throw $e;
            }
        }
        return $this->response->item($rent, new RentTransformer());
    }

    /**
     * Display the specified resource.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        if (! $rent = $this->rentRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Rent not found');
        }

        return $this->response->item($rent, new RentTransformer);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    public function close(Request $request, $uuid, RentService $rentService)
    {
        if (! $rent = $this->rentRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Rent not found!');
        }

        if ($rent->status != RentStatus::OPEN) {
            $this->response->errorBadRequest('Rent is not active!');
        }

        $userBikes = $this->user->bikes()->get();
        if (! $userBikes || ! $userBikes->contains($rent->bike)) {
            $this->response->errorBadRequest('You do not have rent this bike!');
        }

        if (! $stand = app(StandsRepository::class)->findByUuid($request->get('stand'))) {
            return $this->response->errorNotFound('Stand not found!');
        }

        $rentServiceObj = $rentService->returnBike($this->user, $stand, $rent)->closeRentLog()->updateCredit();

        if ($request->has('note')) {
            $rentServiceObj = $rentService->addNote($rent->bike, $request->get('note'));
            $users = app(UsersRepository::class)->getUsersWithRole('admin')->get();
            Notification::send($users, new NoteCreated($rentServiceObj->note));
        }

        event(new RentWasClosed($rentServiceObj->rent));
        event(new BikeWasReturned($rentServiceObj->bike, $stand));

        return $this->response->item($rentServiceObj->rent, new RentTransformer());
    }
}
