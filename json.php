<?php
	require 'newsletter.inc.php';

	if ($_GET["array"] == "")
		$json = "{ ".JsonFromQuery($con,stripslashes($_GET["object"]),false,false)." }";
	else
		$json = "[ ".JsonFromQuery($con,stripslashes($_GET["array"]),false,true)." ]";
	
	header("Content-type: application/json");
	header("Content-length: ".strlen($json));
	echo $json;
 ?>