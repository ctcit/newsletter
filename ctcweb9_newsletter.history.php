<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
		<title>CTC Newsletter - History</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#historytab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
			.before { color: darkred;}
			.after  { color: green;}
		</style>
		<script type="text/javascript" src="/mambots/editors/tinymce3.0.3/jscripts/tiny_mce/tiny_mce.js"></script>
	</head>
	<body onload="Load();LoadTableData();">
	<script>
		<?php
		$table 			= 'ctcweb9_newsletter.historyitem';
		$detail 		= 'ctcweb9_newsletter.historydetail';
		$tablecols		= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
		$detailcols		= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $detail");
		$where 		 	= "1=1";
		$filterdate   	= $_POST["filterdate"];
		$filtertable	= $_POST["filtertable"];
		$filterids		= $_POST["filterids"];
		$filterlimit	= $_POST["filterlimit"];
		$filterids		= trim($filterids,',');
		$filtertables	= array("All"								=>"Select history for all tables",
								"ctcweb9_newsletter.events"			=>"Select history for this table",
								"ctcweb9_newsletter.notices"		=>"Select history for this table",
								"ctcweb9_newsletter.reports"		=>"Select history for this table",
								"ctcweb9_newsletter.fields"			=>"Select history for this table",
								"ctcweb9_newsletter.newsletters"	=>"Select history for this table");
		$filterdates	= array("All"								=>"Select history for all dates",
								"Current"							=>"Select history for the current newsletter");
		$filterdates	+= ArrayFromQuery($con,"SELECT	distinct date_format(datetime,'%Y-%m')	value, 
														'Select history for this month' 		title
												FROM   $table");
		$startdate 		= ValueFromSql($con,"SELECT max(DATE_ADD(prev.issuedate,INTERVAL 1 DAY)) 
											 FROM   ctcweb9_newsletter.newsletters prev,
													ctcweb9_newsletter.newsletters curr
											 WHERE  prev.issuedate < curr.issuedate AND curr.iscurrent");

		if (!array_key_exists($filtertable,$filtertables))
			$filtertable = "All";

		if ($filtertable != "All")
			$where .= " AND `table` = '$filtertable'";
			
		if ($filterids != "")
			$where .= " AND `id` in ($filterids)";
			
		if ($filterids != "" && $filterdate == "")
			$filterdate = "All";
			
		if ($filterlimit == "" || $filterlimit == "0")
			$filterlimit = "1000";

		if 		($filterdate == "Current" || $filterdate == "")
			$where	.= " AND `datetime` >= '$startdate'";
		else if ($filterdate != "All")
			$where	.= " AND date_format(`datetime`,'%Y-%m') like '$filterdate%'";

		$tablerows	= JsonFromQuery($con,"SELECT * FROM $table WHERE $where 
										  ORDER BY itemid desc LIMIT 0, $filterlimit");
		$tables		= JsonFromQuery($con,"SELECT distinct `table` FROM $table WHERE $where 
										  ORDER BY itemid desc LIMIT 0, $filterlimit");
		?>
		</script>
	    <form name="newsletterform" method="post" onsubmit="return false">
			<?php require 'tabs.inc.php';?>
	        <div>
	            <?php require 'buttons.inc.php'; ?>
				<b>Date:</b>
				<select name="filterdate" onchange="Save()">
				<?php echo ArrayToOptions($filterdates,$filterdate,"Current"); ?>
				</select>
				<b>Table:</b>
				<select name="filtertable" onchange="Save()">
				<?php echo ArrayToOptions($filtertables,$filtertable,"All"); ?>
				</select>
				<b>IDs:</b>
				<input name=filterids value="<?php echo $filterids;?>" />
				<b>Limit:</b>
				<input name=filterlimit value="<?php echo $filterlimit;?>" />
				<span id="status"></span>
			</div>
			<div id="postdata"></div>
	    </form>
		<div id="node_<?php echo $table;?>"></div>
		<div id="menu"></div>
	    <script>
	        <?php    
	        echo 
	            "
	            var root = { table:	  	 	'$table',
	                         cols:       	{ $tablecols },
	                         rows:       	{ $tablerows },
	                         sort:			'datetime',
							 tables:		{ $tables },
	                         ro:          	true,
	                         custom:	  	{id: 		{Show: AlwaysTrue, ReadOnlyHtml: GenericId},
											 datetime:	{head: true},
											 username:	{head: true},
											 table:   	{head: true},
											 action:  	{head: true}},
							 PostBuild:		PostBuildHistory};
				var detail = {table:		'$detail',
							 cols:      	{ $detailcols },
							 rows:			{},
	                         ro:        	true,
							 linkcolumn:	'itemid',
							 columns:		\"*\",
							 custom:	  	{column:   	{head: 			true},
											 before:	{newrow:    	true,	
														 ReadOnlyHtml:	CompareHtml, 
														 CellUpdate:	AlwaysTrue},
											 after:		{newcolumn: 	true,	
														 ReadOnlyHtml:	CompareHtml, 
														 CellUpdate:	AlwaysTrue}}};
				root.children = [ detail ];
	            ";
	        require 'object.js';
			require 'table.js';
	        require 'editor.js';
	        ?>
			function StripTag(s)
			{
				return s.replace(new RegExp('&','g'),'&amp;').
						 replace(new RegExp('<','g'),'&lt;').
						 replace(new RegExp('>','g'),'&gt;');
			}
			
			function TableIndexInfo(data)
			{
				ObjectCopy(data,this);
				this.ids = {};
				this.Request();
			}
			
			TableIndexInfo.prototype.StateChange = function()
			{
				if (this.request.readyState != 4)
					return;
					
				var indexes = eval("(" + this.request.responseText + ")");
				var idcol 	= 'id', expr = '';
					
				for (var index in indexes)
				{
					if 		(indexes[index].key_name == "PRIMARY")
						idcol = indexes[index].column_name;
					else if (indexes[index].non_unique == 0)
						expr += ",' " 		+ indexes[index].column_name + ":'"  + 
								",'<b>',`"	+ indexes[index].column_name + "`,'</b>'";
				}
				
				this.query		= "select concat(''" + expr + ") id from " + this.table + " where " + idcol + "=";
				this.request	= null;
			}
			
			TableIndexInfo.prototype.Request = function()
			{
				var table = this;

				this.request = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("MSXML2.XMLHTTP.3.0");
				this.request.open( "GET", "json.php?array=show index from " + this.table, true );
				this.request.onreadystatechange = function() {table.StateChange(); };
				this.request.send(null);
			}
			
			function LoadTableData()
			{
				for (var i in root.tables)
					root.tables[i] = new TableIndexInfo(root.tables[i]);
			}
			
			function GenericId(row)
			{
				var id		= row.data.id;
				var table	= root.tables[row.data.table];
				var cell	= 'cell' + row.id + 'id';

				if (table.ids[id] != null)
					return table.ids[id];

				var request = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("MSXML2.XMLHTTP.3.0");
				
				request.open( "GET", "json.php?array=" + table.query + id, true );
				request.onreadystatechange = function() 
				{
					if (request.readyState != 4)
						return;
							
					var data = eval("(" + request.responseText + ")");
					var html = id + ' ( ' + (data.length == 0 ? '<b>Deleted</b>' : data[0].id) + ' )';

					table.ids[id]							= html;
					document.getElementById(cell).innerHTML	= html;
					request									= null;
				};
				request.send(null);

				return id + " Loading...";
			}
			
			function CompareHtml(row)
			{
				var istart = 0, istop = 0;
				var before = row.data.before;
				var after = row.data.after;
				var target = row.data[this.field];
				
				while (istart < before.length && 
					   istart < after.length && 
					   before[istart] == after[istart])
					istart++;
					   
				while (istop < before.length-istart && 
					   istop < after.length-istart && 
					   before[before.length-1-istop] == after[after.length-1-istop])
					istop++;
					   
				return	StripTag(target.substr(0,istart)) + 
						"<span class=" + this.field + ">" +
						StripTag(target.substr(istart,target.length-istart-istop))+
						"</span>" +
						StripTag(target.substr(target.length-istop));
			}
			
			function PostBuildHistory()
			{
				this.PostBuildBase = DataTable.prototype.PostBuild;
				this.PostBuildBase();
				this.post.filtertable	= null;
				this.post.filterids 	= null;
			}
	    </script>
	</body>
</html>
