<?php require 'newsletter.inc.php'; ?>
<?php
/* This structure encodes the difference between ISO-8859-1 and Windows-1252,
   as a map from the UTF-8 encoding of some ISO-8859-1 control characters to
   the UTF-8 encoding of the non-control characters that Windows-1252 places
   at the equivalent code points. */

$cp1252_map = array(
    "\xc2\x80" => "\xe2\x82\xac", /* EURO SIGN */
    "\xc2\x82" => "\xe2\x80\x9a", /* SINGLE LOW-9 QUOTATION MARK */
    "\xc2\x83" => "\xc6\x92",     /* LATIN SMALL LETTER F WITH HOOK */
    "\xc2\x84" => "\xe2\x80\x9e", /* DOUBLE LOW-9 QUOTATION MARK */
    "\xc2\x85" => "\xe2\x80\xa6", /* HORIZONTAL ELLIPSIS */
    "\xc2\x86" => "\xe2\x80\xa0", /* DAGGER */
    "\xc2\x87" => "\xe2\x80\xa1", /* DOUBLE DAGGER */
    "\xc2\x88" => "\xcb\x86",     /* MODIFIER LETTER CIRCUMFLEX ACCENT */
    "\xc2\x89" => "\xe2\x80\xb0", /* PER MILLE SIGN */
    "\xc2\x8a" => "\xc5\xa0",     /* LATIN CAPITAL LETTER S WITH CARON */
    "\xc2\x8b" => "\xe2\x80\xb9", /* SINGLE LEFT-POINTING ANGLE QUOTATION */
    "\xc2\x8c" => "\xc5\x92",     /* LATIN CAPITAL LIGATURE OE */
    "\xc2\x8e" => "\xc5\xbd",     /* LATIN CAPITAL LETTER Z WITH CARON */
    "\xc2\x91" => "\xe2\x80\x98", /* LEFT SINGLE QUOTATION MARK */
    "\xc2\x92" => "\xe2\x80\x99", /* RIGHT SINGLE QUOTATION MARK */
    "\xc2\x93" => "\xe2\x80\x9c", /* LEFT DOUBLE QUOTATION MARK */
    "\xc2\x94" => "\xe2\x80\x9d", /* RIGHT DOUBLE QUOTATION MARK */
    "\xc2\x95" => "\xe2\x80\xa2", /* BULLET */
    "\xc2\x96" => "\xe2\x80\x93", /* EN DASH */
    "\xc2\x97" => "\xe2\x80\x94", /* EM DASH */

    "\xc2\x98" => "\xcb\x9c",     /* SMALL TILDE */
    "\xc2\x99" => "\xe2\x84\xa2", /* TRADE MARK SIGN */
    "\xc2\x9a" => "\xc5\xa1",     /* LATIN SMALL LETTER S WITH CARON */
    "\xc2\x9b" => "\xe2\x80\xba", /* SINGLE RIGHT-POINTING ANGLE QUOTATION*/
    "\xc2\x9c" => "\xc5\x93",     /* LATIN SMALL LIGATURE OE */
    "\xc2\x9e" => "\xc5\xbe",     /* LATIN SMALL LETTER Z WITH CARON */
    "\xc2\x9f" => "\xc5\xb8"      /* LATIN CAPITAL LETTER Y WITH DIAERESIS*/
);

function cp1252_to_utf8($str) {
        global $cp1252_map;
        return  strtr(utf8_encode($str), $cp1252_map);
}

class RtfGenerator
{
    var $maptagsrc  = array("h1"        => array("{\\pard\\sa220\\fs40 ",   "\\par}\r\n"),
                            "h2"        => array("{\\pard\\sa220\\fs36 ",   "\\par}\r\n"),
                            "h3"        => array("{\\pard\\sa220\\fs32 ",   "\\par}\r\n"),
                            "h4"        => array("{\\pard\\sa220\\fs28 ",   "\\par}\r\n"),
                            "div"       => array("{\\pard\\sa220 ",         "\\par}\r\n"),
                            "p"         => array("{\\pard\\sa220 ",         "\\par}\r\n"),
                            "input"     => array("",                        ""),
                            "b"         => array("{\\b ",                   "}"), 
                            "i"         => array("{\\i ",                   "}"), 
                            "strong"    => array("{\\b ",                   "}"), 
                            "em"        => array("{\\i ",                   "}"), 
                            "br"        => array("\\line ",                 ""));
    var $maptag     = array();
    var $mapchar    = array("amp"  => "&",
                            "nbsp" => "\\~",
                            "gt"   => "<",
                            "lt"   => ">");
    var $con;
    var $fielddata;
    var $debugtext = "";

