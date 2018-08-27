<?php

/// Remove every thing that is RTF markup text, leaving only the readable text
function RtfStrip($s)
{
	$out = "";

	for ($i = 0; $i < strlen($s);)
	{
		$ch = substr($s,$i,1);

		if ($ch == '\\')
		{
			$i++;
			$ch = substr($s,$i,1);
			if	($ch == "\\" || $ch == "{" || $ch == "}" || $ch == "~" || $ch == "*")
				continue;

			while ($i < strlen($s) && ctype_alpha(substr($s,$i,1)))
				$i++;
			while ($i < strlen($s) && substr($s,$i,1) == '-')
				$i++;
			while ($i < strlen($s) && ctype_digit(substr($s,$i,1)))
				$i++;
			if ($i < strlen($s) && substr($s,$i,1) == ' ')
				$i++;
		}
		else
		{
			if ($ch != "{" && $ch != "}")
				$out .= $ch;
			$i++;
		}
	}

	return trim($out);
}

/// Remove every thing inside </> brackets that is RTF markup text, leaving only the readable text
function RtfStripTags($s)
{
	$a = FieldExplode($s,"<",">");

	for ($i = 1; $i < count($a); $i += 2)
		$a[$i] = "<".RtfStrip($a[$i]).">";

	return FieldImplode($a);
}

// Returns TRUE if the RTF is valid at a basic level
function RtfIsValid($rtf)
{
	$rtf    = trim($rtf);
	$length = strlen($rtf);
	$indent = 0;

	for ($i = 0; $i < $length && $indent >= 0; $i++)
	{
		if ($rtf[$i] == '{') $indent++;
		if ($rtf[$i] == '}') $indent--;
		if ($rtf[$i] == '\\') $i++;
	}

	return $indent == 0 && $length > 0 && $rtf[0] == '{' && $rtf[$length-1] == '}';
}

function my_ucwords($str, $is_name=false) {
   // exceptions to standard case conversion
   if ($is_name) {
       $all_uppercase = '';
       $all_lowercase = 'De La|De Las|Der|Van De|Van Der|Vit De|Von|Or|And';
   } else {
       // addresses, essay titles ... and anything else
       $all_uppercase = 'Po|Rr|Se|Sw|Ne|Nw';
       $all_lowercase = 'A|And|As|By|In|Of|Or|To';
   }
   $prefixes = 'Mc';
   $suffixes = "'S";

   // captialize all first letters
   $str = preg_replace('/\\b(\\w)/e', 'strtoupper("$1")', strtolower(trim($str)));

   if ($all_uppercase) {
       // capitalize acronymns and initialisms e.g. PHP
       $str = preg_replace("/\\b($all_uppercase)\\b/e", 'strtoupper("$1")', $str);
   }
   if ($all_lowercase) {
       // decapitalize short words e.g. and
       if ($is_name) {
           // all occurences will be changed to lowercase
           $str = preg_replace("/\\b($all_lowercase)\\b/e", 'strtolower("$1")', $str);
       } else {
           // first and last word will not be changed to lower case (i.e. titles)
           $str = preg_replace("/(?<=\\W)($all_lowercase)(?=\\W)/e", 'strtolower("$1")', $str);
       }
   }
   if ($prefixes) {
       // capitalize letter after certain name prefixes e.g 'Mc'
       $str = preg_replace("/\\b($prefixes)(\\w)/e", '"$1".strtoupper("$2")', $str);
   }
   if ($suffixes) {
       // decapitalize certain word suffixes e.g. 's
       $str = preg_replace("/(\\w)($suffixes)\\b/e", '"$1".strtolower("$2")', $str);
   }
   return $str;
}

