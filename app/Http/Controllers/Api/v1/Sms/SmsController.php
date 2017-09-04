<?php

namespace BikeShare\Http\Controllers\Api\v1\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Sms\Sms;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeDoesNotExistException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotFreeException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotOnTopException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\Exceptions\LowCreditException;
use BikeShare\Http\Services\Rents\Exceptions\MaxNumberOfRentsException;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\ReturnException;
use BikeShare\Http\Services\Rents\Exceptions\StandDoesNotExistException;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Http\Services\Sms\SmsUtils;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\BikeReturnedSuccess;
use BikeShare\Notifications\Sms\BikeToReturnNotRentedByMe;
use BikeShare\Notifications\Sms\NoBikesRented;
use BikeShare\Notifications\Sms\NoBikesUntagged;
use BikeShare\Notifications\Sms\NoNotesDeleted;
use BikeShare\Notifications\Sms\NoteForBikeSaved;
use BikeShare\Notifications\Sms\NoteForStandSaved;
use BikeShare\Notifications\Sms\NoteTextMissing;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRentedSuccess;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
use BikeShare\Notifications\Sms\StandInfo;
use BikeShare\Notifications\Sms\StandListBikes;
use BikeShare\Notifications\Sms\TagForStandSaved;
use BikeShare\Notifications\Sms\Unauthorized;
use BikeShare\Notifications\Sms\UnknownCommand;
use BikeShare\Notifications\Sms\WhereIsBike;
use BikeShare\Notifications\SmsNotification;
use Dingo\Api\Routing\Helpers;
use Gate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Validator;

class SmsController extends Controller
{
    use Helpers;

    /**
     * @var SmsRequestContract
     */
    private $smsRequest;

    /**
     * @var AppConfig
     */
    private $appConfig;

    /**
     * @var StandsRepository
     */
    private $standsRepo;

    /**
     * @var BikesRepository
     */
    private $bikeRepo;

    /**
     * @var RentService
     */
    private $rentService;

    public function __construct()
    {
        $this->smsRequest = app(SmsRequestContract::class);
        $this->appConfig = app(AppConfig::class);
        $this->standsRepo = app(StandsRepository::class);
        $this->bikeRepo = app(BikesRepository::class);
        $this->rentService = app(RentService::class);
    }

    public function receive(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->smsRequest->rules()
        );

        if ($validator->fails()){
            $errorMsg = implode(' ', $validator->messages()->all());
            $this->response->errorBadRequest($errorMsg);
        }

        $receivedSms = $this->smsRequest->smsModel($request);

        if (!$receivedSms->sender){
            activity()
                ->withProperties($request->all())
                ->log("Sms from unregistered number");
            $this->response->errorBadRequest('Unregistered number');
        }

        $receivedSms->save();
        $this->parseCommand($receivedSms);
        return $this->response->noContent();
    }

    protected function parseCommand(Sms $sms)
    {
        $args = SmsUtils::parseSmsArguments($sms->sms_text);
        $command = $args[0];

        try {
            switch($command)
            {
                case "HELP":
                    $this->helpCommand($sms);
                    break;

                case "CREDIT":
                    if (!$this->appConfig->isCreditEnabled()){
                        $this->unknownCommand($sms, $command);
                    } else {
                        $this->creditCommand($sms);
                    }
                    break;

                case "FREE":
                    $this->freeCommand($sms);
                    break;

                case "RENT":
                    if (count($args) < 2){
                        $this->invalidArgumentsCommand($sms, "with bike number: RENT 47");
                    } else {
                        $this->rentCommand($sms, $this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;

                case "RETURN":
                    if (count($args) < 3){
                        $this->invalidArgumentsCommand($sms, "with bike number and stand name: RENT 47 RACKO");
                    } else {
                        $this->returnCommand($sms, $this->bikeRepo->getBikeOrFail($args[1]), $this->standsRepo->getStandOrFail($args[2]));
                    }
                    break;


//            case "FORCERENT":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." FORCERENT 47");
//                rent($sms->Number(),$args[1],TRUE);
//                break;
//            case "FORCERETURN":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),3,_('with bike number and stand name:')." FORCERETURN 47 RACKO");
//                returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())),TRUE);
//                break;


                case "WHERE":
                case "WHO":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, "with bike number: WHERE 47");
                    } else {
                        $this->whereCommand($sms, $this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;

                case "INFO":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, "with stand name: INFO RACKO");
                    } else {
                        $this->infoCommand($sms, $this->standsRepo->getStandOrFail($args[1]));
                    }
                    break;

                case "NOTE":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, 'with bike number/stand name and problem description: NOTE 47 Flat tire on front wheel');
                    } else {
                        $this->noteCommand($sms, $args[1]);
                    }
                    break;

                case "TAG":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, 'with stand name and problem description: TAG MAINSQUARE vandalism');
                    } else {
                        $this->tagCommand(
                            $sms,
                            $this->standsRepo->getStandOrFail($args[1]),
                            SmsUtils::parseNoteFromSms($sms->sms_text, $command)
                        );
                    }
                    break;

                case "DELNOTE":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, "with bike number/stand name and optional pattern. All messages or notes matching pattern will be deleted: DELNOTE 47 wheel");
                    } else {
                        $this->deleteNoteCommand(
                            $sms,
                            $args[1],
                            SmsUtils::parseNoteFromSms($sms->sms_text, $command)
                        );
                    }
                    break;


                case "UNTAG":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, "with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand: UNTAG SAFKO1 pohoda");
                    } else {
                        $this->untagCommand(
                            $sms,
                            $this->standsRepo->getStandOrFail($args[1]),
                            SmsUtils::parseNoteFromSms($sms->sms_text, $command)
                        );
                    }
                    break;
                case "LIST":
                    if (count($args) < 2) {
                        $this->invalidArgumentsCommand($sms, "with stand name: LIST RACKO");
                    } else {
                        $this->listCommand($sms, $this->standsRepo->getStandOrFail($args[1]));
                    }
                    break;

