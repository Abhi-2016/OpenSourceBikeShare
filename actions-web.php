<?php
require("common.php");

function response($message,$error=0,$additional="",$log=1)
{
   global $db;
   $json=array("error"=>$error,"content"=>$message);
   if (is_array($additional))
      {
      foreach ($additional as $key=>$value)
         {
         $json[$key]=$value;
         }
      }
   $json=json_encode($json);
   if ($log==1 AND $message)
      {
      if (isset($_COOKIE["loguserid"]))
         {
         $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
         }
      else $userid=0;
      $number=getphonenumber($userid);
      logresult($number,$message);
      }
   $db->conn->commit();
   echo $json;
   exit;
}

function rent($userId,$bike)
{

   global $db;
   $bikeNum = $bike;

   $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
   $row = $result->fetch_assoc();
   $countRented = $row["countRented"];

   $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
   $row = $result->fetch_assoc();
   $limit = $row["userLimit"];

   if ($countRented>=$limit)
      {
      if ($limit==0)
         {
         response("You can not rent any bikes. Contact the admins to lift the ban.",ERROR);
         }
      elseif ($limit==1)
         {
         response("You can only rent ".$limit." bike at once.",ERROR);
         }
      else
         {
         response("You can only rent ".$limit." bikes at once and you have already rented ".$limit.".",ERROR);
         }
      }

   $result = $db->query("SELECT currentUser,currentCode,note FROM bikes where bikeNum=$bikeNum");
   $row = $result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);
   $currentUser= $row["currentUser"];
   $note= $row["note"];

   $newCode=sprintf("%04d",rand(100,9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

   if ($currentUser==$userId)
      {
      response("You already rented the bike $bikeNum. Code is $currentCode. Return the bike with command: RETURN bikenumber standname.",ERROR);
      return;
      }
   if ($currentUser!=0)
      {
      response($number,"The bike $bikeNum is already rented.",ERROR);
      return;
      }

   $message='<h3>Bike '.$bikeNum.': <span class="label label-primary">Open with code '.$currentCode.'.</span></h3>Change code immediately to <span class="label label-default">'.$newCode.'</span><br />(open, rotate metal part, set new code, rotate metal part back).';
   if ($note)
      {
      $message.="<br />Reported issue: <em>".$note."</em>";
      }

   $result = $db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL where bikeNum=$bikeNum");
   $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
   response($message);

}

function returnBike($userId,$bike,$stand)
{

   global $db;
   $bikeNum = intval($bike);
   $stand = strtoupper($stand);

   $result = $db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
   $rentedBikes = $result->fetch_all(MYSQLI_ASSOC);

   if (count($rentedBikes)==0)
      {
      response("You have no rented bikes currently.",ERROR);
      }

   $result = $db->query("SELECT currentCode,note FROM bikes WHERE currentUser=$userId and bikeNum=$bikeNum");
   $row = $result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);
   $note= $row["note"];

   $result = $db->query("SELECT standId FROM stands where standName='$stand'");
   $row = $result->fetch_assoc();
   $standId = $row["standId"];

   $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum and currentUser=$userId");

   $message = '<h3>Bike '.$bikeNum.': <span class="label label-primary">Lock with code '.$currentCode.'.</span></h3>';
   $message.= '<br />Please, <span class="label label-default">rotate the lockpad to 0000</label> when leaving.';

   $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
   response($message);

}


function where($userId,$bike)
{

   global $db;
   $bikeNum = $bike;

   $result = $db->query("SELECT number,userName,stands.standName,note FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   $row = $result->fetch_assoc();
   $phone= $row["number"];
   $userName= $row["userName"];
   $standName= $row["standName"];
   $note= $row["note"];
   if ($note!="")
      {
      $note="Bike note: $note";
      }

   if ($standName)
      {
      response('<h3>Bike '.$bikeNum.' at <span class="label label-primary">'.$standName.'</span>.</h3>.'.$note);
      }
   else
      {
      response('<h3>Bike '.$bikeNum.' rented by <span class="label label-primary">'.$userName.'</span>.</h3>Phone: +'.$phone.'. '.$note);
      }

}

function checkbikeno($bikeNum)
{
   global $db;
   $result = $db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
   if (!$result->num_rows)
      {
      response('<h3>Bike '.$bikeNum.' does not exist!</h3>',ERROR);
      }
}

function checkstandname($stand)
{
   global $db;
   $standname=trim(strtoupper($stand));
   $result = $db->query("SELECT standName FROM stands WHERE standName='$stand'");
   if (!$result->num_rows)
      {
      response('<h3>Stand '.$stand.' does not exist!</h3>',ERROR);
      }
}

function logrequest($userid)
{
   global $dbServer,$dbUser,$dbPassword,$dbName;
   $localdb=new Database($dbServer,$dbUser,$dbPassword,$dbName);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);

   $number=getphonenumber($userid);

   $result = $localdb->query("INSERT INTO received SET sender='$number',receive_time='".date("Y-m-d H:i:s")."',sms_text='".$_SERVER['REQUEST_URI']."',ip='".$_SERVER['REMOTE_ADDR']."'");

}