// Explodes $s into an array - text enclosed by $open and $close is placed into odd array elements, the rest into even array elements
// e.g. FieldExplode("aaa<b>ccc<d>eee","<",">") returns array("aaa","b","ccc","d","eee")
function FieldExplode($s,$open,$close)
{
    $arr        = array();
    $posclose   = 0;

    while ($posclose < strlen($s))
    {
        $posopen = strpos($s,$open,$posclose);
        if ($posopen === false)
        {
            $arr[] = substr($s,$posclose);
            break;
        }

        $arr[] = substr($s,$posclose,$posopen-$posclose);
        $posopen += strlen($open);

        $posclose = strpos($s,$close,$posopen);
        if ($posclose === false)
            $posclose = strlen($s);

        $arr[] = substr($s,$posopen,$posclose-$posopen);
        $posclose += strlen($close);
    }

    return $arr;
}

// Returns all the elements of the array appended together as a string
function FieldImplode($arr)
{
    $s = '';
    foreach ($arr as $line)
        $s .= $line;

    return $s;
}

// Converts a string to something that can be included in a javascript string
function JsonFromString($val)
{
    $val = addslashes($val);
    $val = str_replace("\r","",     $val);
    $val = str_replace("\n","\\n",  $val);
    $val = str_replace("\x96","-",  $val);
    return "'$val'";
}

// Converts a row from mysqli_fetch_array to a javascript object
function JsonFromRow($queryrow)
{
    $js  = "";
    foreach ($queryrow as $col => $val)
    {
        if (preg_match("/[a-zA-Z]/",$col))
            $js .= JsonFromString(strtolower($col)).':'.JsonFromString($val).',';
    }

    return trim($js,",");
}

// Converts a query to a tree-structured javascript object
function JsonFromQuery($con,$query,$echoquery = true, $array = false)
{
	if ($echoquery)
		echo "/*$query*/\n";

    $queryrows = $con->query($query);
    $js   = "";

    if (!$queryrows)
        die($con->error);

    while ($queryrow = mysqli_fetch_array($queryrows))
	{
		if ($array)
			$js .= "{".JsonFromRow($queryrow)."},\n";
		else
			$js .= JsonFromString(strtolower($queryrow[0])).":{".JsonFromRow($queryrow)."},\n";
	}

    return trim($js,",\n");
}

function ArrayToOptions($arr,$selected,$default)
{
	$options = "";

	if (!array_key_exists($selected,$arr))
		$selected = $default;

	foreach ($arr as $value => $title)
	{
		if ($value == $selected)
			$options .= "<option title='$title' selected>$value</option>\n";
		else
			$options .= "<option title='$title'>$value</option>\n";
	}

	return $options;
}

function ArrayToList($arr)
{
	$s = "";

	foreach ($arr as $key => $val)
		$s .= "'".addslashes($key)."',";

	return trim($s,",");
}

function ArrayFromQuery($con, $query)
{
	$arr  = array();
    $queryrows = $con->query($query);
    if (!$queryrows)
        die("No rows<br>" . $con->error . $query);

    while ($row = mysqli_fetch_array($queryrows))
		$arr[$row[0]] = $row[1];

	return $arr;
}

function ValueFromSql($con,$query)
{
    $queryrows = $con->query($query);

    if (!$queryrows)
        return $query.'\r\n' . $con->error;
    else if ($row = mysqli_fetch_array($queryrows))
		return $row[0];
    else
        return "";
}

