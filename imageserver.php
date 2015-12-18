<?php
require 'newsletter.inc.php';

function CreateServedImage($copyx,$copyy,$incolour)
{
	if ($incolour)
	{
		$image = @imagecreatetruecolor($copyx,$copyy) or die("cannot create image copy");
		
		return $image;
	}
	else
	{
		$image = @imagecreate($copyx,$copyy) or die("cannot create image copy");
		for ($i=0; $i<256; $i++)
		    imagecolorallocate($image, $i, $i, $i);
			
		return $image;
	}
}



$caption 	= array_key_exists("caption",$_GET) ? intval($_GET["caption"]) : 12;
$border		= array_key_exists("border", $_GET) ? intval($_GET["border"])  : 1;
$size	  	= $_GET["size"];
$id		  	= $_GET["id"];
$index	  	= $_GET["index"];
								
$error		= "";								
$text   	= ValueFromSql($con,"SELECT concat(`introtext`,`fulltext`) 'text'
								FROM jos_content 
								WHERE id=$id");
$rtfpagewidth = ValueFromSql($con,"SELECT value 
								FROM ctcweb9_newsletter.fields
								WHERE name='rtfpagewidth'");
$rtfincolour = ValueFromSql($con,"SELECT value 
								FROM ctcweb9_newsletter.fields
								WHERE name='rtfincolour'") == '1';
$size 		= floatval($size) * floatval($rtfpagewidth);

if ($size < 10)
{
	$error	= "size is too small ($size)";
	$size	= 200;
}
else if ($caption > 0 && $size < $caption)
{
	$error	= "size is smaller than caption ($size/$caption)";
	$size	= 200;
}
else if ($text == "")
{
	$captiontext = "Invalid content id ($id)";
}
else
{
	$images		= FieldExplode($text,"{mostripimage ","}");
	$image		= $images[$index*2-1];
	
	if ($image == "")
		$error =	"Photo number $index does not exist for this story($id), ".
					"the last one is number ".((count($images)-1)/2).".";
}
	
if ($error == "")
{
	$image			.= strpos($image,",") === false ? "," : "";
	$pos			= strpos($image,",");
	$source			= trim(substr($image,0,$pos));
	$captiontext	= trim(str_replace('"','',substr($image,$pos+1)));

	$orig = @imagecreatefromjpeg("../images/stories/$source");
	
	if (!$orig)
		$error =	"Cannot find $source for story($id) index($index)";
}

if ($error == "")
{
	$copyx = $origx	= imagesx($orig);
	$copyy = $origy	= imagesy($orig);

	if ($copyx > $size)
	{
		$ratio = floatval($size)/floatval($copyx);
		$copyx = intval($copyx * $ratio);
		$copyy = intval($copyy * $ratio);
	}

	if ($copyy > $size)
	{
		$ratio = floatval($size)/floatval($copyy);
		$copyx = intval($copyx * $ratio);
		$copyy = intval($copyy * $ratio);
	}
	
	$border			= 1;
	$copy			= CreateServedImage($copyx,$copyy,$rtfincolour);
	$textcolour		= $colour[0];
	
	imagefilledrectangle($copy,0,0,$copyx,$copyy,$colour[255]);
	imagecopyresized($copy,$orig,$border,$border,$border,$border,$copyx-$border*2,$copyy-$border*2,$origx,$origy);
}
else
{
	$copyx 			= $size;
	$copyy 			= 20;
	$caption 		= 12;
	$captiontext	= $error;
	$copy			= CreateServedImage($copyx,$copyy,true);
	
	imagefilledrectangle($copy,0,0,$copyx,$copyy,imagecolorallocate($copy, 255, 0, 0));
}

if ($caption > 0)
{
	while (true)
	{
		$font 	= "arial.ttf";
		$bbox	= imagettfbbox($caption,0,$font,$captiontext);
		
		if ($bbox[2] < $copyx || $caption < 8)
			break;
		
		$caption--;
	}
	
	$black = imagecolorallocate($copy, 0, 0, 0);
	$white = imagecolorallocate($copy, 255, 255, 255);
	
	imagefilledrectangle($copy,		$copyx/2-$bbox[2]/2-2,$copyy-$border-2-$bbox[1]+$bbox[7]-2,
									$copyx/2+$bbox[2]/2+2,$copyy-$border-2,$white);
	imagettftext($copy,$caption,0,	$copyx/2-$bbox[2]/2,  $copyy-$border-2-$bbox[1]-1,$black,
									$font,$captiontext);
}
		
header ("Content-type: image/jpeg");
imagejpeg($copy);
imagedestroy($orig);
imagedestroy($copy);
?> 