    function RtfGenerator($con)
    {
        $this->con          = $con;
        $this->fielddata    = ProcessFields($con);
        
        foreach ($this->maptagsrc as $key => $value)
        {
            $this->maptag["$key"]  = $value[0];
            $this->maptag["$key/"] = $value[0].$value[1];
            $this->maptag["/$key"] = $value[1];
        }
    }

    function StripTag($html,$tagopen,$tagclose)
    {
        $arr = FieldExplode(trim($html),$tagopen,$tagclose);
        
        for ($i = 1; $i < count($arr); $i += 2)
            $arr[$i] = "";
            
        return FieldImplode($arr);
    }

    function SubstParts($html,$tagopen,$tagclose)
    {
        $arr    = FieldExplode(trim($html),$tagopen,$tagclose);
        
        for ($i = 1; $i < count($arr); $i += 2)
        {
            $field = strtolower(trim($arr[$i]));
            if (array_key_exists($field,$this->fielddata))
                $arr[$i] = $this->fielddata[$field]["value"];
            else
                $arr[$i] = "{\\b !!$field!!}";
        }
            
        return FieldImplode($arr);
    }
    
    function PrepareHtml($html)
    {
        $html = $this->SubstParts($html,'<part>',       '</part>');
        $html = $this->SubstParts($html,'&lt;part&gt;', '&lt;/part&gt;');
        $html = $this->StripTag($html,'<html',                      '>');
        $html = $this->StripTag($html,'</html',                     '>');
        $html = $this->StripTag($html,'<head',                      '/head>');
        $html = $this->StripTag($html,'<body',                      '>');
        $html = $this->StripTag($html,'</body',                     '>');
        $html = $this->StripTag($html,'<div class="spacer">',       '</div>');
        $html = $this->StripTag($html,'<div class="RouteMapList">', '</div>');
        $html = $this->StripTag($html,'<div class="GPXFileList">',  '</div>');
        $html = $this->StripTag($html,'<h1>Trip Report',            '</h1>');
        $html = $this->StripTag($html,'<h2>Trip Report',            '</h2>');
        $html = $this->StripTag($html,'<h3>Trip Report',            '</h3>');
        $html = $this->StripTag($html,'<h4>Trip Report',            '</h4>');
        $html = $this->StripTag($html,'<h4>Trip Photos</h4><table>','</table>');
        $html = $this->StripTag($html,'<p>-- Uploaded by',          '</p>');
        $html = $this->StripTag($html,'{mostripimage ',             '}');
        return $html;
    }
    
    // GenerateOdt is applied to an Open Office (.odt) template file.
    // See file newsletter_odt_generate.php for details
    function GenerateOdt($template)
    {
        require_once("generate_odt.php");
        $engine = new XmlTemplateEngine($this->con);
        return $engine->processOdtTemplate($template);
    }

    // GenerateXml is applied to an XML template file in which there may be many
    // different tags. Code between <part> ... </part> is subject to tag
    // modification. If there is an embedded
    // <source>tablename</source>, the enclosed code is repeated
    // once for each row of the named table, with any self-closing tags whose names match
    // column names are replaced by the values replaced with the values from
    // that row of the table. If there is no specified source, the code is not
    // replicated but instead any embedded tags that are self-terminated and
    // match the names of entries in the fields database table are replaced with
    // the values obtained by executing the associated SQL from the database row.
    function GenerateXml($template)
    {
        $parts      = FieldExplode($template,"<part>","</part>");
        $partslist  = "";

        for ($i = 1; $i < count($parts); $i += 2)
        {
            $part   = $parts[$i];
            $source = strtolower($this->RtfField($part,"source"));
            if ($source == '') {
                $parts[$i] = $this->XmlSubstMultiple($part);
            }
            else if (array_key_exists($source,$this->fielddata)) {
                $partslist = "$part<default></default>$partslist";
                $parts[$i] = $this->XmlSubst($partslist,$source);
            }
            else die("whoops. No source $source");
        }
        return FieldImplode($parts);
    }

