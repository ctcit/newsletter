<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
	    <title>CTC Newsletter - Notices</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#noticestab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
        <script src="https://cdn.ckeditor.com/4.4.7/standard/ckeditor.js"></script>
        <script> CKEDITOR.env.isCompatible = true;</script>
	</head>
	<body onload=Load()>
		<script>
		<?php
		$tableroot	 = 'ctcweb9_newsletter.noticesGrouped';
		$table		 = 'ctcweb9_newsletter.notices';
		$section     = $_POST["section"];
		$date        = $_POST["date"];
		$current     = CurrentDates($con);
		$issuedate   = $current["issuedate"];
		$search      = $_POST["search"];
		$searchexpr  = "0";
		$sectionexpr = "1";
		$dateexpr    = "1";

		if ($section != "All" && $section != "")  $sectionexpr = "`section` = '$section'";
		if ($date == "Current" || $date == "")    $dateexpr    = "`date` >= '$issuedate'";
		if ($search != "")         				  $searchexpr  = "((`title` LIKE '%$search%') OR ".
						                                         " (`text`  LIKE '%$search%'))";

		$sections	= array("All"			=>"");
		$sections	+= ArrayFromQuery($con,"SELECT DISTINCT section value, '' title FROM $table");
		$dates		= array("All"			=>"Select All dates",
							"Current"		=>"Select dates for the current newsletter");

		$con->query("UPDATE $table SET `order` = id WHERE `order` < 0");
		?>

		</script>
	    <form name="newsletterform" method="post" onsubmit="return false">
			<?php require 'tabs.inc.php';?>
	        <div>
	            <?php require 'buttons.inc.php'; ?>
	            Section:
	            <select name="section" id="section" onchange="Save()">
	                <?php echo ArrayToOptions($sections,$_POST['section'],"All");  ?>
	            </select>
	            Date:
	            <select name="date" id="date" onchange="Save()">
	                <?php echo ArrayToOptions($dates,$_POST['date'],"Current");?>
	            </select>
				Search:
				<input type="text" id="search" name="search" value="<?php echo $_POST['search'];?>"/>
				<span id="status"></span>
	        </div>
			<div id="postdata"></div>
	    </form>
		<div id="node_<?php echo $tableroot;?>"></div>
		<div id="menu"></div>
	    <script>
	        <?php
			$groupcols	= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table LIKE 'id'").','.
						  JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table LIKE 'section'");
			$grouprows	= JsonFromQuery($con,"SELECT min(id) id, section, SUM($searchexpr) found ".
											 "FROM $table WHERE $sectionexpr GROUP BY section");
			$cols 		= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
			$settings	= JsonFromQuery($con,"SELECT name, value FROM ctcweb9_newsletter.fields WHERE `type` = 'setting'");
			$types      = "SELECT DISTINCT type    name FROM $table";
			$sections   = "SELECT DISTINCT section name FROM $table";
			$documents  = "SELECT name FROM ctcweb9_newsletter.documents";

	        echo
	            "
				var root = 	{table:     	'$tableroot',
	                         cols:      	{ $groupcols },
	                         rows:        	{ $grouprows },
							 prefs:			[ $prefs ],
							 settings:	  	{ $settings },
							 ro:			true,
							 rowopen:		true,
							 custom:		{ section: {head: true }}};
	            var child = {table:     	'$table',
	                         cols:      	{ $cols },
	                         defaults:    	{ date: '$current[date]' },
							 columns:		\"*,$searchexpr found\",
							 where:			\"$dateexpr or $searchexpr\",
							 linkcolumn:	'section',
	                         source:      	{	type:     		{ query: \"$types\" },
												section:  		{ query: \"$sections\" },
												includedocument:{ query: \"$documents\" }},
	                         custom:      	{ 	order:  	   {ro:true,sortorder:-1},
												section:	   {head:true},
												type:   	   {head:true},
												title:  	   {head:true},
												date:   	   {head:true},
												text:		   {Show:	ShowText,
																height: 200,
																Wysiwyg: WysiwygFunction},
												includepreview:{Show:	ShowIncludePreview,
																ro:		true,
																Make:   MakeIncludePreview},
												includedocument:{Show:	ShowIncludePreview}}};
				root.children = [child];
	            ";
	        require 'object.js';
			require 'table.js';
	        require 'editor.js';
	        ?>
			function ShowText(data)
			{
				return 	data.type.toLowerCase().indexOf("include") != 0 ;
			}
			function ShowIncludePreview(data)
			{
				return data.type.toLowerCase().indexOf("include") == 0;
			}
			function MakeIncludePreview(data)
			{
				var re  = new RegExp(" ","g");
				var src = "<?php echo BASE_URL; ?>/newsletter/generate.php?name=" +
							data.includedocument.replace(re,'%20');

				if 		(child.source.includedocument.Data()[data.includedocument.toLowerCase()] == null)
					return 	'';
				else if (data.includedocument.toLowerCase().indexOf('.rtf') > 0 ||
						 data.includedocument.toLowerCase().indexOf('.doc') > 0 	)
					return	'<' + '!-- INCLUDE INCLUDETEXT ' + src + ' --' + '>' +
							'<input type=button onclick=\'window.location.replace("' + src + '")\''+
							' value="Download '+data.includedocument+'" class="linkbutton" />';
				else if (data.includedocument.toLowerCase().indexOf('.htm') > 0 	)
					return	'<' + '!-- INCLUDE INCLUDEHTML ' + src + ' --' + '>' +
							'<input type=button onclick=\'window.location.replace("' + src + '")\''+
							' value="Download '+data.includedocument+'" class="linkbutton" />';
				else
					return	'<' + '!-- INCLUDE INCLUDEPICTURE ' + src + '&size=1.0 --' + '>' +
							'<div style="width:'+root.settings.rtfpagewidth.value+'px">'+
							'<img src="' +src +'&size=1.0" />'+
							'</div>';
			}

	    </script>
	</body>
</html>