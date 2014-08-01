<?php
require('functions.php');

//echo 'Poslal si mi "' . htmlspecialchars($_GET["message"]) . '".';

$message = strtoupper(trim(urldecode($_GET["sms_text"])));
//$number = intval($_GET["sender"]);
$number = $_GET["sender"];
$sms_uuid = $_GET["sms_uuid"];

echo "ok:$sms_uuid";
echo "\n";

$args = preg_split("/[\s,\.]+/", $message);

#foreach($args as $arg)
#{
#    print "$arg\n";
#}

if(!validateNumber($number))
{
    sendSMS($number,"Vase cislo nie je registrovane. Your number is not registered.");
    return;//ignore
}

switch($args[0])
{
    case "RENT":
    case "POZICAJ":
	if(count($args)<2)
	{
	    sendSMS($number,"You have to provide the bike number, e.g.: RENT 47");
	    return;
	}
	if(count($args)>2)
	{
	    sendSMS($number,"You have provided too many arguments. Provide only the bike number, e.g.: RENT 47");
	    return;
	}
	rent($number,$args[1]);//intval
	break;
    case "RETURN":
    case "VRAT":
	if(count($args)<=2)
	{
	    sendSMS($number,"You have to provide the bike number and the stand name, e.g.: RETURN 47 TOMAS");
	    break;
	}
	if(count($args)>3)
	{
	    sendSMS($number,"You have provided too many arguments. Provide the bike number and the stand name, e.g.: RETURN 47 TOMAS");
	    break;
	}
	//if(count($args)==2)
	//	returnBike1($number,$args[1]);
	if(count($args)==3)
		returnBike($number,$args[1],$args[2]);
		break;
    case "HELP":
    case "POMOC":
	sendSMS($number,Help());
	break;
    case "WHERE":
    case "KDE":
	if(count($args)<2)
	{
	    sendSMS($number,"You have to provide the bike number, e.g.: WHERE 47");
	    return;
	}
	if(count($args)>2)
	{
	    sendSMS($number,"You have provided too many arguments. Provide only the bike number, e.g.: WHERE 47");
	    return;
	}
	where($number,$args[1]);
	break;
    case "LIST":
    case "ZOZNAM":
	if(count($args)<2)
	{
	    sendSMS($number,"You have to provide the stand name, e.g.: LIST RACKO");
	    return;
	}
	if(count($args)>2)
	{
	    sendSMS($number,"You have provided too many arguments. Provide only the stand name, e.g.: LIST RACKO");
	    return;
	}
	listBikes($number,$args[1]);
	break;
 //    case "NEAR":
//    case "BLIZKO":
//	near($number,$args[1]);
    case "FREE":
	freeBikes($number);
	break;
     default:
	sendSMS($number,"Your message '$message' was not understood. The command $args[0] does not exist.".Help());
}

?>
