<?php

// Richard's code to process an Open Office (.odt) template document

$DEBUGGING = FALSE;

// Get the value of a given database column ($key) in a context
// consisting of a nested sequence of database table rows, each
// represented as an associative array.
function getFieldValue($key, $context) {
    $value = null;
    for ($i = count($context) - 1; $i >= 0; $i--) {
        $row = $context[$i];
        if (array_key_exists($key, $row)) {
            $value = $row[$key];
            break;
        }
    }
    return $value;
}

function debug($s) {
    global $DEBUGGING;
    if ($DEBUGGING) {
        echo $s;
    }
}

// A callback function for use with the engine's processTextNode method.
// Hacky -- uses a global to pass around the context!
// [My initial implementation used anonymous functions
// but the live website php version doesn't have them yet :-(]
function processField($matches) {
    global $gContext;
    $newText = null;
    $key = $matches[2];
    $newText = getFieldValue($key, $gContext);
    if ($newText === null) {
        debug("*** WARNING: key $key not found ***\n");
        $newText = "";
    }
    return xmlTemplateEngine::clean($newText);
}

class XmlTemplateEngine {

    /**
     * Construct a new xmlTemplateEngine.
     * The first parameter is a database object (mysqli).
     * The optional parameter is a list of table rows to be used
     * if a foreach command is encountered without a query argument.
     */
    function __construct($db, $tableRows = null) {
        $db->set_charset("utf8");
        $this->db = $db;
        $this->level = 0;
        $this->reserved = array("foreach", "if", "endif", "endforeach");
        $this->tableRows = $tableRows;
    }

