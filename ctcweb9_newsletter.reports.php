<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
        <title>CTC Newsletter - Report Selection</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require 'editor.css';?>
			#reportstab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
		<script type="text/javascript" src="../media/editors/tinymce/tinymce.min.js"></script>
	</head>
	<body onload="Load()">
		<script>
		<?php

		$table		= "ctcweb9_newsletter.reports";
		$where      = "1";
		$date       = $_POST["date"];
		$current    = CurrentDates($con);
		$issuedate  = $current["issuedate"];
		
		if ($date == "Current" || $date == "")    $where .= " AND $table.date >= '$issuedate'";

		$con->query("UPDATE $table SET `order` = id WHERE `order` < 0");
		
		$year		= substr($issuedate,0,4);
		$cols   	= JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
		$rows   	= JsonFromQuery($con,"SELECT * FROM $table WHERE $where");
		$dates		= array("All"			=>"Select All dates",
							"Current"		=>"Select dates for the current newsletter");

		$ids    =	/*"SELECT jos_content.id                   `id`, ".
					"	  concat(jos_categories.name,' ', ".
					"			 jos_content.title, ".
					"			 ' (', jos_content.id, ')') `name`, ".
					"	  jos_content.title                 `title`, ".
					"	  NOT (jos_content.id IN (SELECT reportid FROM $table)) `common` ".
					"  FROM ctcweb9_joom1.jos_content,  ".
					"	ctcweb9_joom1.jos_sections,  ".
					"	ctcweb9_joom1.jos_categories ".
					"  WHERE jos_categories.name = 'Aunty Iceaxe' ".
					"  AND   jos_sections.id = jos_content.sectionid ".
					"  AND   jos_categories.id = jos_content.catid ".
					"  ORDER BY jos_categories.name, jos_content.id ".
		
					" UNION ". */
					" SELECT tripreport.id as `id`, ".
					"     concat(tripreport.year, ' ', tripreport.title, ".
					"            ' (', tripreport.id, ')') as `name`, ".
					"     tripreport.title as `title`, ".
					"     tripreport.date_display as `date`, ".
					"     tripreport.year = '$year' AND ".
					"	  NOT (tripreport.id IN (SELECT reportid FROM $table)) `common` ".
					" from ctcweb9_tripreports.tripreport";
		
		// TODO reimplement Aunty Iceaxe report inclusion
		// TODO disambiguate Aunty Iceaxe reports from Trip Reports (?)
		
		$images	= 	"SELECT DISTINCT image1 name FROM $table UNION ".
					"SELECT DISTINCT image2 name FROM $table UNION ".
					"SELECT DISTINCT image3 name FROM $table UNION ".
					"SELECT DISTINCT image4 name FROM $table UNION ".
					"SELECT DISTINCT image5 name FROM $table UNION ".
					"SELECT DISTINCT image6 name FROM $table";

		// TODO reimplement image handling
		
		$settings = JsonFromQuery($con,"SELECT name, value FROM ctcweb9_newsletter.fields WHERE `type`='setting'");

		?>

		</script>
		    <form name="newsletterform" method="post" onsubmit="return false">
			<?php require 'tabs.inc.php';?>
		        <div>
		            <?php require 'buttons.inc.php'; ?>
		            Date:
		            <select name="date" id="date" onchange="Save()">
		                <?php echo ArrayToOptions($dates,$_POST['date'],"Current") ?>
		            </select>
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
					var images = { data: {".JsonFromQuery($con,$images)."} };
		            var root = {table:   	 '$table',
		                         cols:        { $cols },
		                         rows:        { $rows },
								 prefs:	      [ $prefs ],
		                         defaults:    { date: '$current[date]' },
		                         sortdefault: 'order',
								 settings:	  { $settings },
		                         source:      { reportid: { data: {".JsonFromQuery($con,$ids)."} },
												image1:   images,
												image2:   images,
												image3:   images,
												image4:   images,
												image5:   images,
												image6:   images}};
		            ";
		        require 'object.js';
				require 'table.js';
		        require 'editor.js';
		        ?>
			</script>
			<script>

		root.custom = {id:                {head:   		false},
						order:             {ro:     		true,
											head:   		true},
						date:          	   {head:   		true},
						reportid:          {head:   		true},
						reportlink:        {ro:     		true,
											head:   		true,
											Make:   		MakeReportLink},
						title:             {ro:     		true,
											head:   		true,
											Make:   		MakeTitle},
						titledate:         {ro:     		true,
											head:   		true,
											Make:   		MakeTitleDate},
						trampers:   	   {colspan:		1},
						image1:	   		   {layoutcolumn:	1},
						trailer:	   	   {ro:     		true,
											Make:   		MakeTrailer,
											layoutcolumn:	0}};

		function MakeReportLink(data)
		{
			if (root.cols.reportid.source.Data()[data.reportid] === null)
				return '';

			return "<a href=\"" +
                    "../tripreports/index.html#/tripreports/" +
		           data.reportid + "/\">Go to report</a>";
		}                            

		function MakeTitleObj(data)
		{
			var obj = root.cols.reportid.source.Data()[data.reportid];
		    if (obj == null)
		        return {title:'',titledate:''};
		    else
			    return {title: obj.title, titledate: obj.date};
		        
		    /*var title = root.cols.reportid.source.Data()[data.reportid].title;
		    var index = title.indexOf(': ');
		    
		    if (index < 0)  index = title.indexOf('. ');
		    if (index < 0)  index = title.indexOf(', ');
		    
		    return {titledate: title.substr(0,index),
		            title:     title.substr(index+1,title.length)}; */

		}                            

		function MakeTitle(data)
		{
		    return MakeTitleObj(data).title;
		}

		function MakeTitleDate(data)
		{
		    return MakeTitleObj(data).titledate;
		}

		function MakeTrailer(data)
		{
			var images = "", trampers = data.trampers;
			var re		= new RegExp(' ');

			for (var i = 1; data["image" + i] != null; i++)
			{
				var options = data["image" + i].replace(re,'').split('(')[0];
			
				if (options == '')
					continue;

				var src = 'http://localhost/ctc/newsletter/imageserver.php?'+
								'id=' + data.reportid + '&index='+i+'&'+options;
				images +=	'<!'+'-- INCLUDE INCLUDEPICTURE '+src+' --'+'>' +
							'<img src="'+src+'" />';
			}
			
			return	(trampers == "" ? ""  : "<p>Trampers: " + trampers + "</p>\r\n") +
					(images   == "" ? ""  : "<div style='width:"+root.settings.rtfpagewidth.value+"px'>" + images + "</div>\r\n");
		}
		        
	    </script>
	</body>
</html>
