<?php
require 'newsletter.inc.php';

if (count($processor->userpositions) == 0)
{
	echo "<script>window.location.replace('" . BASE_URL . "');</script>";
	die('Not logged on');
}

$generatefile = isset($_POST["generatefile"]) ? $_POST["generatefile"]: "";

$prefs	  	= 	"{ value:'GenerateFile(\"".$generatefile."\")'},".
				JsonFromQuery($con,"SELECT value from newsletter.fields 
										WHERE `name`='$username.$_SERVER[REQUEST_URI]'",false,true);

?>
