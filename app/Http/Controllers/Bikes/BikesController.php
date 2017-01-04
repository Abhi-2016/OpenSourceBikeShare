<?php
namespace BikeShare\Http\Controllers\Bikes;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\Requests\RentRequest;
use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\Requests\ReturnRequest;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use BikeShare\Http\Requests;

class BikesController extends Controller
{

    protected $bikeRepo;

    public function __construct(BikesRepository $bikesRepository)
    {
        parent::__construct();
        $this->bikeRepo = $bikesRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bikes = $this->bikeRepo->with(['stand', 'user'])->all();

        return view('bikes.index', [
            'bikes' => $bikes
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        $bike = $this->bikeRepo->findByUuid($uuid);

        return view('bikes.show', [
            'bike' => $bike
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        //
    }


    public function rent(RentRequest $request, $uuid)
    {
        // TODO limits, credits
        $stand = app(StandsRepository::class)->findByUuid($request->get('stand'));
        $bike = $this->bikeRepo->findByUuid($uuid);
        $oldCode = $bike->current_code;
        $newCode = $this->bikeRepo->generateCode();

        $bike->status = BikeStatus::OCCUPIED;
        $bike->current_code = $newCode;
        $bike->stand()->dissociate($bike->stand);
        $bike->user()->associate(auth()->user());

        $bike->save();

        $rent = new Rent();
        $rent->status = RentStatus::OPEN;
        $rent->user()->associate(auth()->user());
        $rent->bike()->associate($bike);
        $rent->standFrom()->associate($stand);
        $rent->started_at = Carbon::now();
        $rent->old_code = $oldCode;
        $rent->new_code = $newCode;

        $rent->save();

        return redirect()->route('app.rents.index');
    }


    public function returnBike(ReturnRequest $request, $uuid)
    {
        // TODO only if i have rented this bike
        $stand = app(StandsRepository::class)->findByUuid($request->get('stand'));

        $bike = $this->bikeRepo->findByUuid($uuid);
        $bike->status = BikeStatus::FREE;
        $bike->stand()->associate($stand);
        $bike->save();

        $rent = $bike->rents()->where('rents.status', RentStatus::OPEN);
        $rent->standTo()->associate($stand);
        $rent->ended_at = Carbon::now();
        $rent->status = RentStatus::CLOSE;
        $rent->save();

    }
}
