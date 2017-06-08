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
        <li><a class="link" href="../tripsignup/api/AddTripsFromNewsletter.php" onclick="return confirm('Publish trip lists for upcoming trips to website. Are you sure?')" target="_blank">Publish upcoming trip lists to web site</a></li>
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
				<li>On the <i>Main</i> tab, click <u>Generate Newsletter</u>,
				open the document in Microsoft Word, select the entire document and
				update all the fields (Ctrl+A, F9) - this updates all the linked documents and images.</li>
				<li>Check the document and make any corrections required, using the database
				(i.e. do not edit the Word document),
				using step 3 to check your changes as needed</li>
				<li>Use step 3 to regenerate the document, save it as a Word document (not RTF),
				and email it to all interested parties on the committee, asking for corrections.</li>
				<li>On the main tab, click <u>Generate Trips Only</u>,
				and open it in Microsoft Word.
				Save this document and email it to all the trip leaders, asking for corrections.
				Their email addresses are at the start of the document.</li>
				<li>Make further corrections as required.</li>
				<li>Some time at or before the morning of the publish date,
				Use step 3 to regenerate the document, perform any necessary manual repagination,
				save it as a Word document, and email it to
				<a href="armagh@printstopplus.co.nz">armagh@printstopplus.co.nz</a>,
				who will print it.  It can normally be collected later in the afternoon from
				Print Stop Plus, 103 Armagh St.</li>
				<li>Also email it to
				<a href="christchurch@bivouac.co.nz">christchurch@bivouac.co.nz</a>,
				<a href="lilburnel@landcareresearch.co.nz">lilburnel@landcareresearch.co.nz</a>, and
				<a href="richard.lobb@canterbury.ac.nz">richard.lobb@canterbury.ac.nz</a>.
				Bivouac print it out for their customers to look at, and
				Linda and Richard do the email distribution.</li>
			</ol>
		<li>Steps required to include an image or another document.</li>
			<ol>
				<li>Go to the <i>Documents</i> tab.</li>
				<li>Prepare your document and save it somewhere on your computer -
					RTF (Rich Text Format), DOC (MS Word), GIF, JPEG and PNG documents will be accepted.
					Unless you <i>want</i> to replace an existing document, make sure that an existing document with that name doesn't already exist,
					if it does, rename your document.</li>
				<li>Upload your document by clicking the <i>Browse...</i> button, selecting your document, clicking <i>Open</i> and
					then clicking the save button (<img src="save.gif" />).</li>
				<li>Go to the <i>Notices</i> Tab</li>
				<li>Create a new notice, giving it a tilte will help you manage it in the future.</li>
				<li>Go to the <b>type</b> field and select "Include".</li>
				<li>Go to the <b>includedocument</b> field and select your document.</li>
				<li>If your document was a picture then it will be displayed in the <b>includepreview</b> field, if it was an
					DOC or RTF document a link to the document will be shown.</li>
				<li>Click the save button (<img src="save.gif" />).</li>
			</ol>
		<li>Steps required to change the template documents.</li>
			<ol>
				<li>Go to the <i>Documents</i> tab.</li>
				<li>Download the template RTF document by clicking on the download link on the document list.</li>
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
