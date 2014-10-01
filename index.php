<?php
require("config.php");
require("db.class.php");
require("actions-web.php");

$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();
?>
<html>
<head>
<title><? echo $systemName; ?> map with availability</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery-timing.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<script type="text/javascript" src="js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="js/modernizr.custom.js"></script>
<script type="text/javascript" src="js/functions.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="css/map.css" />
<script>
var maplat=<?php echo $systemLat; ?>;
var maplon=<?php echo $systemLong; ?>;
var mapzoom=<?php echo $systemZoom; ?>;
var standselected=0;
<?php
if (isloggedin())
   {
   echo 'var loggedin=1;',"\n";
   echo 'var priv=',getprivileges($_COOKIE["loguserid"]),";\n";
   }
else
   {
   echo 'var loggedin=0;',"\n";
   echo 'var priv=0;',"\n";
   }
?>
</script>
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
</head>
<body>
<div id="map"></div>
<div id="sidebar">
<div class="row">
   <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
   <h1><?php echo $systemName; ?></h1>
   </div>
   <div class="col-xs-5 col-sm-5 col-md-5 col-lg-5">
   <ul class="nav nav-pills">
   <?php
   if (isloggedin()) echo '<li><em>',getusername($_COOKIE["loguserid"]),'</em><br /><a href="command.php?action=logout" id="logout">Log out</a></li>';
   else
      {
      echo '<li><a href="login.php" id="login">Log in</a></li>';
      }
   ?>
   </ul>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<h2 id="standname"></h2>
<div id="standinfo"></div>
<div id="standbikes"></div>
<div id="console"></div>
<div class="row">
<div id="standactions" class="btn-group">
  <div class="col-lg-12">
         <button class="btn btn-primary" type="button" id="rent"><span class="glyphicon glyphicon-log-out"></span> Rent <span class="bikenumber"></span></button>
  </div>
</div>
</div>
<div class="row"><div class="col-lg-12">
<br /></div></div>
<div id="rentedbikes"></div>
<div class="row">
   <div class="btn-group bicycleactions">
   <div class="col-lg-12">
   <button type="button" class="btn btn-primary" id="return"><span class="glyphicon glyphicon-log-in"></span> Return bicycle <span class="bikenumber"></span></button>
   </div></div>
   <div class="btn-group bicycleactions">
   <div class="col-lg-12">
   <button type="button" class="btn btn-warning" id="note"><span class="glyphicon glyphicon-exclamation-sign"></span> Report bicycle <span class="bikenumber"></span></button>
   </div>
</div>
</div>
<div class="row">
  <div class="btn-group adminactions">
  <div class="col-lg-12">
  <div class="input-group">
         <input type="text" name="adminparam" id="adminparam" class="form-control">
         <span class="input-group-btn">
         <button class="btn btn-default" type="button" id="where"><span class="glyphicon glyphicon-screenshot"></span> Where is?</button>
         </span>
  </div>
  </div>
</div>
</div>
<div class="row">
   <div class="btn-group adminactions">
   <div class="col-lg-12">
   <button type="button" id="revert" class="btn btn-default"><span class="glyphicon glyphicon-fast-backward"></span> Revert</button>
   <button type="button" id="last" class="btn btn-default"><span class="glyphicon glyphicon-stats"></span> Last usage</button>
   <button type="button" id="createuser" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span> Create user</button>
   </div>
   </div>
</div>
</div>
</body>
</html>