<?php

// mainframe is an API workhorse, lots of 'core' interaction routines
$mainframe = new mosMainFrame( $database, '', '.' );
$mainframe->initSession();

/** get the information about the current user from the sessions table */
$my = $mainframe->getUser();
$username = $my->username;
$userpositions = ArrayFromQuery($con,
    "select con_position, jos_contact_details.name 
     from jos_contact_details, jos_users
     where jos_users.username = '$username'
     and jos_users.name = jos_contact_details.name");

if (count($userpositions) == 0)
{
	echo "<script>window.location.replace('" . BASE_URL . "');</script>";
	die('Not logged on');
}

?>
