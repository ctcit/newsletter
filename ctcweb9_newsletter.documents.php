<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
        <title>CTC Newsletter - Report Selection</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#documentstab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
		<script type="text/javascript" src="/mambots/editors/tinymce3.0.3/jscripts/tiny_mce/tiny_mce.js"></script>
	</head>
	<body onload="Load()">
		<script>
			<?php
			$table	  = "newsletter.documents";
			$settings = JsonFromQuery($con,"SELECT name, value FROM newsletter.fields WHERE `type` = 'setting'");
			$cols     = JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
			$rows     = JsonFromQuery($con,"SELECT id, name, size, uploaded,
										concat('<a href=generate.php?name=', name, '>download</a> ',
										if (name like '%.rtf' or name like '%.xml' or name like '%.odt',
										concat('<a href=generate.php?expand=', name, '>generate</a>'),'')) data
										FROM $table");
			?>
		</script>
	    <form name="newsletterform" method="post" onsubmit="return false" enctype="multipart/form-data">
			<?php require 'tabs.inc.php';?>
	        <div>
	            <?php require 'buttons.inc.php'; ?>
	            <span id="status"></span>
				<b>Upload File:</b>
				<input type="hidden" name="MAX_FILE_SIZE" value="4000000" />
				<input type="file" id="upload" name="upload" onchange="UploadChange()"/>
				<span id="uploadwarning" class="qc"></span>
	        </div>
			<div id="postdata"></div>
	    </form>
		<div id="node_<?php echo $table;?>"></div>
		<div id="menu"></div>
	    <script>
	        <?php    
	        echo 
	            "
	            var root = {table:   		'$table',
	                         cols:      	{ $cols },
	                         rows:     		{ $rows },
							 prefs:			[ $prefs ],
							 settings:		{ $settings },
							 custom: 		{name:{head:true},
											 size:{head:true, ro: true},
											 data:{head:true, ro: true},
											 uploaded:{head:true, ro: true}}};
	            ";
	        require 'object.js';
	        require 'table.js';
	        require 'editor.js';
	        ?>
			function UploadChange()
			{
				var re			= new RegExp(' ','g');
				var upload		= document.getElementById('upload').value;
				var nameparts	= upload.replace(new RegExp("\\\\","g"),"/").split("/");
				var name 		= nameparts[nameparts.length-1].toLowerCase().replace(re,'');
				var extparts	= name.split(".");
				var ext			= extparts[extparts.length-1];
				var warning		= ext + " is not a valid document type";
				var exts		= root.settings.acceptabledocumenttypes.value.split(","); 
				
				for (var i in exts)
				{
					if (ext != "" && ext == exts[i] && extparts.length > 1)
						warning = "";
				}
				
				for (var i in root.rows)
				{
					if (name != "" && name == root.rows[i].data.name.toLowerCase())
						warning = name + " already exists";
				}
				
				document.getElementById('uploadwarning').innerHTML = name == "" ? "" : warning;
			}
		</script>
	</body>
</html>