    // Replace all the tags within $data that are self-terminating (end with />) and
    // have a corresponding entry in the fields table with the result of executing
    // the corresponding SQL from the field table.
    function XmlSubstMultiple($data)
    {
        $fields = FieldExplode($data,"<",">");

        for ($i = 1; $i < count($fields); $i += 2) {
            $field = $fields[$i];
            // We're only interested in non-CTC tags ending with "/"
            if (strpos($field,"ctc:") !== FALSE || strrpos($field,"/") != strlen($field)-1) {
                $fields[$i] = "<$field>"; // Leave the tag as it was originally
            }
            else if (!array_key_exists($field = trim(substr($field,0, strlen($field)-1)), $this->fielddata)) {
                $fields[$i] = "<ctc:error field=\"$field\"/>";
            }
            else  {
                $sql = $this->fielddata[$field]["sql"];
                $rows  = $this->con->query($sql);

                if (!$rows || mysqli_num_rows($rows) != 1) {
                    $fields[$i] = "<ctc:queryfailed sql=\"$sql\"/>";
                }
                else {
                    $row = mysqli_fetch_row($rows);
                    $fields[$i] = $row[0];
                }
            }
        }
        $xml = FieldImplode($fields);
        return $xml;
    }

    // A modified version of RtfSubst for use with xml files
    function XmlSubst( $template, $source )
    {
        $xml    = "";
        $sql    = $this->fielddata[$source]["sql"];
        $rows   = $this->con->query($sql);

        if (!$rows) {
            die($sql.$this->con->error);
        }

        while ($rowMixedCase = mysqli_fetch_array($rows)) {
            $row = Array();

            foreach ($rowMixedCase as $col => $val) {
                $row[strtolower($col)] = $val;
            }

            $row['source']  = $source;
            $type           = strtolower($row["type"]);
            $rowtemplate    = $this->RtfField( $template, $type );
            if ($rowtemplate == '') {
                $rowtemplate = $this->RtfField( $template, "default");
            }

            $fields = FieldExplode($rowtemplate,"<",">");

            for ($i = 1; $i < count($fields); $i += 2)
            {
                $field = $fields[$i];
                // We're only interested in non-CTC tags ending with "/"
                if (strpos($field,"ctc:") !== FALSE || strrpos($field,"/") != strlen($field)-1) {
                    $fields[$i] = "<$field>";
                }
                else {
                    $field = trim(substr($field,0, strlen($field)-1));
                    if (array_key_exists($field,$row)) {
                        $html = $this->PrepareHtml($row[$field]);
                        $fields[$i] = htmlspecialchars($html); //, ENT_QUOTES, 'ISO-8859-1', false);
                    }
                    else {
                        $fields[$i] = "<ctc:error field=\"$field\"/>";
                    }
                }
            }
            $xml .= FieldImplode($fields);
        }
        return $xml;
    }
    
    // Probably defunct but I'll leave the stub here
    function HtmlToXml($html)
    {
        return str_replace("<p>","<ctc:p>", str_replace("</p>","</ctc:p>", $html));
    }

    
    function RtfFromHtml($row, $col, $template)
    {
        $html = $this->PrepareHtml($row[$col]);
        
        $html = str_replace(" & ",  " &amp; ",  $html);
        $html = str_replace("\\",   "\\\\",     $html);
        $html = str_replace("{",    "\\{",      $html);
        $html = str_replace("}",    "\\}",      $html);
        
        $arr  = FieldExplode($html,"<",">");
        $includehtmlbookmark = false;
       
        for ($i = 1; $i < count($arr); $i += 2)
        {
            $tag     = explode(" ",strtolower($arr[$i]));
            
            if ($tag[0] == "!--" && $tag[1] == "include" && $tag[4] == "--")
            {
                $inc = explode('"',$this->RtfField( $template, $tag[2] ));
                $inc[1] = "\"$tag[3]\"";
                $arr[$i] = FieldImplode($inc);
                $this->debugtext .= "$tag[2]=$inc[1]<br/>\n";
            }
            else
            {
                if (!array_key_exists($tag[0],$this->maptag) || count($tag) > 1)
                    $includehtmlbookmark = true;
            
                $arr[$i] = $this->maptag[$tag[0]];
            }
        }
        
        if ($includehtmlbookmark && $row['source'] != '' && $row['id'] != '')
        {
            $inc = explode('"',$this->RtfField( $template, 'includehtmlbookmark' ));
            $inc[1] = '"' . BASE_URL . "/$_SERVER[PHP_SELF]?source=$row[source]&id=$row[id]&col=$col&bookmark=1\"";
            $this->debugtext .= "includehtml=$inc[1]<br/>\n";
            return FieldImplode($inc);
        }
        
        for ($i = 0; $i < count($arr); $i += 2)
        {
            $chars = FieldExplode($arr[$i],"&",";");
        
            for ($c = 1; $c < count($chars); $c += 2)
                $chars[$c] = $this->mapchar[$chars[$c]];
                    
            $arr[$i] = FieldImplode($chars);
        }
        
        return trim(FieldImplode($arr));
    }

