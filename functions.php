<?php

error_reporting(-1);

require("config.php");

function Help()
{
	return "Available commands: RENT,RETURN";
}
function sendSMS($number, $text)
{
    global $gatewayId, $gatewayKey, $gatewaySenderNumber;
    $s = substr(md5($gatewayKey.$number),10,11);
    $text = substr($text,0,160);
     
    //print $s;
    //fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$id&s=d79a84418a0&d=1&sender=Miso&number=$number&msg=this is testing message");
    //$message="toto je testovacia sms";
    $um = urlencode($text);
    fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
    //print $um;
}

function getUser($number)
{
	global $dbServer, $dbUser, $dbPassword, $dbName;
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

	if ($result = $mysqli->query("SELECT userId FROM users where number=$number")) {
    		if($result->num_rows==1)
		{
			$row = $result->fetch_assoc();
			return $row["userId"];
		}
		return -1;
	}	
}

function validateNumber($number)
{
    if(getUser($number)!=-1)
	return true;
    else
	return false;
}

function error($message)
{
	//nieco sa pokazilo
	die($message);
}

function rent($number,$bike)
{
	global $dbServer, $dbUser, $dbPassword, $dbName;
	
	$userId = getUser($number);
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

	$bikeNum = intval($bike);

	if ($result = $mysqli->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId")) {
    		$row = $result->fetch_assoc();
		$countRented = $row["countRented"];
	} else error("count not retrieved");

	if ($result = $mysqli->query("SELECT userLimit FROM limits where userId=$userId")) {
    		$row = $result->fetch_assoc();
		$limit = $row["userLimit"];
	} else error("limit not retrieved");

	if($countRented >= $limit)
	{
		//echo "limit exceeded";
		sendSMS($number,"You cannot rent more bikes at one time.");
		return;
	}

	if ($result = $mysqli->query("SELECT currentUser,currentCode FROM bikes where bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Bike $bikeNum does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$currentCode = sprintf("%04d",$row["currentCode"]);
		$currentUser= $row["currentUser"];
	} else error("bike code not retrieved");

	$newCode = sprintf("%04d",rand(1,9999));

	if($currentUser==$userId)
	{
		sendSMS($number,"You already rented the bike $bikeNum. Code is $currentCode. Return the bike with command: RETURN bikenumber standname.");
		return;	
	}

	if($currentUser!=0)
	{
		sendSMS($number,"The bike $bikeNum is already rented.");
		return;
	}

	sendSMS($number,"Open with code $currentCode, change code immediately to $newCode (open,rotate metal part,set new code,rotate metal part back).");

	if ($result = $mysqli->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL where bikeNum=$bikeNum")) {
	} else error("update failed");
	//echo "RENT success";

}


function returnBike($number,$bike,$stand)
{
	global $dbServer, $dbUser, $dbPassword, $dbName;
	
	$userId = getUser($number);
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

	$bikeNum = intval($bike);
	$stand = strtoupper($stand);

	if(!preg_match("/^[A-Z]+$/",$stand))
	{
		sendSMS($number,"The stand name '$stand' you have provided was not in the correct format. A correct stand name looks like a person's first name, e.g.: TOMAS"); 
		return;
	}


	if ($result = $mysqli->query("SELECT bikeNum FROM bikes where currentUser=$userId")) {
		$rentedBikes = $result->fetch_all(MYSQLI_ASSOC);
	} else error("rented bikes not fetched");

	if(count($rentedBikes)==0)
	{
		sendSMS($number,"You have no rented bikes currently."); 
		return;
	}

	$listBikes="";
	for($i=0; $i<count($rentedBikes);$i++)
	{
		if($i!=0)
			$listBikes.=",";
		$listBikes.=$rentedBikes[$i]["bikeNum"];
	}


	if ($result = $mysqli->query("SELECT currentCode FROM bikes where currentUser=$userId and bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"You have not rented the bike $bikeNum. You have rented the following bike(s): $listBikes");
			return;
		}

		$row = $result->fetch_assoc();
		$currentCode = $row["currentCode"];
	} else error("code not retrieved");

	if ($result = $mysqli->query("SELECT standId FROM stands where standName='$stand'")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Stand '$stand' does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$standId = $row["standId"];
	} else error("stand not retrieved");


	if ($result = $mysqli->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId where bikeNum=$bikeNum")) {
	} else error("update failed");
	
	sendSMS($number,"You have successfully returned the bike $bikeNum to stand $stand. Make sure you have set the code $currentCode. Do not forget to rotate the lockpad to 0000 when leaving.");
//	echo "RETURN success";
}



?>