function SetField($con,$name,$value,$type)
{
	$con->query("INSERT INTO newsletter.fields(`name`,`value`,`type`)
												VALUES('$name','$value','$type')
				ON DUPLICATE KEY UPDATE `value`='$value'");
}

function ProcessFields($con)
{
    $queryrows = $con->query("SELECT * FROM newsletter.`fields`");
	$out = array();

    if (!$queryrows)
        die($con->error);

    while ($row = mysqli_fetch_array($queryrows))
    {
        $asql   = FieldExplode($row["sql"],"{","}");

		for ($i = 1; $i < count($asql); $i += 2)
			$asql[$i] = $row[$asql[$i]];

		$sql		= FieldImplode($asql);
        $id     	= $row["id"];
		$override	= $row["valueoverride"];

		if 		($override == "" && $sql == "")
			$value  = $row["value"];
		else if ($override != "")
			$value  = $override;
        else
            $value = ValueFromSql($con,$sql);

		$out[strtolower($row["name"])] = array( "value" => $value, "sql" => $sql );

		if ($value != $row["value"] &&
			!$con->query("update newsletter.`fields`
							set value='".addslashes($value)."' where id = $id"))
			die($con->error);
    }

	return $out;
}

class PostProcessor
{
    var $historyitem   		= 'newsletter.historyitem';
	var $historydetail 		= 'newsletter.historydetail';
    var $errors 			= '';
    var $datetime			= '';
	var $con;
	var $userpositions;
	var $username;

	function PostProcessor($con,$username)
	{
		// get the information about the current user from the sessions table
		$this->con 				= $con;
		$this->username 		= $username;
        $this->userpositions = ArrayFromQuery($con,
            "SELECT role, fullName
             FROM ctc.view_members, ctc.members_roles, ctc.roles
             WHERE loginName='$username'
             AND members_roles.memberId = view_members.memberId
             AND members_roles.roleId = roles.id");
		//$this->userpositions	= ArrayFromQuery($con,"select con_position, jos_contact_details.name
		//												from jos_contact_details, jos_users
		//												where jos_users.username = '$this->username'
		//												and jos_users.name = jos_contact_details.name");
	}

	// Processes posts from editor.js
	function ProcessPost($post)
	{
		if (count($this->userpositions) == 0)
			return "";

		$rows = array();

	    foreach ($post as $name => $valueRaw)
	    {
	    	$value = addslashes($valueRaw);
			$regs = explode(",",$name);

			switch ($regs[0])
			{
	        case "datetime":
		        $this->datetime = $value;
				break;

			case "uservalue":
				SetField($this->con,$this->username.".".$post['usersetting'],
									$value,'User Preference');
				break;

			case "create":
				// create,database,table,id,column
	            $table = $regs[1].".".$regs[2];
	            $id    = $regs[3];
	            $col   = $regs[4];

	            if (array_key_exists($id,$rows))
	                $rows[$id]["set"] .= ", `$col` = '$value'";
				else
	                $rows[$id] = array(	"action"=>"INSERT INTO",
										"table"	=>$table,
										"set"	=>"SET `$col`='$value'",
										"hist"	=>array());

	            $rows[$id]['hist'][] = ", `column` = '$col', `after` = '$value'";
				break;

			case "update":
	        	// update,database,table,id,column
	            $table   = $regs[1].".".$regs[2];
	            $id      = $regs[3];
	            $col     = $regs[4];

	            if (array_key_exists($id,$rows))
	                $rows[$id]["set"] .= ", `$col` = '$value'";
	            else
	                $rows[$id] = array(	"action"=>"UPDATE",
										"table"	=>$table,
										"set"	=>"SET `$col` = '$value'",
										"where"	=>"WHERE id=$id",
										"id"	=>$id,
										"hist"	=>array());

	            $before = addslashes(ValueFromSql($this->con,"SELECT `$col` FROM $table WHERE id = $id"));

	            $rows[$id]['hist'][] = ", `column` = '$col', `after` = '$value',
															 `before` = '$before'";
				break;

			case "delete":
				// delete,database,table,id
	            $table = $regs[1].".".$regs[2];
	            $id    = $regs[3];

	            $rows[$id] = array(	"action"=>"DELETE FROM",
									"table"	=>$table,
	                                "where"	=>"WHERE id=$id",
	                                "id"	=>$id,
	                                "hist"	=>array(""));
				break;
			}
	    }

	    foreach ($rows as $row)
	    {
	        $sql = "$row[action] $row[table] $row[set] $row[where]";
	        if (!$this->con->query($sql))
			{
	            $this->errors .= "$sql\n".$this->con->error."<br/>\n";
				continue;
			}

			if (array_key_exists("id",$row))
				$id = $row["id"];
			else
				$id = ValueFromSql($this->con,"SELECT max(id) FROM $table");

			$sql = "INSERT INTO $this->historyitem
					SET `datetime`='".$this->datetime."', `table`='$row[table]', `id`='$id',
						`action`='$row[action]', `username`='".$this->username."'";
	        if (!$this->con->query($sql))
			{
	            $this->errors .= "$sql\n".$this->con->error."<br/>\n";
				continue;
			}

			$itemid = ValueFromSql($this->con,"SELECT max(itemid) FROM $this->historyitem");

	        foreach ($row["hist"] as $hist)
	        {
	             $sql = "INSERT INTO $this->historydetail SET `itemid`='$itemid' $hist";
	             if (!$this->con->query($sql))
	                $this->errors .= "$sql\n".$this->con->error."<br/>\n";
	        }
	    }

	    return $this->errors;
	}

	function ProcessUpload($files)
	{
		if (count($this->userpositions) == 0 || $files['upload']['name'] == "")
			return "";

		$name			= $files['upload']['name'];
		$tmp			= $files['upload']['tmp_name'];
		$name			= str_replace(" ","",$name);
		$ext			= str_replace(".","",strtolower(substr($name,strrpos($name,"."))));
		$table			= 'newsletter.documents';
		$exts			= ValueFromSql($this->con, "SELECT `value`
											FROM newsletter.fields
											WHERE name = 'acceptabledocumenttypes'");

		if ($exts == "" || strpos(",$exts,",",$ext,") === FALSE)
			return "The uploaded file (".$files['upload']['name'].") was not a valid file";

		switch ($ext)
		{
		case "rtf":
			$data	= file_get_contents($tmp);
			if (!$data)
				return "Could not read file - $tmp";
			if (!RtfIsValid($data))
				return "The uploaded file ($files[upload][name]) was not a genuine RTF file";
			break;
		}

		$hand = fopen($tmp, "r");
		$size = filesize($tmp);
		$data = addslashes(fread($hand, $size));
		fclose($hand);

		if (!$data)
			return "Could not read file - $tmp";

		if (ValueFromSql($this->con,"SELECT id FROM $table WHERE `name` = '$name'") == "")
			$sql = "INSERT INTO $table SET `size`=$size, `data`='$data', uploaded='$this->datetime', `name`='$name'";
		else
			$sql = "UPDATE $table SET `size`=$size, `data`='$data', uploaded='$this->datetime' WHERE `name`='$name'";

	    if (!$this->con->query($sql))
			return "$name\n".$this->con->error;

		$id = ValueFromSql($this->con, "SELECT id FROM $table WHERE `name` = '$name'");
		$sql = "INSERT INTO $this->historyitem
					SET `datetime`='$this->datetime', `table`='$table', `id`='$id',
						`action`='Uploaded', `username`='$this->username'";
	    if (!$this->con->query($sql))
			return "$name\n" . $this->con->error;

		return "";
	}
}

// Returns relevant information from the newsletter.newsletter table
function CurrentDates($con)
{
    $current = $con->query("SELECT * FROM newsletter.newsletters WHERE IsCurrent");

    if ($row = mysqli_fetch_array($current))
        return array("date"         => $row["date"],
                     "issuedate"    => $row["issueDate"]);
    else
        return array("date"         => "2007-01-01",
                     "issuedate"    => "2007-01-01");
}

// Set flag that this is a parent file
define( '_VALID_MOS', 1 );
define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));// Assume newsletter at top level in website
//require_once( JPATH_BASE.'/globals.php' );
require_once( JPATH_BASE.'/configuration.php' );
require_once ( JPATH_BASE.'/includes/defines.php' );
require_once ( JPATH_BASE.'/includes/framework.php' );
$app = JFactory::getApplication('site');
$user = JFactory::getUser();
$config = new JConfig();
define('BASE_URL', $config->live_site);
$con    = mysqli_connect($config->host,  $config->user, $config->password);
if (!$con)
    die('mysqli_connect failed');
$username	= $user->username;

$processor = new PostProcessor($con,$username);
$processor->ProcessPost($_POST);
$processor->ProcessUpload($_FILES);


?>
