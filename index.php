<?php require 'newsletterloggedon.inc.php'; ?>
<html>
	<head>
	    <title>CTC Newsletter - Main Page</title>
		<link rel="shortcut icon" href="icon.gif" />
		<style>
			<?php require_once 'editor.css';?>
			#indextab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
		</style>
	</head>
	<body>
		<script>
		<?php
		require 'object.js';
		require 'table.js';
		require 'editor.js';
		?>
		</script>
		<?php require 'tabs.inc.php';?>
		<h2>Newsletter menu</h2>
		<ul>
	    <li><a class="link" href="javascript:Submit('events','type','Weekend Trips')">Edit Weekend Trips</a></li>
	    <li><a class="link" href="javascript:Submit('events','type','Day Trips')">Edit Day Trips</a></li>
	    <li><a class="link" href="javascript:Submit('events','type','Social Event')">Edit Social Events</a></li>
        <li><a class="link" href="../scripts/processNewsletterEvents.php" onclick="return confirm('Rewrite trip list and social events to website. Are you sure?')" target="_blank">Publish trips and social events to web site</a></li>
        <li><a class="link" href="../tripsignup/api/AddTripsFromNewsletter.php" onclick="return confirm('Update trip signup system. Are you sure?')" target="_blank">Update trip signup system</a></li>
		<?php
		$rows = $con->query("select 'All' section UNION select distinct section from ctcweb9_newsletter.notices");

		while ($row = mysqli_fetch_array($rows))
		{
			echo "<li><a class='link' href=\"javascript:Submit('notices','section','$row[section]')\">Edit Notices ($row[section])</a></li>";
		}
		?>
		<li><a class="link" href="index.php?resetuserpreferences=1">Reset User Preferences</a></li>
		</ul>
		<h2>Login Status</h2>
		<ul>
		<?php
		foreach ($processor->userpositions as $pos => $name)
		    echo "<li>You are logged in as $name and your position is $pos</li>";

		if ($_GET['resetuserpreferences'] == '1')
		{
			$con->query("delete from ctcweb9_newsletter.fields
						where `name` like '$processor->username.%'
						and `type` = 'User Preference'");
		}
		?>
		</ul>
		<h2>Instructions for Trip organisers, Social Convenors and Notice Contributors</h2>
		<ol>
		<li>Log in to <a href="<?php echo BASE_URL; ?>"><?php echo BASE_URL; ?></a> using your user name.</li>
		<li>Navigate to <a href="<?php echo BASE_URL; ?>/newsletter/index.php"><?php echo BASE_URL; ?>/newsletter/index.php</a> .</li>
		<li>Click on "Edit Weekend Trips" or whatever is appropriate for you.</li>
		<li>Scroll down to one of the bottom ten rows (they are grey, and the place new entries are entered)</li>
		<li>Click one of the <input value="+" type="button" class="tool" /> buttons,
			you will then see the fields for the entry.</li>
		<li>Enter the information for your entry - the grey row will turn green to indicate a new entry.
			Enter dates using the <input value=" &gt; " type="button"/> button.</li>
		<li>Repeat steps 5 and 6 for each new entry.</li>
		<li>Click the save button (<img src="save.gif" />).</li>
		<li>All Done!</li>
		</ol>
		<h2>Instructions for the Editor</h2>
		<ul>
		<li>Steps required to publish a newsletter</li>
			<ol>
				<li>At least 3 weeks <i>before</i> the publish date:</li>
					<ol>
						<li>Select the correct newsletter by going to the <i>Newsletters</i> tab,
						opening the entry for the correct newsletter (using the <input value="+" type="button" class="tool" /> button),
						checking the <b>iscurrent</b> field,
						and pressing the save button (<img src="save.gif" />).</li>
						<li>Update the closing date fields by going to the <i>Events</i> tab,
						pressing the update all rows button (<img src="update.gif">)
						and pressing the save button (<img src="save.gif" />).</li>
					</ol>
				<li>Wait for the Trip organisers, Social Convenors and Notice Contributors,
				to enter their contributions.</li>
				<li>On the <i>Main</i> tab, click <u>Generate: Newsletter</u>,
				open the document in Microsoft Word or Open Office (ignore warnings from MS Word).
				</li>
				<li>Check the document and make any corrections required, using the database
				(i.e. do not edit the Word document),
				using step 3 to check your changes as needed</li>
				<li>Use step 3 to regenerate the document, save it as a pdf document,
				and email it to all interested parties on the committee, asking for corrections.</li>
				<li>On the main tab, click <u>Generate: Trips Only</u>,
				and open it in Microsoft Word or Open Office.
				Save this document as pdf and email it to all the trip leaders, asking for corrections.
				Their email addresses are at the start of the document.</li>
				<li>Make further corrections as required.</li>
				<li>Some time at or before the morning of the publish date,
				Use step 3 to regenerate the document, perform any necessary manual repagination,
				save it as a pdf document, and email it to
				<a href="armagh@printstopplus.co.nz">armagh@printstopplus.co.nz</a>,
				who will print it.  It can normally be collected later in the afternoon from
				Print Stop Plus, 103 Armagh St.</li>
				<li>[Defunct step??] Also email it to
				<a href="christchurch@bivouac.co.nz">christchurch@bivouac.co.nz</a>
				to print for their customers to look at.</li>
                                <li>Sync the trip list to the database by clicking both the "Publish
                                trips and social events to web site" and "Update trip signup system"
                                links on the Newsletter main page.
			</ol>
		<li>Steps required to change the template documents.</li>
			<ol>
				<li>Go to the <i>Documents</i> tab.</li>
				<li>Download the template odt document
                                    in Open Office or Libre Offce.</li>
				<li>Make the required changes.</li>
				<li>Upload the changed document, using step 3 in the previous section.</li>
			</ol>
		</ul>
		<script>
		function Submit(table,name,value)
		{
			document.getElementById('submitform').action	= "ctcweb9_newsletter."+table+".php";
			document.getElementById('submitfield').value	= value;
			document.getElementById('submitfield').name		= name;
			document.getElementById('submitform').submit();
		}
		</script>
		<form name="events" action="ctcweb9_newsletter.events.php" method="post" id="submitform">
			<input type="hidden" name="type" value="" id="submitfield" />
		</form>
	</body>
</html>