//                //checkUserPrivileges($sms->Number()); //allowed for all users as agreed
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name:')." LIST RACKO");
//                validateReceivedSMS($sms->Number(),count($args),2,"with stand name: LIST RACKO");
//                listBikes($sms->Number(),$args[1]);
//                break;
//            case "ADD":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),3,_('with email, phone, fullname:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
//                add($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
//                break;
//            case "REVERT":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." REVERT 47");
//                revert($sms->Number(),$args[1]);
//                break;
//            //    case "NEAR":
//            //    case "BLIZKO":
//            //	near($sms->Number(),$args[1]);
//            case "LAST":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." LAST 47");
//                last($sms->Number(),$args[1]);
//                break;
                default:
                    $this->unknownCommand($sms, $args[0]);
                    break;
            }

        }
        catch (BikeDoesNotExistException $e)
        {
            $sms->sender->notify(new BikeDoesNotExist($e->bikeNumber));
        }
        catch (StandDoesNotExistException $e)
        {
            $sms->sender->notify(new StandDoesNotExist($e->standName));
        }
        catch (AuthorizationException $e)
        {
            $sms->sender->notify(new Unauthorized);
        }
    }

    private function helpCommand(Sms $sms)
    {
        $sms->sender->notify(new Help($sms->sender, $this->appConfig));
    }

    private function unknownCommand(Sms $sms, $command)
    {
        $sms->sender->notify(new UnknownCommand($command));
    }

    private function creditCommand(Sms $sms)
    {
        $sms->sender->notify(new Credit($this->appConfig, $sms->sender));
    }

    private function freeCommand($sms)
    {
        $sms->sender->notify(new Free($this->standsRepo));
    }

    private function invalidArgumentsCommand($sms, $errorMsg)
    {
        $sms->sender->notify(new InvalidArgumentsCommand($errorMsg));
    }

    private function rentCommand(Sms $sms, Bike $bike)
    {
        $user = $sms->sender;
        try
        {
            $rent = $this->rentService->rentBike($user, $bike);
            $user->notify(new BikeRentedSuccess($rent));
        }
        catch (LowCreditException $e)
        {
            $user->notify(new RechargeCredit($this->appConfig, $e->userCredit, $e->userCredit));
        }
        catch (BikeNotFreeException $e)
        {
            $user->notify(new BikeAlreadyRented($user, $bike));
        }
        catch (MaxNumberOfRentsException $e)
        {
            $user->notify(new RentLimitExceeded($e->userLimit, $e->currentRents));
        }
        catch (BikeNotOnTopException $e)
        {
            $user->notify(new BikeNotTopOfStack($bike, $e->topBike));
        }
        catch (RentException $e){
            throw $e; // unknown type, rethrow
        }
    }

    private function returnCommand(Sms $sms, Bike $bike, Stand $stand)
    {
        $user = $sms->sender;

        if ($this->bikeRepo->bikesRentedByUserCount($user) == 0){
            $user->notify(new NoBikesRented);
            return;
        }

        $noteText = SmsUtils::parseNoteFromReturnSms($sms->sms_text);

        try {
            $rent = $this->rentService->returnBike($user, $bike, $stand);
            if ($noteText){
                $this->rentService->addNoteToBike($bike, $user, $noteText);
            }
            $user->notify(new BikeReturnedSuccess($this->appConfig, $rent, $noteText));
        }
        catch (BikeNotRentedException | BikeRentedByOtherUserException $e )
        {
            $user->notify(new BikeToReturnNotRentedByMe($user, $bike, $this->bikeRepo->bikesRentedByUser($user)));
        }
        catch (ReturnException $e)
        {
            throw $e; // unknown type, rethrow
        }
    }

    private function whereCommand(Sms $sms, Bike $bike)
    {
        $sms->sender->notify(new WhereIsBike($bike));
    }

    private function infoCommand(Sms $sms, Stand $stand)
    {
        $sms->sender->notify(new StandInfo($stand));
    }

    private function noteCommand(Sms $sms, $param)
    {
        $param = trim($param);

        $noteText = SmsUtils::parseNoteFromSms($sms->sms_text, "note");

        if (!$noteText){
            $sms->sender->notify(new NoteTextMissing());
            return;
        }

        $this->bikeOrStandInvoke($sms, $param,
            function ($bikeNum) use ($sms, $noteText){
                $this->bikeNoteCommand($sms, $bikeNum, $noteText);
            }, function ($standName) use ($sms, $noteText){
                $this->standNoteCommand($sms, $standName, $noteText);
            }
        );
    }

    private function bikeNoteCommand(Sms $sms, Bike $bike, $noteText)
    {
        $this->rentService->addNoteToBike($bike, $sms->sender, $noteText);
        $sms->sender->notify(new NoteForBikeSaved($bike));
    }

    private function standNoteCommand(Sms $sms, Stand $stand, $noteText)
    {
        $this->rentService->addNoteToStand($stand, $sms->sender, $noteText);
        $sms->sender->notify(new NoteForStandSaved($stand));
    }

    private function tagCommand($sms, Stand $stand, $note)
    {
        if (!$note){
            $sms->sender->notify(new NoteTextMissing);
            return;
        }

        $this->rentService->addNoteToAllStandBikes($stand, $sms->sender, $note);
        $sms->sender->notify(new TagForStandSaved($stand));
    }

    private function deleteNoteCommand(Sms $sms, $bikeOrStand, $pattern)
    {
        if (!$pattern){
            $sms->sender->notify(new NoteTextMissing);
            return;
        }

        $this->bikeOrStandInvoke($sms, $bikeOrStand,
            function ($bike) use ($sms, $pattern){
                $this->bikeDeleteNoteCommand($sms, $bike, $pattern);
            }, function ($stand) use ($sms, $pattern){
                $this->standDeleteNoteCommand($sms, $stand, $pattern);
            }
        );
    }



    // Helper function to call method depending on parameter type (bike/stand)
    private function bikeOrStandInvoke(Sms $sms, $bikeOrStand, callable $callableBike, callable $callableStand)
    {
        if (preg_match("/^[0-9]*$/", $bikeOrStand))
        {
            $callableBike($this->bikeRepo->getBikeOrFail($bikeOrStand));
        }
        else if (preg_match("/^[A-Z]+[0-9]*$/i", $bikeOrStand))
        {
            $callableStand($this->standsRepo->getStandOrFail($bikeOrStand));
        }
        else {
            $sms->sender->notify(new class($bikeOrStand) extends SmsNotification{
                private $param;
                public function __construct($param)
                {
                    $this->param = $param;
                }
                public function smsText()
                {
                    return "Error in bike number / stand name specification:" . $this->param;
                }
            });
        }
    }

    private function bikeDeleteNoteCommand(Sms $sms, Bike $bike, $pattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromBike($bike, $sms->sender, $pattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $sms->sender->notify(new NoNotesDeleted($sms->sender, $pattern, $bike));
        }
    }

    private function standDeleteNoteCommand($sms, Stand $stand, $pattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromStand($stand, $sms->sender, $pattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $sms->sender->notify(new NoNotesDeleted($sms->sender, $pattern, null, $stand));
        }
    }

    private function untagCommand($sms, Stand $stand, $pattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromAllStandBikes($stand, $sms->sender, $pattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $sms->sender->notify(new NoBikesUntagged($pattern, $stand));
        }
    }

    private function listCommand(Sms $sms, Stand $stand)
    {
        $sms->sender->notify(new StandListBikes($stand));
    }
}