function logresult($userid,$text)
{
   global $dbServer,$dbUser,$dbPassword,$dbName;

   $localdb=new Database($dbServer,$dbUser,$dbPassword,$dbName);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);
   $userid = $localdb->conn->real_escape_string($userid);
   $logtext="";
   if (is_array($text))
      {
      foreach ($text as $value)
         {
         $logtext.=$value.", ";
         }
      }
   else
      {
      $logtext=$text;
      }

   $logtext = strip_tags($localdb->conn->real_escape_string($logtext));

   $result = $localdb->query("INSERT INTO sent SET number='$userid',text='$logtext'");

}

function addnote($userId,$bikeNum,$message)
{

        global $db;
	$bikeNum = intval($bikeNum);

	if ($result = $db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum")) {
               $row = $result->fetch_assoc();
               $phone= $row["number"];
               $userName= $row["userName"];
               $standName= $row["standName"];
        } else error("bike code not retrieved");

        if($standName!=NULL)
        {
                $bikeStatus = "B.$bikeNum is at $standName.";
        }
        else
        {
                $bikeStatus = "B.$bikeNum is rented by $userName (+$phone).";
        }

	$reportedBy=getusername($userId);

	$userNote=$db->conn->real_escape_string(trim($message));

	if ($userNote)
	{
		if ($result = $db->query("UPDATE bikes SET note='$userNote' where bikeNum=$bikeNum")) {
		} else error("update failed");

		response("Note for bike $bikeNum saved.");
		notifyAdmins("Note b.$bikeNum by $reportedBy:".$userNote." ".$bikeStatus);

	}


}

function listbikes($stand)
{
   global $db;

   $stand=$db->conn->real_escape_string($stand);
   $result=$db->query("SELECT bikeNum,note FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand'");
   while($row=$result->fetch_assoc())
      {
      if ($row["note"]) $bicycles[]="*".$row["bikeNum"]; // bike with note / issue
      else $bicycles[]=$row["bikeNum"];
      }
   if (!$result->num_rows) $bicycles="";
   response($bicycles,0,"",0);

}

function removenote($userId,$bikeNum)
{
   global $db;

   $result = $db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
   response("Note for bike $bikeNum deleted.");
}

/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
**/
function notifyAdmins($message,$notificationtype=0)
{
   global $db;

   $result = $db->query("SELECT number,mail FROM users where privileges & 2 != 0");
   $admins = $result->fetch_all(MYSQLI_ASSOC);
   for ($i=0; $i<count($admins);$i++)
   {
   if ($notificationtype==0)
      {
      sendSMS($admins[$i]["number"],$message);
      }
   else
      {
      sendEmail($admins[$i]["mail"],$message,"");
      }
   }

}

function last($userId,$bike)
{

   global $db;
   $bikeNum = intval($bike);

   $result = $db->query("SELECT userName,parameter,standName,time FROM `history` join users on history.userid=users.userid left join stands on stands.standid=history.parameter where bikenum=$bikeNum order by time desc LIMIT 10");
   $bikeHistory= $result->fetch_all(MYSQLI_ASSOC);

   $historyInfo="<h3>Bike $bikeNum history:</h3><ul>";
   for($i=0; $i<count($bikeHistory);$i++)
   {
      $time=strtotime($bikeHistory[$i]["time"]);
      $historyInfo.="<li>".date("d/m H:i",$time)." - ";
      if($bikeHistory[$i]["standName"]!=NULL)
      {
      $historyInfo.=$bikeHistory[$i]["standName"];
      }
      else
      {
      $historyInfo.=$bikeHistory[$i]["userName"]." (code ".$bikeHistory[$i]["parameter"].")";
      }
      $historyInfo.="</li>";
   }
   $historyInfo.="</ul>";

   response($historyInfo,0,"",0);

}


