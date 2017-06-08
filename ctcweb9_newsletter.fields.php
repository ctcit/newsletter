<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
	    <title>CTC Newsletter - Fields</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#fieldstab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
        <script src="https://cdn.ckeditor.com/4.4.7/standard/ckeditor.js"></script>
        <script> CKEDITOR.env.isCompatible = true;</script>
	</head>
	<body onload=Load()>
		<script>
		<?php
		ProcessFields($con);

		$date        = $_POST["date"];

		if (!$con->query("UPDATE ctcweb9_newsletter.fields SET `order` = id WHERE `order` < 0"))
			die($con->error);

		$table 		= 'ctcweb9_newsletter.fields';
		$tableroot	= 'ctcweb9_newsletter.fieldsGrouped';
		$groupcols	= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table LIKE 'id'").','.
					  JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table LIKE 'type'");
		$grouprows	= JsonFromQuery($con,"SELECT min(id) id, `type` ".
										 "FROM $table GROUP BY `type`");
		$cols 		= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
		$positions  = "SELECT DISTINCT con_position name FROM ctcweb9_joom1.jos_contact_details";
		$columns   	= "SELECT DISTINCT `column`     name FROM $table";
		$types   	= "SELECT DISTINCT `type`       name FROM $table";

		?>
		</script>
	    <form name="newsletterform" method="post" onsubmit="return false">
		<?php require 'tabs.inc.php';?>
	        <div>
	            <?php require 'buttons.inc.php'; ?>
	            <span id=status></span>
	        </div>
			<div id="postdata"></div>
	    </form>
		<div id="node_<?php echo $tableroot;?>"></div>
		<div id="menu"></div>
	    <script>
	        <?php
	        echo
	            "
				var root = 	{table:     	'$tableroot',
	                         cols:        	{ $groupcols },
	                         rows:        	{ $grouprows },
						     prefs:	      	[ $prefs ],
							 ro:			true,
							 custom:		{ 'type': {head:true}}};
				var child = {table:       	'$table',
							 cols:      	{ $cols },
							 columns:		\"*\",
							 where:			\"1\",
							 linkcolumn:  	'type',
	                         sortdefault: 	'order',
	                         source:      	{data: 			{query: \"$positions\"	},
											column: 		{query: \"$columns\" 	},
											'type':			{query: \"$types\" 		} },
	                         custom:	  	{order:    		{head: true, ro:   true, sortorder:-1},
											name:    	 	{head: true},
											data:     		{head: true},
											column:     	{head: true},
											value:     		{head: true, ro:   true},
											valueoverride:	{head: true},
											'type':     	{head: true}}};
				root.children = [child];
	            ";
			require 'object.js';
			require 'table.js';
	        require 'editor.js';
	        ?>
	    </script>
	</body>
</html>