<?php

namespace BikeShare\Http\Controllers\Api\v1\Sms;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Sms\Sms;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotFreeException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotOnTopException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\Exceptions\LowCreditException;
use BikeShare\Http\Services\Rents\Exceptions\MaxNumberOfRentsException;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\ReturnException;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\NoBikesRented;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRented;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
use BikeShare\Notifications\Sms\UnknownCommand;
use Dingo\Api\Routing\Helpers;
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
        $args = self::parseSmsArguments($sms->sms_text);

        switch($args[0])
        {
            case "HELP":
                $this->helpCommand($sms);
                break;
            case "CREDIT":
                if (!$this->appConfig->isCreditEnabled()){
                    $this->unknownCommand($sms, $args[0]);
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
                    $this->rentCommand($sms, $args[1]);
                }
                break;
            case "RETURN":
                if (count($args) < 3){
                    $this->invalidArgumentsCommand($sms, "with bike number and stand name: RENT 47 RACKO");
                } else {
                    $this->returnCommand($sms, $args[1], $args[2]);
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
//            case "WHERE":
//            case "WHO":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." WHERE 47");
//                where($sms->Number(),$args[1]);
//                break;
//            case "INFO":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name:')." INFO RACKO");
//                info($sms->Number(),$args[1]);
//                break;
//            case "NOTE":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number/stand name and problem description:')." NOTE 47 "._('Flat tire on front wheel'));
//                note($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "TAG":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name and problem description:')." TAG MAINSQUARE "._('vandalism'));
//                tag($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "DELNOTE":
//                validateReceivedSMS($sms->Number(),count($args),1,_('with bike number and optional pattern. All messages or notes matching pattern will be deleted:')." NOTE 47 wheel");
//                delnote($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "UNTAG":
//                validateReceivedSMS($sms->Number(),count($args),1,_('with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand:')." UNTAG SAFKO1 pohoda");
//                untag($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "LIST":
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

    private function helpCommand(Sms $sms)
    {
        $sms->sender->notify(new Help($this->appConfig));
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

    private function rentCommand(Sms $sms, $bikeNumber)
    {
        $user = $sms->sender;
        if (!$bike = $this->bikeRepo->findByBikeNum($bikeNumber)) {
            $user->notify(new BikeDoesNotExist($bikeNumber));
            return;
        }

        try
        {
            $rent = $this->rentService->rentBike($user, $bike);
            $user->notify(new BikeRented($rent));
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

    private function returnCommand(Sms $sms, $bikeNumber, $standName)
    {
        $user = $sms->sender;

        if (!$bike = $this->bikeRepo->findByBikeNum($bikeNumber)) {
            $user->notify(new BikeDoesNotExist($bikeNumber));
            return;
        }

        if (!$stand = $this->standsRepo->findByStandNameCI($standName)) {
            $user->notify(new StandDoesNotExist($standName));
            return;
        }

        if ($this->bikeRepo->bikesRentedByUserCount($user) == 0){
            $user->notify(new NoBikesRented());
            return;
        }

        try {
            $rent = $this->rentService->returnBike($user, $bike, $stand);
            // TODO
//            $this->user->notify()
        }
        catch (BikeNotRentedException $e)
        {
            // TODO
        }
        catch (BikeRentedByOtherUserException $e)
        {
            // TODO
        }
        catch (ReturnException $e)
        {
            throw $e; // unknown type, rethrow
        }

        if ($userNote = self::parseNoteFromReturnSms($sms->sms_text)){
            $this->rentService->addNote($bike, $user, $userNote);
        }
    }

    public static function parseSmsArguments($smsText)
    {
        //preg_split must be used instead of explode because of multiple spaces
        return preg_split("/\s+/", strtoupper(trim(urldecode($smsText))));
    }

    public static function parseNoteFromReturnSms($smsText)
    {
        if (preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $smsText, $matches)) {
            return trim($matches[1]);
        } else {
            return null;
        }
    }


}