function userbikes($userId)
{
   global $db;
   if (!isloggedin()) response("");
   $result=$db->query("SELECT bikeNum FROM bikes where currentUser=$userId ORDER BY bikeNum");
   while ($row=$result->fetch_assoc())
      {
      $bicycles[]=$row["bikeNum"];
      }
   if (!$result->num_rows) $bicycles="";
   response($bicycles,0,"",0);
}

function revert($userId,$bikeNum)
{

   global $db;

   $standId=0;
   $result = $db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeNum AND currentUser<>'NULL'");
   if (!$result->num_rows)
      {
      response("Bicycle $bikeNum is not rented right now. Revert not successful!",ERROR);
      return;
      }
   $result = $db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON standId=parameter WHERE bikeNum=$bikeNum AND action='RETURN' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $standId=$row["parameter"];
      $stand=$row["standName"];
      }
   $result = $db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action='RENT' ORDER BY time DESC LIMIT 1,1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $code=$row["parameter"];
      }
   if ($standId and $code)
      {
      $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code where bikeNum=$bikeNum");
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='REVERT',parameter='$standId|$code'");
      $result = $db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RENT',parameter=$code");
      $result = $db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      response('<h3>Bicycle '.$bikeNum.' reverted to <span class="label label-primary">'.$stand.'</span> with code <span class="label label-primary">'.$code.'</span>.</h3>');
      }
   else
      {
      response("No last stand or code for bicycle $bikeNum found. Revert not successful!",ERROR);
      }

}

function register($number,$code,$checkcode,$fullname,$email,$password,$password2)
{
   global $db, $dbPassword;

   $number=$db->conn->real_escape_string(trim($number));
   $code=$db->conn->real_escape_string(trim($code));
   $checkcode=$db->conn->real_escape_string(trim($checkcode));
   $fullname=$db->conn->real_escape_string(trim($fullname));
   $email=$db->conn->real_escape_string(trim($email));
   $password=$db->conn->real_escape_string(trim($password));
   $parametercheck=$number.";".str_replace(" ","",$code).";".$checkcode;
   $result = $db->query("SELECT parameter FROM history WHERE userId=0 AND bikeNum=0 AND action='REGISTER' AND parameter='$parametercheck' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $result = $db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='$number',privileges=0");
      $userId=$db->conn->insert_id;
      response("You have been successfully registered.");
      }
   else
      {
      response("Problem with registration. Please reload the page and try again.",ERROR);
      }

}

function login($number,$password)
{
   global $db,$systemURL,$countryCode;

   $number=$db->conn->real_escape_string(trim($number));
   $password=$db->conn->real_escape_string(trim($password));
   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   $number=$countryCode.substr($number,1,strlen($number));

   $result=$db->query("SELECT userId FROM users WHERE number='$number' AND password=SHA2('$password',512)");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $userId=$row["userId"];
      $sessionId=hash('sha256',$userId.$number.time());
      $timeStamp=time()+86400*14; // 14 days to keep user logged in
      $result=$db->query("DELETE FROM sessions WHERE userId='$userId'");
      $result=$db->query("INSERT INTO sessions SET userId='$userId',sessionId='$sessionId',timeStamp='$timeStamp'");
      $db->conn->commit();
      setcookie("loguserid",$userId,time()+86400*14);
      setcookie("logsession",$sessionId,time()+86400*14);
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL);
      header("Connection: close");
      exit;
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."login.php?error=1");
      header("Connection: close");
      exit;
      }

}

function checksession()
{
   global $db,$systemURL;

   $result=$db->query("DELETE FROM sessions WHERE timeStamp<='".time()."'");
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1)
         {
         $timestamp=time()+86400*14;
         $result=$db->query("UPDATE sessions SET timeStamp='$timestamp' WHERE userId='$userid' AND sessionId='$session'");
         $db->conn->commit();
         }
      else
         {
         $result=$db->query("DELETE FROM sessions WHERE userId='$userid' OR sessionId='$session'");
         $db->conn->commit();
         setcookie("loguserid","",time()-86400);
         setcookie("logsession","",time()-86400);
         header("HTTP/1.1 301 Moved permanently");
         header("Location: ".$systemURL."login.php?error=2");
         header("Connection: close");
         exit;
         }
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."login.php?error=2");
      header("Connection: close");
      exit;
      }

}

function isloggedin()
{
   global $db;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1) return 1;
      else return 0;
      }
   return 0;

}

