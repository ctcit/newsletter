<table width="100%">
	<tr>
		<td class="tabtop" colspan="19" rowspan="1"/>
	</tr>
	<tr>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="indextab">
			<input type="button" onclick="Save('index.php')" value="Main" class="tabbutton" title="Go to the Main tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="eventstab">
			<input type="button" onclick="Save('ctcweb9_newsletter.events.php')" value="Events" class="tabbutton" title="Go to the Events tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="noticestab">
			<input type="button" onclick="Save('ctcweb9_newsletter.notices.php')" value="Notices" class="tabbutton" title="Go to the Notices tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="reportstab">
			<input type="button" onclick="Save('ctcweb9_newsletter.reports.php')" value="Reports" class="tabbutton" title="Go to the Reports tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="fieldstab">
			<input type="button" onclick="Save('ctcweb9_newsletter.fields.php')" value="Fields" class="tabbutton" title="Go to the Fields tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="documentstab">
			<input type="button" onclick="Save('ctcweb9_newsletter.documents.php')" value="Documents" class="tabbutton" title="Go to the Documents tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="newsletterstab">
			<input type="button" onclick="Save('ctcweb9_newsletter.newsletters.php')" value="Newsletters" class="tabbutton" title="Go to the Newsletters tab"/>
		</td>
		<td class="tabgap">&nbsp;</td>
		<td class="tabcell" id="historytab">
			<input type="button" onclick="Save('ctcweb9_newsletter.history.php')" value="History" class="tabbutton" title="Go to the History tab"/>
		</td>
		<td style="width:100%; text-align:right" class="tabgap">
		Generate:
		<?php
		$docrows = mysql_query("select replace(name,'.odt','') name from ctcweb9_newsletter.documents 
							 where name like '%.odt' and name not like '%merge%'",$con);
		$docdate = date('Ymd_Hms');

		while ($docrow = mysql_fetch_array($docrows))
			echo " <input type=\"button\" onclick=\"Save(null,'$docrow[name].odt')\" 
				 	value=\"$docrow[name]\" class=\"linkbutton\" title=\"Generate the $docrow[name] document\"/>";
		?>
		</td>
	</tr>
	<tr>
		<td id="errors" colspan="19" rowspan="1"><?php echo $processor->errors;?></td>
	</tr>
</table>