    function RtfField( $rtf, $tag )
    {
        $arr = FieldExplode($rtf,"<$tag>","</$tag>");
        return count($arr) <= 1 ? "": $arr[1];  
    }

    // Substitute the text inside
    function RtfSubst( $template, $source )
    {
        $rtf    = "";
        $sql    = $this->fielddata[$source]["sql"];
        $rows   = $this->con->query($sql);
        
        if (!$rows)
            die($sql.$this->con->error);
            
        $this->debugtext .= "sql=$sql<br/>\n";
        
        while ($rowMixedCase = mysqli_fetch_array($rows))
        {
            $row = Array();
            
            foreach ($rowMixedCase as $col => $val)
                $row[strtolower($col)] = $val;

            $this->debugtext .= "id=$row[id]<br/>\n";
            
            $row['source']  = $source;
            $type           = strtolower($row["type"]);
            $rowtemplate    = $this->RtfField( $template, $type );
            
            if ($rowtemplate == "")
                $rowtemplate = $this->RtfField( $template, "default" );
            
            $fields          = FieldExplode($rowtemplate,"<","/>");
            
            for ($i = 1; $i < count($fields); $i += 2)
            {
                $field = $fields[$i];
                if (array_key_exists($field,$row))
                {
                    $fields[$i] = $this->RtfFromHtml($row, $field, $template);
                    
                    if (!RtfIsValid("{".$fields[$i]."}"))
                        die("RTF invalid id=$row[id] table=$row[table] rtf=$fields[$i]");
                }
                else                
                {
                    $this->debugtext .= "missingfield=$field<br/>\n";
                    $fields[$i] = "{\\b !!$field!!}";
                }
            }
                
            $rtf .= FieldImplode($fields);
        }
        
        return $rtf;
    }

    function RtfToHtml($s)
    {
        $s = str_replace("<","&lt;",$s);
        $s = str_replace(">","&gt;",$s);
        return "<pre>$s</pre>";
    }

    function Generate($template)
    {
        // Substitute the text inside the <part>/</part> tags
        $template   = RtfStripTags($template);
        $parts      = FieldExplode($template,"<part>","</part>");
        $partslist  = "";   

        for ($i = 1; $i < count($parts); $i += 2)
        {
            $part   = $parts[$i];
            $source = strtolower(RtfStrip($this->RtfField($part,"source")));

            if ($source == '')
            {
                $field     = strtolower(RtfStrip($part));
                $parts[$i] = $this->PrepareHtml("<part>$field</part>");
                $this->debugtext .= "field=$field<br/>\n";
            }
            else if (array_key_exists($source,$this->fielddata))
            {
                $partslist = "$part<default></default>$partslist";
                $parts[$i] = $this->RtfSubst($partslist,$source);
                
                if (!RtfIsValid('{'.$parts[$i].'}'))
                    die("RTF invalid source=$source rtf=$parts[$i]");
            }
            else
            {
                $parts[$i] = "{\\b !!$source!!}";
                $this->debugtext .= "Invalid field $source<br/>\n";
            }           
        }

        $parts = FieldExplode(FieldImplode($parts),"INCLUDEPICTURE ","\\\\d");
        for ($i = 1; $i < count($parts); $i += 2)
            $parts[$i] = "INCLUDEPICTURE " . $parts[$i];
            
        SetField($this->con,'debug',addslashes($this->debugtext),'Debug');
        
        return FieldImplode($parts);
    }
}