function logout()
{
   global $db,$systemURL;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("DELETE FROM sessions WHERE userId='$userid'");
      $db->conn->commit();
      }
   header("HTTP/1.1 301 Moved permanently");
   header("Location: ".$systemURL);
   header("Connection: close");
   exit;
}

function sendConfirmationEmail($email)
{

        global $db, $dbPassword;

	$subject = 'registracia/registration White Bikes';

	if ($result = $db->query("SELECT userName,userId FROM users where mail='$email'")) {
		$user = $result->fetch_all(MYSQLI_ASSOC);
	} else error("email not fetched");

	$userId =$user[0]["userId"];
	$userKey = hash('sha256', $email.$dbPassword.rand(0,1000000));

	if ($result = $db->query("INSERT into registration SET userKey='$userKey',userId='$userId'")) {
	} else error("insert registration failed");

	if ($result = $db->query("INSERT into limits SET userId='$userId',userLimit=0")) {
	} else error("insert limit failed");

		$mena = preg_split("/[\s,]+/",$user[0]["userName"]);
		$krstne = $mena[0];
		$message = "Ahoj $krstne, [EN below]\n
bol/a si zaregistrovany/a do systemu komunitneho poziciavania bicyklov White Bikes.\n
Navod k Bielym Bicyklom najdes na http://v.gd/navod

Ak suhlasis s pravidlami, klikni na linku dole v maili.

Dear $krstne,
you were registered to the community bikesharing White Bikes.
The current guide (in English) for White Bikes can be found at http://v.gd/introWB

If you agree with the rules, click on the following link:

http://whitebikes.info/sms/agree.php?key=$userKey
";
		sendEmail($email, $subject, $message);
}

function confirmUser($userKey)
{
	global $db;
	$userKey = $db->conn->real_escape_string($userKey);

	if ($result = $db->query("SELECT userId FROM registration where userKey='$userKey'")) {
		if($result->num_rows==1)
		{
			$row = $result->fetch_assoc();
			$userId = $row["userId"];
		}
		else
		{
			response("Some problem occured!",ERROR);
			return FALSE;
		}
	} else error("key not fetched");

	if ($result = $db->query("UPDATE limits SET userLimit=1 where userId=$userId")) {
	} else error("update limit failed");

	if ($result = $db->query("DELETE from registration where userId='$userId'")) {
	} else error("delete registration failed");

	response("All fine. Welcome!");

}

function checkprivileges($userid)
{
   global $db;
   $privileges=getprivileges($userid);
   if (!$privileges)
      {
      response("Sorry, this command is only available for the privileged users.",ERROR);
      exit;
      }
}

function smscode($number)
{

   global $db, $gatewayId, $gatewayKey, $gatewaySenderNumber, $countryCode;
   srand();

   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   $number=$countryCode.substr($number,1,strlen($number));
   $number = $db->conn->real_escape_string($number);
   $result = $db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows)
      {
      response("User with the number ".$number." already exists!",ERROR);
      }

   $smscode=chr(rand(65,90)).chr(rand(65,90))." ".rand(100000,999999);
   $smscodenormalized=str_replace(" ","",$smscode);
   $checkcode=md5("WB".$number.$smscodenormalized);
   $text="Enter this code to register: ".$smscode;
   $text=$db->conn->real_escape_string($text);

   $result = $db->query("INSERT INTO sent SET number='$number',text='$text'");
   $result = $db->query("INSERT INTO history SET userId=0,bikeNum=0,action='REGISTER',parameter='$number;$smscodenormalized;$checkcode'");

   if (DEBUG===TRUE)
      {
      response($number,0,array("checkcode"=>$checkcode));
      }
   else
      {
      $s = substr(md5($gatewayKey.$number),10,11);
      $text = substr($text,0,160);
      $um = urlencode($text);
      fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
      response($number,0,array("checkcode"=>$checkcode));
      }
}

function mapgetmarkers()
{
   global $db;

   $jsoncontent=array();
   $result = $db->query("SELECT standId,count(bikeNum) AS bikecount,standDescription,standName,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId WHERE stands.serviceTag=0 GROUP BY standName");
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=$row;
      }
   echo json_encode($jsoncontent);
}

function mapgetlimit($userId)
{
   global $db;

   if (!isloggedin()) response("");
   $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
   $row = $result->fetch_assoc();
   $rented= $row["countRented"];

   $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
   $row = $result->fetch_assoc();
   $limit = $row["userLimit"];

   $currentlimit=$limit-$rented;

   echo json_encode(array("limit"=>$currentlimit,"rented"=>$rented));
}

?>