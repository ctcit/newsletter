<?php
require 'newsletter.inc.php';

if (count($processor->userpositions) == 0)
{
	echo "<script>window.location.replace('" . BASE_URL . "');</script>";
	die('Not logged on');
}

/*
 * SURELY THIS SHOULD BE DEFUNCT???? [RJL 11/6/17]
 */
$genfile = GetPost('generatefile');
$prefs	  	= 	"{ value:'GenerateFile(\"$genfile\")'},".
				JsonFromQuery($con,"SELECT value from ctcweb9_newsletter.fields
										WHERE `name`='$username.$_SERVER[REQUEST_URI]'",false,true);

?>