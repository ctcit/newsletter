<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
        <title>CTC Newsletter - Newsletter Dates</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#newsletterstab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
        <script src="//cdn.ckeditor.com/4.7.0/standard/ckeditor.js"></script>
        <script> CKEDITOR.env.isCompatible = true;</script>
	</head>
	<body onload=Load()>
		<script>
		<?php
		$table 		= 'ctcweb9_newsletter.newsletters';
		$cols 		= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
		$rows 		= JsonFromQuery($con,"SELECT * FROM $table");

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
		<div id="node_<?php echo $table;?>"></div>
		<div id="menu"></div>
	    <script>
	        <?php
	        echo
	            "
	            var root = {table:  	  '$table',
	                         cols:        { $cols },
	                         rows:        { $rows },
							 prefs:	      [ $prefs ],
	                         sortdefault: 'date',
							 custom:	  {Default:  {head: true},
										   id:       {head: false},
										   iscurrent:{Make:MakeIsCurrent}}};
	            ";
	        require 'object.js';
			require 'table.js';
	        require 'editor.js';
	        ?>
	        function MakeIsCurrent(data)
	        {
	            if (!Number(data.iscurrent))
	                return data.iscurrent;

	            for (var y in root.rows)
	                undo.Set(root.rows[y],'iscurrent',data.id == root.rows[y].data.id ? '1' : '0');

	            for (var y in root.rows)
	                root.rows[y].Update();

	            return data.iscurrent;
	        }
	    </script>
	</body>
</html>