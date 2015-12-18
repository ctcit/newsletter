<?php
require 'newsletter.inc.php';

if (count($processor->userpositions) == 0)
{
	echo "<script>window.location.replace('http://www.ctc.org.nz');</script>";
	die('Not logged on');
}

$prefs	  	= 	"{ value:'GenerateFile(\"$_POST[generatefile]\")'},".
				JsonFromQuery($con,"SELECT value from ctcweb9_newsletter.fields 
										WHERE `name`='$username.$_SERVER[REQUEST_URI]'",false,true);

?>