$generator   = new RtfGenerator($con);
$db          = (!isset($_GET['db'])    || $_GET['db']    == '') ? 'newsletter': $_GET['db'];
$table       = (!isset($_GET['table']) || $_GET['table'] == '') ? 'documents'         : $_GET['table'];
$col         = (!isset($_GET['col'])   || $_GET['col']   == '') ? 'data'              : $_GET['col'];
//$random        = $_GET['random']  == '' ? date('Ymd_His')      : $_GET['random'];
// Changed above to below, RJL 26/1/09
$random      = date('Ymd_His');
$query       = (!isset($_GET['source']) || $_GET['source']  == '') ? $db.'.'.$table   : '('.$generator->fielddata[$_GET['source']]["sql"].') q';
$idcol       = (!isset($_GET['id'])     || $_GET['id']      == '') ? 'name'           : 'id';
$id          = (!isset($_GET['id'])     ? '' : $_GET['id']).
               (!isset($_GET['name'])   ? '' : $_GET['name']).
               (!isset($_GET['expand']) ? '' : $_GET['expand']);
$pos         = strrpos($id,'.');
$ext         = $pos === false         ? 'html'               : str_replace('.','',strtolower(substr($id,$pos)));
$filename    = $pos === false         ? $id                  : substr($id,0,$pos);
$data        = ValueFromSql($con,"SELECT `$col` FROM $query WHERE `$idcol` = '$id'");
$len         = strlen($data);

if ($len == 0)
    die("<h1>$idcol with $id not found in $table</h1>");
    
header("Cache-Control: no-cache");  // RJL 25/3/09. Kill the caching problem at last?!

switch ($ext)
{
case "rtf":
    if ($_GET["expand"] != "")
    {
        $data   = $generator->Generate($data);
        $len    = strlen($data);
    }

    // Output the RTF file
    header("Content-type: application/rtf");
    header("Content-length: $len");
    header("Content-Disposition: inline; filename=$filename.$random.$ext");
    echo $data;                                            
    break;
    
case "doc":
    // Output the DOC file
    header("Content-type: application/msword");
    header("Content-length: $len");
    header("Content-Disposition: inline; filename=$filename.$random.$ext");
    echo $data;                                            
    break;
    
case "htm":
case "html":

    $data   = $_GET["id"]       == "" ? $data : $generator->PrepareHtml($data);
    $data   = $_GET["bookmark"] == "" ? $data : '<a name="bookmark">'.$data.'</a>';
    $len    = strlen($data);
    
    header("Content-type: text/html");
    header("Content-length: $len");
    header("Content-Disposition: inline; filename=$filename.$random.$ext");
    echo $data;                                            
    break;

case "xml":

    if ($_GET["expand"] != "") {
        $data   = cp1252_to_utf8($generator->GenerateXml($data));
        $len    = strlen($data);
    }

    header("Content-type: text/xml; charset=utf-8");
    header("Content-length: $len");
    header("Content-Disposition: attachment; filename=$filename.$random.xml");
    echo $data;
    break;
    
case "odt":
    if ($_GET["expand"] != "")
    {
        $data   = $generator->GenerateOdt($data);
        $len    = strlen($data);
    }

    header("Content-type: application/odt; charset=utf-8");
    header("Content-length: $len");
    header("Content-Disposition: attachment; filename=$filename.odt");
    echo $data;
    break;
            
default:
    // Output the image
    $rtfpagewidth   = floatval($generator->fielddata['rtfpagewidth']['value']);
    $size           = floatval($_GET["size"]);
    $type           = "image/".str_replace("jpg","jpeg",$ext);
    
    header("Content-type: $type");
    header("Content-Disposition: inline; filename=$filename.$random.$ext");
    
    if ($size <= 0 || $rtfpagewidth <= 0)
    {
        header("Content-length: $len");
        echo $data;
    }
    else
    {
        $handle = fopen($id,"w");
        fwrite($handle,$data,strlen($data));
        fclose($handle);
        
        switch ($ext)
        {
        case ".png":    $orig = imagecreatefrompng($id);    break;
        case ".gif":    $orig = imagecreatefromgif($id);    break;
        default:        $orig = imagecreatefromjpeg($id);   break;
        }
        
        $x      = $size * $rtfpagewidth;
        $y      = $x * imagesy($orig) / imagesx($orig);
        $copy   = imagecreatetruecolor($x,$y);
        imagecopyresized($copy,$orig,0,0,0,0,$x,$y,imagesx($orig),imagesy($orig));
        
        imagejpeg($copy);
        imagedestroy($copy);
        imagedestroy($orig);
        unlink($id);
    }
    
    break;
}
?>
