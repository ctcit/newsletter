<?php require 'newsletterloggedon.inc.php'; ?>
<html>
    <head>
        <title>CTC Newsletter - Trips and Social Events</title>
        <link rel="shortcut icon" href="icon.gif" />
        <style>
            <?php require 'editor.css';?>
            #eventstab { border: solid 2px black; border-bottom: solid 2px white; background: none;}
        </style>
        <script type="text/javascript" src="/mambots/editors/tinymce3.0.3/jscripts/tiny_mce/tiny_mce.js"></script>
    </head>
    <body onload="Load()">
        <script>
        <?php
        $table      = 'ctcweb9_newsletter.events';
        $type       = $_POST["type"];
        $date       = $_POST["date"];
        $current    = CurrentDates($con);
        $issuedate  = $current["issuedate"];
        $search     = $_POST["search"];
        $searchexpr = "0";
        $where      = "1";

        if (ereg("Social",$type))  $defaults .= ",type:       'Social'";
        if (ereg("Social",$type))  $defaults .= ",grade:      'Club Night'";
        if (ereg("Weekend",$type)) $defaults .= ",triplength: '2'";

        if (ereg("Social",$type))  $where .= " AND type = 'Social'";
        if (ereg("Weekend",$type)) $where .= " AND triplength > 1";
        if (ereg("Day",$type))     $where .= " AND triplength <= 1";
        if (ereg("Trips",$type))   $where .= " AND type = 'Trip'";
        if (ereg("[0-9]",$date))   $where .= " AND date_format(date,'%Y-%m') like '$date%'";
        if ($date == "Current")    $where .= " AND date >= '$issuedate'";
        if ($date == "")           $where .= " AND date >= '$issuedate'";
        if ($search != "")         $searchexpr = "(leader LIKE '%$search%') OR
                                                  (grade  LIKE '%$search%') OR
                                                  (title  LIKE '%$search%') OR
                                                  (text   LIKE '%$search%')";

        $triplengths =  "SELECT triplength name ".
                        "  FROM   $table ".
                        "  WHERE  triplength IS NOT NULL and triplength <> ''".
                        "  GROUP BY triplength";
        $grades      =  "SELECT grade name ".
                        "  FROM   $table ".
                        "  WHERE  grade IS NOT NULL and grade <> ''".
                        "  GROUP BY grade";
        $costs       =  "SELECT cost name ".
                        "  FROM   $table ".
                        "  WHERE  cost IS NOT NULL and cost <> ''".
                        "  GROUP BY cost";
        $maps        =  "SELECT name                        id,".
                        "       concat(`name`,' ',`title`)  name,".
                        // "       concat(`group`,' ',`title`) title,".
                        "       name in ( SELECT map1 FROM $table UNION ".
                        "                 SELECT map2 FROM $table UNION ".
                        "                 SELECT map3 FROM $table       ) common ".
                        "  FROM   ctcweb9_newsletter.maps ".
                        "  ORDER BY name";
        $leaders     =  "SELECT fullName AS name,
                        primaryEmail AS email,
                        homePhone AS phone,
                        concat( fullName,' ',homePhone,' ',primaryEmail,' ') as title,
                        fullName in
                            (select leader from $table) as common
                        FROM ctcweb9_ctc.view_members
                        WHERE status = 'Active'
                        ORDER BY name";
        $eventtypes  = "select 'Social' name union select 'Trip' name";
        $departurepoints = "select 'Z Station Papanui' name union select 'Caltex Russley' name union select 'Contact Leader' name";

        $cols        = JsonFromQuery($con,"SHOW FULL COLUMNS FROM $table");
        $rows        = JsonFromQuery($con,"SELECT *, ($searchexpr) found
                                       FROM   $table
                                       WHERE ($where) or ($searchexpr)");
        $types  = array("All"           =>"Select trips and social events",
                        "Social Events" =>"Select social events only",
                        "Trips"         =>"Select day trips and weekend trips",
                        "Weekend Trips" =>"Select weekend trips only",
                        "Day Trips"     =>"Select day trips only");

        $dates  = array("All"           =>"Select All dates",
                        "Current"       =>"Select dates for the current newsletter");
        $dates  += ArrayFromQuery($con,"SELECT distinct date_format(date,'%Y') value, '' title
                                       FROM   $table
                                       UNION
                                       SELECT distinct date_format(date,'%Y-%m') value, '' title
                                       FROM   $table");

        ?>
        </script>
            <?php require 'tabs.inc.php';?>
            <form name="newsletterform" method="post" onsubmit="return false;">
                <div>
                    <?php require 'buttons.inc.php'; ?>
                    Trip/Social:
                    <select name="type" id="type" onchange="Save()">
                        <?php echo ArrayToOptions($types,$_POST['type'],"All"); ?>
                    </select>
                    Date:
                    <select name="date" id="date" onchange="Save()">
                        <?php echo ArrayToOptions($dates,$_POST['date'],"Current"); ?>
                    </select>
                    Search:
                    <input type="text" id="search" name="search" value="<?php echo $_POST['search'];?>"/>
                    <span id="status"></span>
                </div>
                <div id="postdata"><input type="hidden" name="random" value="<?php echo date('Ymd_His');?>"></div>
            </form>
            <div id="node_<?php echo $table;?>"></div>
            <div id="menu"></div>
            <script>
                <?php
                echo
                    "
                    var maps       = {data:{".JsonFromQuery($con,$maps)."}};
                    var root       = {table:      '$table',
                                      cols:        { $cols },
                                      rows:        { $rows },
                                      prefs:       [ $prefs ],
                                      defaults:    { date: '$current[date]' $defaults },
                                      sortdefault: 'date',
                                      source:      {leader:      	{ data: {".JsonFromQuery($con,$leaders)."} },
                                                    grade:       	{ data: {".JsonFromQuery($con,$grades)."} },
                                                    cost:        	{ data: {".JsonFromQuery($con,$costs)."} },
                                                    departurepoint: { data: {".JsonFromQuery($con, $departurepoints)."} },
                                                    'type':      	{ data: {".JsonFromQuery($con,$eventtypes)."} },
                                                    triplength:  	{ data: {".JsonFromQuery($con,$triplengths)."} },
                                                    map1:        	maps,
                                                    map2:        	maps,
                                                    map3:        	maps},
                                      issuedate:   '$issuedate'};
                    ";
                require 'object.js';
                require 'table.js';
                require 'editor.js';
                ?>
        root.custom = {  date:           {head: true},
                         triplength:     {Show: ShowOnTripOnly},
                         datedisplay:    {ro:   true,
                                          Make: MakeDateDisplay},
                         leader:         {Show: ShowOnTripOnly,
                                          head: root.type != "Social Events"},
                         leaderphone:    {Show: ShowOnTripOnly,
                                          ro:   true,
                                          Make: MakeLeaderPhone},
                         leaderplus:     {Show: ShowOnTripOnly},
                         leaderemail:    {Show: ShowOnTripOnly,
                                          ro:   true,
                                          Make: MakeLeaderEmail},
                         showemail:      {Show: ShowOnTripOnly},
                         departurepoint: {Show: ShowOnTripOnly},
                         map1:           {Show: ShowOnTripOnly, layoutcolumn:1},
                         map2:           {Show: ShowOnTripOnly},
                         map3:           {Show: ShowOnTripOnly},
                         closetext:      {Show: ShowOnTripOnly},
                         close1:         {Show: ShowOnTripOnly,
                                          ro:   true,
                                          Make: MakeClose1},
                         close2:         {Show: ShowOnTripOnly,
                                          ro:   true,
                                          Make: MakeClose2},
                         cost:           {Show: ShowOnTripOnly},
                         order:          {ro:   true,
                                          head: true},
                         qc:             {ro:   true,
                                          Make: MakeQC},
                         title:          {head: true,           layoutcolumn:0},
                         text:           {height: 200, Wysiwyg: WysiwygFunction}};

        function MakeCloseDate(data)
        {
            var dClose  = DateFromString(data.date);

            if (data.triplength >= 2)
                dClose.setDate(dClose.getDate()-7);

            while (dClose.getDay() != 4)
                dClose.setDate(dClose.getDate()-1);

            return dClose;
        }

        function MakeClose1(data)
        {
            if (data.closetext != '')
                return data.closetext;
            else if (MakeCloseDate(data) > DateFromString(root.issuedate))
                return "Closes:";
            else
                return "Closed:";
        }

        function MakeClose2(data)
        {
            if (data.closetext != '')
                return '';

            var dClose = MakeCloseDate(data);

            return dClose.getDate() + ' ' + months[dClose.getMonth()].substr(0,3);
        }

        function MakeLeaderPhone(data)
        {
            var leader = root.source.leader.Data()[data.leader.toLowerCase()];

            if (leader == null)
                return '';
            else
                return leader.phone;
        }

        function MakeLeaderEmail(data)
        {
            var leader = root.source.leader.Data()[data.leader.toLowerCase()];

            if (leader == null)
                return '';
            else
                return leader.email;
        }

        function MakeDateDisplay(data)
        {
            var dStart  = new Date(DateFromString(data.date));
            var prefix  = '';

            if (Number(data.triplength) <= 1 || data.type == 'Social')
                return days[dStart.getDay()] + ' ' + dStart.getDate() + ' ' + months[dStart.getMonth()];

            var dStop   = new Date(dStart);

            dStop.setDate(dStart.getDate() + eval(data.triplength) - 1);

            if (data.triplength == '2' && days[dStart.getDay()] == 'Saturday')
                prefix = 'Weekend ';
            else if (data.triplength == '3' || data.triplength == '4' || data.triplength == '5')
                prefix = 'Long Weekend ';
            else
                prefix = 'Multi day trip ';

            if (dStart.getMonth() == dStop.getMonth())
                return prefix +
                        dStart.getDate() + '-' +
                        dStop.getDate() + ' ' + months[dStop.getMonth()];
            else
                return prefix +
                        dStart.getDate() + ' ' + months[dStart.getMonth()] + '-' +
                        dStop.getDate() + ' ' + months[dStop.getMonth()];
        }

        function MakeQC(data,row)
        {
            if (row.blank && !row.changed)
                return '';

            var comp = {};

            for (var i in row.datatable.cols)
                comp[i] = data[i].toLowerCase().replace(new RegExp(' ','g'),'');

            var dow         = days[(new Date(DateFromString(data.date))).getDay()];
            var socialgrade = comp.grade  == 'clubnight'    ||
                              comp.grade  == 'socialevent'  ||
                              comp.grade  == 'noclubnight'      ;

            if (comp.grade  == '')                              return '<span class=qc>No grade</span>';
            if (comp.title  == '')                              return '<span class=qc>No title</span>';
            if (comp.text   == '')                              return '<span class=qc>No text</span>';
            if (comp.type == 'trip'   && comp.leader == '')     return '<span class=qc>No leader</span>';
            if (comp.type == 'trip'   && comp.map1 == '')       return '<span class=qc>No map</span>';
            if (comp.type == 'trip'   && comp.cost == '')       return '<span class=qc>No cost</span>';
            if (comp.type == 'trip'   && socialgrade)           return '<span class=qc>Wrong grade</span>';
            if (comp.type == 'social' && !socialgrade)          return '<span class=qc>Wrong grade</span>';
            if (comp.grade == 'clubnight' && dow != 'Wednesday') return '<span class=qc>Club night not Wednesday</span>';
            if (comp.triplength == 2 && dow != 'Saturday')      return '<span class=qc>Weekend trip not Saturday</span>';

            return '';
        }

        function ShowOnTripOnly(data)
        {
            return data.type == 'Trip';
        }

        </script>
    </body>
</html>