    // Node cloning function that copies namespace info.
    // Modified from http://nz.php.net/manual/en/domnode.clonenode.php
    function cloneNode($node, $deep = True) {
        // echo "Cloning node named ". $node->nodeName;
        $nd = $this->dom->createElement($node->nodeName);

        foreach ($node->attributes as $value)
            $nd->setAttribute($value->nodeName, $value->value);

        if ($deep) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeName == "#text")
                    $nd->appendChild($this->dom->createTextNode($child->nodeValue));
                else
                    $nd->appendChild($this->cloneNode($child));
            }
        }

        return $nd;
    }

    // Break a string containing <p> ... </p> or <br /> tags (in their HTML-entities form)
    // into a list of non-empty paragraphs.
    // [This means that people who try to space stuff out vertically with multiple line breaks
    // are out of luck. Tough!.]
    function getParagraphs($s) {
        debug("Getting paragraphs of $s\n");
        $pattern = "/(&lt;p.*&gt;)|(&lt;br.*&gt;)/sU";
        // $pattern = "/<p.*><br.*>/Us";
        $bits = preg_split($pattern, $s); // Process <p> and <br> tags
        $paras = array();
        foreach ($bits as $bit) {
            $bit = preg_replace("|&lt;/p.*&gt;|Us", "", $bit); // Strip <p/> tags
            if ($bit != '') {
                $paras[] = $bit;
            }
        }
        // print_r($paras);

        return $paras;
    }

    function indent() {
        $indent = "\n";
        for ($i = 0; $i < $this->level; $i++) {
            $indent .= "    ";
        }
        return $indent;
    }

    function addCtcStyles($autoStyleNode) {
        // Adds special styles to the given automatic style node
        // for use when expanding <strong> and <em> tags
        $styles = array(
            'ctc_bold_7316' => array('fo:font-weight' => 'bold', 'fo:font-weight-asian' => 'bold', 'fo:font-weight-complex' => 'bold'),
            'ctc_italic_7316' => array('fo:font-style' => 'italic', 'fo:font-style-asian' => 'italic', 'fo:font-style-complex' => 'italic')
        );

        foreach ($styles as $name => $props) {
            $style = $this->dom->createElement('style:style');
            $style->setAttribute('style:name', $name);
            $style->setAttribute('style:family', 'text');
            $properties = $this->dom->createElement('style:text-properties');
            foreach ($props as $prop => $value) {
                $properties->setAttribute($prop, $value);
            }
            $style->appendChild($properties);
            $autoStyleNode->appendChild($style);
        }
    }

    // Get the text of the given element node or Null if there is none.
    // The text is obtained by concatenating the values of all child
    // text nodes and (recursively) text:span nodes.
    // RJL: Modified 7/3/11 to return the nodeValue in the case that the given
    // node is a text node.

    function nodeText($node) {
        if ($node->nodeType == XML_TEXT_NODE) {
            return $node->nodeValue;
        }

        $result = '';

        $children = $node->childNodes;
        foreach ($children as $child) {
            if ($child->nodeType == XML_TEXT_NODE) {
                $result .= $child->nodeValue;
            }
            else if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == 'text:span') {
                $childText = $this->nodeText($child);
                if ($childText != null) {
                    $result .= $childText;
                }
            }
        }
        return $result == '' ? null : $result;
    }

    // Called with text from a text node as a parameter.
    // Returns a list of new nodes to replace that original text node, by
    // expanding any embedded HTML styles (<strong>, <em> etc) into
    // text:span nodes.
    function getTextSpans($text, $replacement) {

        $newNodes = array();
        list($pattern, $ctcName) = $replacement;
        $bits = preg_split($pattern, $text, null, PREG_SPLIT_DELIM_CAPTURE);
        $outside = true;
        foreach ($bits as $bit) {
            if ($bit != '' || count($bits) == 1) {  // Discard empty paras unless that's all there is
                if ($outside) {
                    $newNodes[] = $this->dom->createTextNode($bit);
                }
                else {
                    $newNode = $this->dom->createElement('text:span');
                    $newNode->setAttribute('text:style-name', $ctcName);
                    $newNode->appendChild($this->dom->createTextNode($bit));
                    $newNodes[] = $newNode;
                }
            }
            $outside = !$outside;
        }
        return $newNodes;
    }

    // Recursively apply to a given node a given HTML style element, returning a new
    // node after all embedded HTML matching the given style has been
    // processed by embedding new text:span nodes.
    // The replacement rule is given as a 2-element array containing
    // the replacement pattern and the text:span style name to use
    // for the matching text.
    //
    function processStyle($parent, $replacement) {
        if ($parent->hasChildNodes()) {
            $newChildren = array();
            foreach ($parent->childNodes as $child) {
                if ($child->nodeType != XML_TEXT_NODE) {
                    $newChildren[] = $this->processStyle($child, $replacement);
                }
                else {
                    $newNodes = $this->getTextSpans($child->nodeValue, $replacement);
                    foreach ($newNodes as $node) {
                        $newChildren[] = $this->processStyle($node, $replacement);
                    }
                }
            }

            if ($parent->nodeType == XML_TEXT_NODE) {
                die("*** I GOOFED ***");
            }

            while ($parent->hasChildNodes()) {
                $parent->removeChild($parent->firstChild);
            }

            foreach ($newChildren as $child) {
                $parent->appendChild($child);
            }
        }
        return $parent;
    }

    // Called to process HTML styles (currently just <strong>, <b>, <em>
    // and <i>). Action is to replace any XML_TEXT_NODE children with
    // multiple new children which are either XML_TEXT_NODEs or text:span
    // nodes, the latter being used to impart the style dictated by any
    // embedded HTML.
    function processStyles($node) {

        $replacements = array(
            array('|&lt;strong&gt;(.*)&lt;/strong&gt;|Us', 'ctc_bold_7316'),
            array('|&lt;b&gt;(.*)&gt;/b&lt;|Us', 'ctc_bold_7316'),
            array('|&lt;em&gt;(.*)&lt;/em&gt;|Us', 'ctc_italic_7316'),
            array('|&lt;i&gt;(.*)&lt;/i&gt;|Us', 'ctc_italic_7316')
        );

        foreach ($replacements as $replacement) {
            $node = $this->processStyle($node, $replacement);
        }

        return $node;
    }

    // Given a text:p or text:span node, scan through the children attempting
    // to expand any embedded HTML within text children. Strip all but the
    // first expanded paragraph from the node, plus all remaining child nodes,
    // returning all other paragraphs in an array.

    function stripAllButFirstPara($node) {
        // Make a list of all the children. This is necessary because replacing
        // a child breaks the foreach child loop. PHP DOM-handling is broken, I reckon!
        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        $extraParas = array();
        foreach ($children as $child) {
            if (count($extraParas) > 0) {  // If we already have extra paras, just accumulate the rest
                $extraParas = array_merge($extraParas, $this->getParagraphs($this->nodeText($child)));
                $node->removeChild($child);
            }
            else if ($child->nodeType == XML_TEXT_NODE) {
                $paras = $this->getParagraphs($child->nodeValue);
                if (count($paras) > 0) {  // Not sure if we can get zero back, but let's be safe
                    $child->nodeValue = array_shift($paras); // Save only the first para in the node
                    $extraParas = $paras;  // And start accumulating the rest
                }
            }
            else if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == 'text:span') {
                $extraParas = $this->stripAllButFirstPara($child);
            }
        }

        return $extraParas;
    }

    // Called with a text:p node and returns a list of
    // new text:p nodes, one per paragraph, by expanding <p>..</p> tags
    // into multiple text:p nodes.
    // Only operates on text:p nodes with a single text-node child,
    // or text:p nodes with a single text:style child with a single text-node child.
    // [More complex structures, like paragraphs with embedded stylings,
    // don't have any obvious expansion, e.g. consider a paragraph
    // consisting of two fields separated by a tab -- if the fields
    // expand to multiple paragraphs, what happens to the tab, or the
    // stylings of the individual fields?]
    // RJL: Modified 7/3/11 to attempt an expansion of more complex structures.
    // Algorithm preserves the existing children of the given text:p node
    // until a descendent with embedded HTML paragraph breaks is encountered.
    // From the first such break onwards, all new paragraphs are attached to
    // a clone of the original text:p node. This isn't totally correct
    // but then "totally correct" isn't well defined here!

    function expandHTML($node) {
        assert($node->nodeType == XML_ELEMENT_NODE && $node->tagName == 'text:p');
        $replacementNodes = array($node);
        $extraParas = $this->stripAllButFirstPara($node);

        // Make new text:p nodes for all remaining paragraphs
        foreach ($extraParas as $para) {
            $newNode = $this->cloneNode($node, False);
            $newChild = $this->dom->createTextNode($para);
            $newNode->appendChild($newChild);
            $replacementNodes[] = $newNode;
        }

        return $replacementNodes;
    }

    // Process embedded HTML (represented with HTML entities) in text nodes of
    // an almost-ready DOM node. Mostly this is just dealing with <br>, <p> and </p>
    // tags, represented as &lt;p&gt; etc. Returns a new DOM tree using <text:p>
    // and </text:p> in lieu (Open Office specific, alas).
    function filterHTML($node) {
        if ($node->hasChildNodes()) {
            $children = $node->childNodes;
            $copyKids = array();

            foreach ($children as $child) {
                $copyKids[] = $child;
            }
            $newKids = array();
            foreach ($copyKids as $child) {
                $node->removeChild($child);
                if ($child->nodeType == XML_ELEMENT_NODE && $child->tagName == "text:p") {
                    $newKids = array_merge($newKids, $this->expandHTML($child));
                }
                else {
                    $newKids[] = $this->filterHTML($child);
                }
            }
            foreach ($newKids as $child) {
                $node->appendChild($child);
            }
        }

        return $node;
    }

    // Return a pair (2-element array) containing the index to the first node in
    // the given list of nodes that is a text node containing the given string
    // together with the associated argument of the given string (which must be
    // a template command. For example if the 3rd text node is "{{Blah thing}}",
    // where $s is "Blah" the function returns (3, "thing").
    // Backwards is true to search backwards through the list.
    function findNodeContaining($s, $nodeList, $backwards = False) {
        $i = $backwards ? count($nodeList) - 1 : 0;
        $done = False;
        do {
            $node = $nodeList[$i];
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $text = $this->nodeText($node);
                if ($text !== Null && trim($text) != '') {
                    // echo $this->indent() . "TEXT: " . $text;
                    $pattern = "/({{" . $s . " *)([^}]*)(}})/";
                    $matches = array();
                    if (preg_match($pattern, $text, $matches) > 0) {
                        $param = $matches[2];
                        return array($i, $param);
                    }
                }
            }
            $i = $backwards ? $i - 1 : $i + 1;
            $done = $backwards ? $i < 0 : $i >= count($nodeList);
        } while (!$done);
        return null;
    }

    // Process all children of the given node but leaving the node itself
    // unaltered.
    function doChildren($node, $context) {
        /*
          $nodeClone = cloneNode($node);
          foreach ($nodeClone->childNodes as $child) {
          $newChild = processNode($child, $context);
          $nodeClone->replaceChild($newChild, $child);
          }
          return $nodeClone;
         */

        foreach ($node->childNodes as $child) {
            $newChild = $this->processNode($child, $context);
            $node->replaceChild($newChild, $child);
        }
        return $node;
    }

    /**
     * Process all the nodes in the given node list (which is the list
     * of nodes bracketed by if and endif nodes, both of which have been
     * removed) under the given context,
     * only when the given parameter of the if is satisfied.
     * Currently the parameter has to be of the form fieldName = 'literal'
     * or fieldName != 'literal'
     */
    function handleIfBlock($nodeList, $param, $context) {
        if (strpos($param, "!=") !== False) {
            $bits = explode("!=", $param);
            $op = 'notequals';
        }
        else {
            $bits = explode("=", $param);
            $op = 'equals';
        }

        if (count($bits) != 2) {
            die("Illegal parameter to if: $param ");
        }

        $name = trim($bits[0]);
        $value = trim($bits[1]);
        if (!preg_match("|'.*'|", $value)) {
            die("Illegal 'if' literal in $param; should be enclosed in single quotes");
        }

        $value = substr($value, 1, -1);
        $fieldValue = getFieldValue($name, $context);
        if ($fieldValue === null) {
            echo "**** If parameter $param references non-existent field ****/n";
            $process = True;
        }
        else {
            $process = $op == 'equals' ? $fieldValue == $value : $fieldValue != $value;
        }

        if ($process) {
            return $this->processNodeList($nodeList, $context);
        }
        else {
            return array();
        }
    }

    /**
     * Return the index in the given node list to the first terminator
     * for the given control ('foreach' or 'if') allowing for possible
     * nested structures of the same control.
     * $start is the index to the first node to examine.
     * Dies with an error message if no terminator is found -- the value
     * of $param is printed in that error message.
     */
    function findTerminator($control, $nodes, $start, $param) {
        $i = $start;
        $depth = 0;
        while ($i < count($nodes)) {
            $node = $nodes[$i];
            if ($node->nodeType == XML_ELEMENT_NODE) {
                $text = $this->nodeText($node);
                if ($text !== null && trim($text) != '') {
                    $blockStartPattern = "/({{" . $control . " *)([^}]*)(}})/";
                    if (preg_match($blockStartPattern, $text)) {
                        $depth++;
                    }
                    else {
                        $blockEndPattern = "/({{end" . $control . " *)([^}]*)(}})/";
                        if (preg_match($blockEndPattern, $text)) {
                            if ($depth == 0) {
                                return $i;
                            }
                            else {
                                $depth--;
                                if ($depth < 0) {
                                    die("Unmatched endif encountered when looking for terminator for if $param ");
                                }
                            }
                        }
                    }
                }
            }
            $i++;
        }
        die("Can't find terminator for $control $param");
        return null;
    }

    /* Called to handle a list of nodes bracked by foreach/endforeach
      paragraphs (which have been discarded). $forEachQueryResult is
      the mysql query result defining the set of rows over which the
      foreach block must be iterated, or null to use the default set
      of table rows supplied as a parameter to the constructor.
      The returned list of nodes will generally be much larger than
      the original list.
     */

    function handleForEachBlock($nodeList, $forEachQueryResult, $context) {
        $result = array();
        $rows = array();
        if ($forEachQueryResult == null) {
            foreach ($this->tableRows as $row) {
                $rows[] = get_object_vars($row);  // Convert object to associative array
            }
        }
        else {
            $rows = array();
            while ($row = mysqli_fetch_assoc($forEachQueryResult)) {
                $rows[] = $row;
            }
        }

        # Evaluate all nodes in the context of each row
        foreach ($rows as $row) {
            array_push($context, $row);
            $newNodes = array();
            foreach ($nodeList as $node) {
                $newNodes[] = $this->cloneNode($node);
            }
            $newNodes = $this->processNodeList($newNodes, $context);
            array_pop($context);
            $result = array_merge($result, $newNodes);
        }
        return $result;
    }

    // Perform all the necessary transformations on an element node
    // and return the transformed node to insert into the new DOM.
    function processElementNode($node, $context) {
        $children = array();

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $node->removeChild($child);
        }

        $children = $this->processNodeList($children, $context);

        foreach ($children as $child) {
            $node->appendChild($child);
        }

        if ($node->tagName == 'office:automatic-styles') {
            $this->addCtcStyles($node);
        }

        return $node;
    }

    // Process all the nodes in the given nodelist, including
    // handling of (possibly nested) foreach blocks and if blocks.
    // TODO clean up this mess.
    function processNodeList($nodes, $context) {

        if (count($nodes) == 0)
            return array();

        $pair1 = $this->findNodeContaining('foreach', $nodes);
        $pair2 = $this->findNodeContaining('if', $nodes);
        if ($pair1 !== null && ($pair2 === null || $pair2[0] > $pair1[0])) {
            list($startForEach, $param) = $pair1;

            if ($param == '') {
                if ($this->tableRows == null) {
                    die("Missing foreach parameter and no default tableRows");
                }
                $queryResult = null;
            }
            else {

                // Check if foreach parameter starts with 'select'.
                // If not, prefix it with "select *". Otherwise use it as is.
                $bits = explode(" ", $param);
                if (strtolower($bits[0]) != 'select') {
                    $sql = "select * from $param";
                }
                else {
                    $sql = $param;
                }
                $queryResult = $this->db->query($sql);
                if (!$queryResult) {
                    die('Invalid query: ' . $this->db->error);
                }
            }

            $endForEach = $this->findTerminator("foreach", $nodes, $startForEach + 1, $param);
            debug($this->indent() . "Processing foreach  $param ($startForEach, $endForEach)");
            $firstBit = $this->processNodeList(array_slice($nodes, 0, $startForEach), $context);
            $forEachBlock = array_slice($nodes, $startForEach + 1, $endForEach - $startForEach - 1);
            $expandedBlock = $this->handleForEachBlock($forEachBlock, $queryResult, $context);
            $endBit = $this->processNodeList(array_slice($nodes, $endForEach + 1), $context);
            return array_merge($firstBit, $expandedBlock, $endBit);
        }
        else if ($pair2 !== null && ($pair1 === null || $pair1[0] > $pair2[0])) {
            list($startIf, $param) = $pair2;
            $endIf = $this->findTerminator("if", $nodes, $startIf + 1, $param);
            debug($this->indent() . "Processing if  $param ($startIf, $endIf)");
            $firstBit = $this->processNodeList(array_slice($nodes, 0, $startIf), $context);
            $ifBlock = array_slice($nodes, $startIf + 1, $endIf - $startIf - 1);
            $expandedBlock = $this->handleIfBlock($ifBlock, $param, $context);
            $endBit = $this->processNodeList(array_slice($nodes, $endIf + 1), $context);
            return array_merge($firstBit, $expandedBlock, $endBit);
        }
        else {
            $result = array();
            foreach ($nodes as $node) {
                $result[] = $this->processNode($node, $context);
            }
            return $result;
        }
    }

    // Clean up Microsoft's "clever" encodings.
    // From http://php.net/manual/en/function.chr.php
    // SHOULD BE REDUNDANT NOW AS MOST (ALL?) NEWSLETTER CONTENT HAS
    // BEEN PROCESSED INTO HTML SPECIAL CHARS.
    static function fixMicrosoft($str) {
        $str = str_replace(chr(130), ',', $str);    // baseline single quote
        $str = str_replace(chr(131), 'NLG', $str);  // florin
        $str = str_replace(chr(132), '"', $str);    // baseline double quote
        $str = str_replace(chr(133), '...', $str);  // ellipsis
        $str = str_replace(chr(134), '**', $str);   // dagger (a second footnote)
        $str = str_replace(chr(135), '***', $str);  // double dagger (a third footnote)
        $str = str_replace(chr(136), '^', $str);    // circumflex accent
        $str = str_replace(chr(137), 'o/oo', $str); // permile
        $str = str_replace(chr(138), 'Sh', $str);   // S Hacek
        $str = str_replace(chr(139), '<', $str);    // left single guillemet
        $str = str_replace(chr(140), 'OE', $str);   // OE ligature
        $str = str_replace(chr(145), "'", $str);    // left single quote
        $str = str_replace(chr(146), "'", $str);    // right single quote
        $str = str_replace(chr(147), '"', $str);    // left double quote
        $str = str_replace(chr(148), '"', $str);    // right double quote
        $str = str_replace(chr(149), '-', $str);    // bullet
        $str = str_replace(chr(150), '-', $str);    // endash
        $str = str_replace(chr(151), '--', $str);   // emdash
        $str = str_replace(chr(152), '~', $str);    // tilde accent
        $str = str_replace(chr(153), '(TM)', $str); // trademark ligature
        $str = str_replace(chr(154), 'sh', $str);   // s Hacek
        $str = str_replace(chr(155), '>', $str);    // right single guillemet
        $str = str_replace(chr(156), 'oe', $str);   // oe ligature
        $str = str_replace(chr(159), 'Y', $str);    // Y Dieresis
        return $str;
    }

    // Clean a string from the database for insertion into the DOM
    // Various hacks here to inject HTML breaks in lieu of newline chars,
    // to strip out junk that I can deal with (spans, font changes, anchors etc)
    // and to perform various replacements that I seem to be able to get away with
    // when pushing XML into Open Office.
    // Converts everything into html specials first then translates
    // the stuff I know about back to UTF-8.
    static function clean($s) {
        // debug(" Cleaning $s\n");
        // $s = xmlTemplateEngine::fixMicrosoft($s);
        $cleaned = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8", False);
        $s = str_replace(array("\n", "\r"), array(" ", ""), trim($s));

        // Now convert a known set of HTML Entities to UTF-8, but leaving &lt; and &gt;
        // alone, as they're used for structure elements like <p> tags.
        // [Which is why I can't just call html_entity_decode].

        $cleaned = XmlTemplateEngine::htmlEntities2utf8($cleaned);

        $junkToRemove = array(
            "|&lt;!--[if.*&lt;![endif]--&gt;|Us",
            "|&lt;/?span.*&gt;|Us",
            "|&lt;/?font.*&gt;|Us",
            "|&lt;/?a.*&gt;|Us"
        );
        foreach ($junkToRemove as $junk) {
            $cleaned = preg_replace($junk, "", $cleaned);
        }
        return $cleaned;
    }


    static function htmlEntities2utf8($s) {
        // Replace various common html entities with UTF-8, leaving &lt; and &gt;
        // unchanged, as they're used for HTML structure, still to be processed.
        $s = str_replace(array('&lt;', '&gt;'), array('&__lt;', '&__gt;'), $s); // Hack!
        $s = str_replace(array('&#039;', '&#39;'), array('&rsquo;', '&rsquo;'), $s);
        $s = html_entity_decode($s, ENT_COMPAT, 'UTF-8');
        $s = str_replace(array('&__lt;', '&__gt;'), array('&lt;', '&gt;'), $s);
        return $s;
    }

    // Perform all the necessary transformations on a text node
    // and return the transformed node to insert into the new DOM.
    function processTextNode($node, $context) {
        global $gContext;
        $val = $node->nodeValue;
        $gContext = $context;
        $newVal = preg_replace_callback(
                "|({{)([^}]*)(}})|", 'processField', $val
        );
        $node->nodeValue = $newVal;
        assert(!$node->hasChildNodes());
        return $node;
    }

    // Traverse the given DOM node, modifying as necessary
    // to yield a new replacement node.
    function processNode($node, $context) {
        global $docNode;
        // echo $this->indent() . "processNode";
        $this->level++;
        $nt = $node->nodeType;
        if ($nt == XML_ELEMENT_NODE) {
            debug($this->indent() . "Doing element node {$node->tagName}");
            $node = $this->processElementNode($node, $context);
        }
        elseif ($nt == XML_ATTRIBUTE_NODE) {
            throw new Exception("Program error: XML attributes being processed");
        }
        elseif ($nt == XML_TEXT_NODE) {
            debug($this->indent() . "Doing text node");
            $node = $this->processTextNode($node, $context);
        }
        elseif ($nt == XML_DOCUMENT_NODE) {
            debug($this->indent() . "Doing document node");
            $node = $this->doChildren($node, $context);
        }
        elseif ($nt == XML_DOCUMENT_TYPE_NODE) {
            debug($this->indent() . "Doing document type node");
            $node = $this->doChildren($node, $context);
        }
        else {
            throw new Exception("Unexpected XML Node type (%d)" . $nt);
        }
        $this->level--;
        return $node;
    }

    function expandTemplate($template) {
        $this->dom = new DOMDocument();
        $this->dom->loadXML($template);
        $newDom = $this->processNode($this->dom, array());
        $newDom = $this->filterHTML($newDom);
        $newDom = $this->processStyles($newDom);
        $xml = $newDom->saveXML();
        return $xml;
    }

    function processOdtTemplate($template) {
        $zipPath = sys_get_temp_dir ()."/zipFile.odt";
        $zipFile = fopen($zipPath, "w");
        fwrite($zipFile, $template, strlen($template));
        fclose($zipFile);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception("Template isn't a .odt file");
        }

        $data = $zip->getFromName('content.xml');
        if ($data == '') {
            throw new Exception("Template doesn't contain content.xml");
        }

        $expanded = $this->expandTemplate($data);
        $zip->deleteName('content.xml');
        $zip->addFromString('content.xml', $expanded);
        $zip->close();

        $newData = file_get_contents($zipPath);

        return $newData;
    }

}

// "main body" is just debugging
if ($DEBUGGING) {
    $db = mysqli_connect("localhost", 'ctcweb9_ctcadmin', 'murgatr0ad');
    $db || die('Could not connect to database');
    $db->set_charset("utf8");
    $db->select_db('ctcweb9_newsletter') || die('Could not open database');
    $filename = "newsletterTemplate.odt";
    $template = file_get_contents($filename);
    $engine = new XmlTemplateEngine($db);
    $result = $engine->processOdtTemplate($template);
    $outFile = fopen('result.odt', 'w');
    fwrite($outFile, $result);
    fclose($outFile);
}
?>
