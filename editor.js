var undo    = new Transaction('Undo');
var redo    = new Transaction('Redo');
var dragdrop = null;
var days    = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];                         
var months  = ['January','February','March','April','May','June',
                'July','August','September','October','November','December'];
var seledit  = '(enter something not in this list)';
var selall   = '(show all the options)';

// Loads the html document
function Load()
{
    root 					= new DataTable(root, null, root.rows);

    Actions( 'root.LoadColumns()',
             'root.LoadRows(root.sourcerows)',
             'root.LoadPreferences()',
             'root.SortRows()',
             'root.DisplayRows(root)',
			 'root.UpdateCalendar()',
             'root.UpdateRows(root,0)');
}

// Submits the data to the database
function Save(nextpage,generatefile)
{
    if (generatefile != null)
        generatefile = "generate.php?random=" + DateTimeToRandom() + "&expand=" + generatefile;

    if (document.newsletterform == null)
    {
        window.location.replace(nextpage || generatefile);
    }
    else
    {
        if (nextpage != null)
            document.newsletterform.action = nextpage;
            
        Actions('root.PostBuild("'+(generatefile||'')+'")',
                'root.PostAssign()',
                'document.newsletterform.submit()');
    }
}

// Updates all cells
function Update()
{
    Actions('root.UpdateAllRows()',
            'root.DisplayRows(root)',
            'root.UpdateRows(root,0)');
}

// Undoes the last action
function Undo()
{
    undo.Rollback(redo);
}

// Redoes the last action
function Redo()
{
    if (redo.action.length)
    {
        redo.Rollback(undo);
    }
    else if (redo.lastedit == null)
    {
        Message('Nothing to redo');
    }
    else
    {
        undo.New(redo.Last().name);
        root.SetSelected(redo.lastedit.col, redo.lastedit.value);
            
        Message('Redid edit to ' + redo.lastedit.col);
    }
}

function Delete()
{
    root.deleted = null;
    root.DeleteSelected();
    root.DisplayRows(root);
    root.UpdateRows(root,0);
}

function ShowCalendar()
{
	if ($("calendar"))
	{
		root.showcalendar = !root.showcalendar;
		root.UpdateCalendar();
	}
	else
	{
		Message("Calendar is not available here");
	}
}

function Message(message)
{
	$('status').innerHTML = message;
	setTimeout("root.UpdateStatus()",3000);
}

function GenerateFile(generatefile)
{
    if (generatefile != '')
        window.location.replace(generatefile);
}

function MouseDown(e)
{
    e 			= e || window.event;
    var obj		= e.target || e.srcElement;
    var row		= ObjectFind(obj,'datarow');
    
    if ($('dragdrop') == null || row == null || !row.IsOrderable() || obj.type != null)
        return;

    var tr 		= $('tr'+row.id);
    var table	= new Table({'class':'dragdroptable'});
    
    for (var x = 0; x < tr.childNodes.length; x++)
    {
        table.Cell(x,0).props.width = tr.childNodes[x].offsetWidth;
        table.Cell(x,0).html 		= tr.childNodes[x].innerHTML;
    }
    
    dragdrop 					= new Rectangle(tr,0,-e.clientY);
    dragdrop.row				= row;
    dragdrop.node				= ObjectFind(tr,'datanode');
    dragdrop.cursor 			= $('dragdrop');
    dragdrop.cursor.innerHTML	= table.Html();
}

function MouseMove(e)
{
    if (dragdrop == null)
        return;

    e			= e || window.event;
    var rows	= dragdrop.node.Rows();
    var	x		= e.pageX || e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
    var	y		= e.pageY || e.clientY + document.body.scrollTop  + document.documentElement.scrollTop;

    dragdrop.hit = null;
    for (var i in rows)
    {
        var pos = new Rectangle($('tr'+rows[i].id));
        
        if (dragdrop.hit == null && rows[i].id != dragdrop.row.id && rows[i].IsOrderable() && pos.Contains(x,y))
            dragdrop.hit = rows[i];
            
        if (rows[i].id == dragdrop.row.id)
            rows[i].Update('rowdragsource');
        else if (dragdrop.hit == null || rows[i].id != dragdrop.hit.id)
            rows[i].Update();
        else if (dragdrop.hit.sortorder < dragdrop.row.sortorder)
            rows[i].Update('rowdropabove');
        else
            rows[i].Update('rowdropbelow');
    }
        
    dragdrop.cursor.style.position		= 'absolute';
    dragdrop.cursor.style.left 			= (dragdrop.x) + 'px';
    dragdrop.cursor.style.top			= (dragdrop.y + y) + 'px';
    dragdrop.cursor.style.width			= dragdrop.sx + 'px';
    dragdrop.cursor.style.height		= dragdrop.sy + 'px';
    dragdrop.cursor.style.visibility	= 'visible';	
}

function MouseUp(e)
{
    if (dragdrop == null)
        return;
        
    dragdrop.cursor.style.visibility	= 'hidden';	
    document.onselectstart 				= null;
        
    if (dragdrop.hit)
    {
        if (dragdrop.hit.sortorder < dragdrop.row.sortorder)
            dragdrop.row.Move('Drag Down',	dragdrop.hit.sortorder - dragdrop.row.sortorder - 1);
        else
            dragdrop.row.Move('Drag Up', 	dragdrop.hit.sortorder - dragdrop.row.sortorder + 1);
    }
    else
    {
        dragdrop.row.Update();
    }

    dragdrop 							= null;
}


// Sequentially executes each argument, 
// updating the status area before each argument
function Actions(action)
{
    $('status').innerHTML = action;
    
    if (arguments.length > 1)
    {
        action += ';Actions(';
        for (var i = 1, sep = ''; i < arguments.length; i++, sep = ',')
            action += sep + '"' + arguments[i] + '"';
        action += ')';            
    }            
        
    setTimeout(action);
}

// Constructor for the Transaction object
function Transaction(title)
{
    this.index      = 0;
    this.action     = [];
    this.title      = title;
}

// Creates a new transaction
Transaction.prototype.New = Transaction_New;
function Transaction_New(name)
{
    this.index++;
    this.name = name;
}

// Creates a new log entry
Transaction.prototype.Set = Transaction_Set;
function Transaction_Set(row,col,value,clearredo)
{
    var old = col == 'deleted' ? row.deleted : row.data[col];
    
    if (old == value)
        return false;
        
    if (clearredo == null || clearredo)
        redo.action.length = 0;

    this.action[this.action.length] = {index:	this.index,
                                        name:  	this.name,
                                        row:    row,
                                        col:   	col,
                                        value: 	old};

    if (col == 'deleted')                                      
        row.deleted = value
    else        
        row.data[col] = value;    
        
    return true;                             
}

// Rolls back the last transaction
Transaction.prototype.Rollback = Transaction_Rollback;
function Transaction_Rollback(opposite)
{
    if (this.action.length == 0)
        return;
        
    var action  = this.Last();
    
    opposite.New(action.name);
    
    while (this.action.length && this.Last().index == action.index)
    {
        opposite.Set(this.Last().row,
                     this.Last().col,
                     this.Last().value,false);
        this.action.length--;
    }
    
    if (action.name.toLowerCase().indexOf('move') == 0)
        action.row.datatable.SortRows('order');

    Actions("root.SortRows()",
            "root.UpdateRows(root,0)");
}

// Returns the last log entry
Transaction.prototype.Last = Transaction_Last;
function Transaction_Last()
{
    if (this.action.length > 0)
        return this.action[this.action.length-1];
    else if (this.lastedit == null)
        return {name:'Nothing'};
    else
        return {name:'Edit to ' + this.lastedit.col + ' on selected rows'};
}

// Constructor for the Row object
function Row(datatable,data,newid)
{
    this.datatable	= datatable;
    this.id			= datatable.id + '_' +	data[datatable.keycolumn] + '_';
    this.key		= 						data[datatable.keycolumn];
    this.newid  	= newid;
    this.data      	= ObjectCopy(data,{});
    this.orig      	= ObjectCopy(data,{});
    this.edited    	= false;
    this.deleted   	= false;
    this.selected 	= false;
    this.open      	= Boolean(datatable.rowopen);
    this.found     	= data.found == null ? 0 : Number(data.found);
    this.size		= {sizes:{}, remeasure:true, resize:true};
    this.nodes		= [];
        
    for (var i in datatable.children)
        this.nodes[i] = new Node(datatable.children[i],this,this.id + datatable.children[i].id);
}

Row.prototype.IsOrderable = Row_IsOrderable;
function Row_IsOrderable()
{
    return	this.newid == 0 						&& 
            !this.deleted							&&
            !this.datatable.ro						&&
            this.datatable.sortdirection == 1 		&&
            this.datatable.cols.order				&& 
            this.datatable.cols.order.sortorder == 1	;
}

// Generates HTML for the row
Row.prototype.Html = Row_Html;
function Row_Html()
{
    if (!this.open)
        return '';

    this.size.resize = true;
    
    var table        = new Table({width:"100%"});
    var collast      = '';
    var colspan      = [0,0,{},0,0,{},0];
    var widths       = [1,1,45,1,1,45,1];
    var movecell   	 = table.Cell(0,0).control;
    
    if (this.IsOrderable())
    {
        movecell.id      = 'move' + this.id;
        movecell.value   = "Move >";
        movecell.onclick = "ObjectFind(this,'datarow').MoveMenu(this,'Move Up')";
    }
    
    var xx = 2, yy = 0, yymax = 0, yycolumn = 0;
    
    // Work out where each field is going to go, and how much space it will take up
    for (var col in this.datatable.cols)
    {
        var column = this.datatable.cols[col];
        
        if (!column.Show(this.data))
            continue;

        if (column.layoutcolumn != null)
        {
            if (column.layoutcolumn == 0)
                yycolumn = yymax;
            
            yy = yycolumn;
            xx = 2 + 3 * column.layoutcolumn;
        }

        colspan[2][yy]  = 2;
        colspan[xx][yy] = colspan.length - xx;
        column.xx       = xx;
        column.yy       = yy++;
        collast         = col;
        yymax			= Math.max(yymax,yy);
    }
        
    // Make the HTML table
    for (var col in this.datatable.cols)
    {
        var column = this.datatable.cols[col];

        if (!column.Show(this.data))
            continue;
            
        var xx      = column.xx;            
        var yy      = column.yy;
        var head    = table.Cell(xx-1,yy);
        var data    = table.Cell(xx,yy);
        var width	= 0;
            
        head.html           = '<label for="edit'+this.id+col+'">' + col + '</label>';
        data.props.id		= 'cell'+this.id+col;
        data.props.title	= column.comment;
        data.props.colspan	= colspan[xx][yy];
        
        if (column.height != null)
            data.props.height = column.height;

        if (column.ro || this.deleted)
            data.html		= "";
        else
            data.html		= column.Html(this,"");
        
        if (column.typename == 'date' && !this.deleted)
        {
            data.props.colspan--;
            
            var menu    = table.Cell(xx+data.props.colspan,yy).control;            
        
            menu.onclick   		= "ObjectFind(this,'datarow').DateMenu('"+col+"')";
            menu.value     		= '>';
            menu.title     		= 'Select a date from the calendar';
            data.props.width	= widths[xx+data.props.colspan] + '%';
        }
        
        for (var i = 0; i < data.props.colspan; i++)
            width += widths[xx+i];
            
        head.props.width	= widths[xx-1]  + '%';
        data.props.width	= width			+ '%';
    }
    
    var html = table.Html();
    
    for (var i in this.nodes)
    {
        var node = this.nodes[i];
        html += "<div id='node_"+node.id+"' class=childtable>" + node.datatable.Html(node,node.Rows()) + "</div>";
    }
    
    return html;
}

Row.prototype.Selected = Row_Selected;
function Row_Selected()
{
    this.selected = $("sel"+this.id).checked;
    this.Update();
}

// Opens or closes the row
Row.prototype.Open = Row_Open;
function Row_Open()
{
    this.open = !this.open;
    $('menu').innerHTML   		= "";
    $('row'+this.id).innerHTML	= this.Html();
    $('exp'+this.id).value    	= this.open ? '-' : '+';
    this.Update();
}

// Displays the movement menu
Row.prototype.MoveMenu = Row_MoveMenu;
function Row_MoveMenu(obj,action)
{
    var table	= new MenuTable(obj, null, '');
    
    this.datatable.UpdateStatus(false);
    
    table.Cell(1,0).control = {value: "Move Up",     id: "Move Up",     onclick: "ObjectFind(this,'datarow').Move('Move Up',-3)"};
    table.Cell(2,0).control = {value: "Move Down",   id: "Move Down",   onclick: "ObjectFind(this,'datarow').Move('Move Down',3)"};
    table.Cell(3,0).control = {value: "Move Top", 	  id: "Move Top",    onclick: "ObjectFind(this,'datarow').Move('Move Top',-10000)"};
    table.Cell(4,0).control = {value: "Move Bottom", id: "Move Bottom", onclick: "ObjectFind(this,'datarow').Move('Move Bottom',10000)"};
    table.Show().datarow = this;
    $(action).focus();
}

// Moves the row in the desired direction
Row.prototype.Move = Row_Move;
function Row_Move(action,distance)
{
    $('menu').innerHTML = '';
    
    var node = ObjectFind($('row' + this.id),'datanode');
    var rows = node.Rows();
    var y;
    var order = [];
    var sort = [];
    
    for (var i in rows)
    {
        if (rows[i].data.order >= 0)
        {
            sort[i] = rows[i];
            order[i] = rows[i].data.order;
        }
    }
        
    this.sortorder += distance;
    sort.sort(function(a,b) {return a.sortorder - b.sortorder;});
    
    undo.New(action);
    for (var i in sort)
        undo.Set(sort[i],'order',order[i]);
    
    this.datatable.SortRows();
    this.datatable.DisplayRows(node);
    this.datatable.UpdateRows(root,0);
    
    if (action.indexOf('Move') == 0)
        this.MoveMenu($('move' + this.id),action);
}

// Returns a string reflecting the current state of what should be shown and how
Row.prototype.Show = Row_Show;
function Row_Show()
{
    var show = "";
    
    for (var col in this.datatable.cols)
        show += this.datatable.cols[col].Show(this.data) + 
                this.datatable.cols[col].Wysiwyg(this.data) ;
        
    return show;
}


// Makes values for the row
Row.prototype.Make = Row_Make;
function Row_Make()
{
    for (var col in this.datatable.cols)
    {
        var column = this.datatable.cols[col]; 
    
        if (column.Make != null && column.Show(this.data))
            undo.Set(this,col,column.Make(this.data,this));
    }
}

// Updates the state of the row
Row.prototype.Update = Row_Update;
function Row_Update(droptarget)
{
    $('tr'	+ this.id).datarow = this;
    $('row'	+ this.id).datarow = this;
    
    if (this.open)
    {
        for (var i in this.nodes)
        {
            $("node_"+this.nodes[i].id).datanode  = this.nodes[i];
            $("node_"+this.nodes[i].id).datatable = this.nodes[i].datatable;
        }
    }
            
    this.changed = false;

    if (this.size.remeasure && this.open)
        this.size.sizes = {};
    
    for (var col in this.datatable.cols)
    {
        if (this.data[col] != this.orig[col])
            this.changed = true;
            
        var column = this.datatable.cols[col];
        var cell = this.open   ? $('cell'+this.id+col) : null;
        var edit = this.open   ? $('edit'+this.id+col) : null;
        var head = column.head ? $('head'+this.id+col) : null;
            
        if (cell != null)
        {
            cell.className = this.data[col] == this.orig[col] ? 'rownormal' : 'rowchanged';
            if (column.ro || this.deleted)
                cell.innerHTML = column.ReadOnlyHtml(this);
        }

        if (edit != null && this.size.remeasure)
            this.size.sizes[edit.id]  = {x: cell.offsetWidth, y: cell.offsetHeight};

        if (edit != null)
            column.Update(this,edit);
        
        if (head != null)
            head.innerHTML    = column.HeaderHtml(this);
    }
    
    if (this.open)
    {
        this.Resize();
        for (var i in this.nodes)
            this.nodes[i].datatable.UpdateRows(this.nodes[i],0);
    }
    
    var tr      = $('tr'+this.id);
    
    if 		(droptarget)					tr.className = droptarget;
    else if (this.deleted)                  tr.className = 'rowdeleted';
    else if (this.newid && this.changed)	tr.className = 'rownew';
    else if (this.newid)                	tr.className = 'rowblank';
    else if (this.changed)                  tr.className = 'rowchanged';
    else if (this.found > 0)                tr.className = 'rowfound';
    else                                    tr.className = 'rownormal';  
    
    if (!this.open)
        $('row'+this.id).style.height = 0;
        
    tr.style.color = this.selected ? "darkred" : "";        
    $('sel'+this.id).checked = this.selected;        
}            

Row.prototype.Resize = Row_Resize;
function Row_Resize()
{
    if (this.size.resize)
    {
        for (var id in this.size.sizes)
        {
            $(id).style.width   = this.size.sizes[id].x;
            $(id).style.height  = this.size.sizes[id].y;
        }
    }
    
    this.size.resize	= false;
    this.size.remeasure	= false;
}

// Updates a row - taking data from the form
Row.prototype.UserUpdate = Row_UserUpdate;
function Row_UserUpdate(col,value)
{
    var column 	= this.datatable.cols[col];
    
    if (value == seledit || value == selall)
    {
        this.size.resize = true;
        column.Update(this,$('edit'+this.id+column.field),value);
        return;
    }

    undo.New("Edit " + col);
    
    var rowshow = this.Show();
    var changed = undo.Set(this,col,value);
            
    this.Update();
    
    if (changed)
        redo.lastedit = {col:col, value:value};
    
    if (this.changed || changed)
    {
        this.Make();

        if (rowshow != this.Show())
        {
            this.size.remeasure = true;
            $('row'+this.id).innerHTML  = this.Html();
        }

        this.Update();
    }
    
    root.UpdateStatus();
}

// Implements the date menu for the cell
Row.prototype.DateMenu = Row_DateMenu;
function Row_DateMenu(col,filter)
{
    var table    = new MenuTable($('edit'+this.id+col), filter);
    var dCurrent = new Date(DateFromString(this.data[col]));
    var dMonth   = new Date(dCurrent);
    var hide     = 0;
    
    if (filter != null)
        dMonth = DateFromString(filter);
        
    dMonth.setDate(1);
    
    var dPrev = new Date(dMonth); 
    var dNext = new Date(dMonth); 
    
    dPrev.setDate(0);
    dNext.setDate(32);
    table.Cell(1,0).control.value   = '<';
    table.Cell(1,0).control.onclick = "ObjectFind(this,'datarow').DateMenu('"+col+"','"+DateToString(dPrev)+"')";
    table.Cell(1,0).control.title   = months[dPrev.getMonth()] + ' ' + dPrev.getFullYear();
    table.Cell(2,0).html            = months[dMonth.getMonth()] + ' ' + dMonth.getFullYear();
    table.Cell(2,0).props.colspan	= 4;
    table.Cell(6,0).control.value   = '>';
    table.Cell(6,0).control.onclick = "ObjectFind(this,'datarow').DateMenu('"+col+"','"+DateToString(dNext)+"')";
    table.Cell(6,0).control.title   = months[dNext.getMonth()] + ' ' + dNext.getFullYear();
    
    for (var xx = 0; xx < 7; xx++)
    {
        table.Cell(xx,1).props.width=30;
        table.Cell(xx,1).html 		= days[xx].substr(0,3);
    }
    
    for (var dDay = dMonth, yy = 2; dDay.getMonth() == dMonth.getMonth(); dDay = DateAdd(dDay,0,0,1))
    {
        var s    = DateToString(dDay);
        var cell = table.Cell(dDay.getDay(),yy);
        
        cell.control.onclick  	= "ObjectFind(this,'datarow').UserUpdate('"+col+"','"+s+"')";
        cell.control.value    	= dDay.getDate();
        cell.control.title    	= 'Select ' + days[dMonth.getDay()] + ', ' + 
                                            dDay.getDate() + ' ' + 
                                            months[dMonth.getMonth()] + ' ' + 
                                            dDay.getFullYear();
        cell.props.align		= 'right';
        cell.control.Class  	= 'date';
        
        if (dCurrent == dDay)
            cell.control.id = 'selected'
			
        yy += dDay.getDay() == 6 ? 1 : 0;
    }
    
    table.Show(25,0).datarow = this;
}

function Column(objectdata,datatable)
{
    ObjectCopy(objectdata,this);
    this.datatable		= datatable;
    this.field			= this.field.toLowerCase();
    this.typename		= this.type.split("(")[0];
    this.ro           	= Boolean(this.ro) || datatable.ro || this.key == 'PRI';
    this.head         	= Boolean(this.head);
    this.height         = 10;
    this.defaultvalue	= this['default'];

    this.Show         	= AlwaysTrue;
    this.Wysiwyg		= AlwaysFalse;
    this.ReadOnlyHtml	= Column.prototype.Html
    this.HeaderHtml		= Column.prototype.Html;
    this.Html			= Column.prototype.HtmlInput;
    this.Update			= Column.prototype.UpdateInput;
    
    if (this.sortorder == null)		this.sortorder	= this.key == 'PRI' ? 1 : 2;
    if (this.key == 'PRI')			this.Show		= AlwaysFalse;
    if (this.typename == 'text') 	this.height		= 200;
    if (this.typename == 'text') 	this.Html		= Column.prototype.HtmlTextArea;
    if (this.typename == 'text') 	this.Update		= Column.prototype.UpdateTextArea;
    if (this.typename == 'tinyint') this.Html		= Column.prototype.HtmlCheckbox;
    if (this.typename == 'tinyint') this.Update		= Column.prototype.UpdateCheckbox;
    if (datatable.source != null)	this.source		= datatable.source[this.field];
    if (this.source != null)		this.Html		= Column.prototype.HtmlSelect;
    if (this.source != null)		this.Update		= Column.prototype.UpdateSelect;
    
    if (datatable.defaults != null && datatable.defaults[this.field] != null)
        this.defaultvalue = datatable.defaults[this.field];
    
    ObjectCopy(datatable.custom.Default,	this);
    ObjectCopy(datatable.custom[this.field],this);
}

Column.prototype.Class = Column_Class;
function Column_Class(row)
{
    return this.sortorder > 1 ? 'col' : (this.datatable.sortdirection > 0 ? 'colsorted' : 'colsorteddescending');
}

Column.prototype.Html = Column_Html;
function Column_Html(row)
{
    return row.data[this.field];
}

// create the html for a checkbox
Column.prototype.HtmlCheckbox = Column_HtmlCheckbox;
function Column_HtmlCheckbox(row)
{
    var obj = {id: 'edit'+row.id+this.field, title: this.comment, type: 'checkbox', 
                onclick: "ObjectFind(this,'datarow').UserUpdate('"+this.field+"',this.checked ? '1' : '0')"};
    
    return TableProps('input', obj, '');
}

// Create the HTML for a drop down list
Column.prototype.HtmlSelect = Column_HtmlSelect;
function Column_HtmlSelect(row,value)
{
    if (value == seledit)
        return this.HtmlInput(row);

    var obj 		= {id: 'edit'+row.id+this.field, title: this.comment, 
                        onchange: "ObjectFind(this,'datarow').UserUpdate('"+this.field+"',this.value)"};
        
    return TableProps('select', obj, '');
}

// Create the html for the text area
Column.prototype.HtmlTextArea = Column_HtmlTextArea;
function Column_HtmlTextArea(row)
{
    var obj = {id: 'edit'+row.id+this.field, title: this.comment, rows: this.rows, 
                onblur: "ObjectFind(this,'datarow').UserUpdate('"+this.field+"',this.value)"};
    
    return TableProps('textarea', obj, ' ');
}

// Create the html for the default text box
Column.prototype.HtmlInput = Column_HtmlInput;
function Column_HtmlInput(row)
{
    var obj = {id: 'edit'+row.id+this.field, title: this.comment, type: 'text', 
                onblur: "ObjectFind(this,'datarow').UserUpdate('"+this.field+"',this.value)"};
    
    return TableProps('input',obj,'');
}

Column.prototype.UpdateInput = Column_UpdateInput;
function Column_UpdateInput(row,edit)
{
    edit.value = row.data[this.field];
}

Column.prototype.UpdateTextArea = Column_UpdateTextArea;
function Column_UpdateTextArea(row,edit)
{
    edit.value = row.data[this.field];

    if (!this.Wysiwyg(row.data))
        return;

    root.LoadWysiwyg();
    edit.datacol		= this.field;
    edit.style.width   	= row.size.sizes[edit.id].x;
    edit.style.height	= row.size.sizes[edit.id].y;
    tinyMCE.execCommand("mceAddControl", true, edit.id);
}

Column.prototype.UpdateCheckbox = Column_UpdateCheckbox;
function Column_UpdateCheckbox(row,edit)
{
    edit.checked  = Number(row.data[this.field]);
}

Column.prototype.UpdateSelect = Column_UpdateSelect;
function Column_UpdateSelect(row,edit,value)
{
    if (value == seledit)
    {
        $('cell'+row.id+this.field).innerHTML = this.HtmlInput(row);
        edit = $('edit'+row.id+this.field);
        this.UpdateInput(row,edit);
        row.Resize();
        return;
    }

    if (this.source.data == null && this.source.request == null)
        this.source.Request();
        
    if (this.source.data == null)
        return;
    
    var missed 		= false;
    var rowvalue 	= row.data[this.field];
    var values		= {};

    for (var i in this.source.data)
    {
        var item = this.source.data[i];
        
        if (Number(item.common) || value == selall || item.id == rowvalue)
            values[item.id]	= {title:item.title,	html:item.name};
        else
            missed = true;
    }
    
    if (values[rowvalue] == null)
        values[rowvalue]	= {title:'',			html:rowvalue};

    if (values[''] == null)
        values[''] 			= {title:'Set blank',	html:''};
    
    if (missed)
        values[selall]		= {title:'',			html:selall};
    
    values[seledit]			= {title:'',			html:seledit};
    
    edit.options.length = 0;
    for (var i in values)
    {
        var option 							= new Option(values[i].html, i);
        option.title 						= values[i].title;
        edit.options[edit.options.length]	= option;
    }
    
    edit.value = rowvalue;
}

function Source(objectdata,datatable)
{
    this.datatable = datatable;
    ObjectCopy(objectdata,this);
    this.Update();
}

Source.prototype.Update = Source_Update;
function Source_Update()
{
    if (this.data == null)
        return;

    for (var i in this.data)
    {
        if (this.data[i].common == null) this.data[i].common = true;
        if (this.data[i].id     == null) this.data[i].id     = this.data[i].name;
        if (this.data[i].title  == null) this.data[i].title  = this.data[i].name;
    }
}

Source.prototype.Request = Source_Request;
function Source_Request(synchronous)
{
    var source = this;
    
    this.request = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("MSXML2.XMLHTTP.3.0");
    this.request.open( "GET", "json.php?object=" + this.query + "&random=" + DateTimeToRandom(), !Boolean(synchronous) );
    this.request.onreadystatechange = function() {source.StateChange(); };
    this.request.send(null);
    
    if (Boolean(synchronous))
        this.StateChange();
}

Source.prototype.Data = Source_Data;
function Source_Data()
{
    if (this.data == null)
        this.Request(true);
    
    return this.data;
}

Source.prototype.StateChange = Source_StateChange;
function Source_StateChange()
{
    if ( this.request == null || this.request.readyState != 4)
        return;
        
    try
    {
        this.data = eval( "(" + this.request.responseText + ")" );
        this.request = null;
    }
    catch(e)
    {
        alert(this.request.responseText);
        return;
    }
    
    this.Update();
    
    for (var col in this.datatable.cols)
    {
        var column = this.datatable.cols[col];
        
        if (column.source != null && column.source.query == this.query)
        {
            column.source.data = this.data;
                
            for (var y in this.datatable.rows)
            {
                var row = this.datatable.rows[y];
                var edit = row.open ? $('edit'+row.id+column.field) : null;
                
                if (edit != null)
                    column.Update(row,edit);
            }
        }
    }
}

function DataTable(objectdata,parent,sourcerows)
{
    ObjectCopy(objectdata,this);
    this.parent				= parent;
    this.children			= this.children == null ? [] : this.children;
    this.rows 				= [];
	this.calendarrows 		= [];
    this.sourcerows			= sourcerows;
    this.newid				= 10000;
    this.id					= this.table;
    this.group 				= 0;
    this.ro					= Boolean(this.ro);
    this.sortdirection		= 1;
    this.source				= this.source == null ? {} : this.source;
    
    for (var i in this.source)
        this.source[i] = new Source(this.source[i],this);

    for (var i in this.children)
        this.children[i] = new DataTable(this.children[i],this,null);
}

// Loads the columns
DataTable.prototype.LoadColumns = DataTable_LoadColumns;
function DataTable_LoadColumns()
{
    for (var col in this.cols)
    {
        var column  = this.cols[col] = new Column(this.cols[col],this);
        
        if (this.keycolumn == null || column.key == 'PRI')
            this.keycolumn = col;
        
        this.group = column.group;
    }
    
    for (var i in this.children)
        this.children[i].LoadColumns();
}

// loads the rows
DataTable.prototype.LoadRows = DataTable_LoadRows;
function DataTable_LoadRows(sourcerows, parentrow)
{
    for (var y in sourcerows)
        this.rows[this.rows.length]	= new Row(this,sourcerows[y],0);
         
    if (this.ro)
        return;
    
    var newsource	= {found:0};
    
    for (col in this.cols)
        newsource[col] = this.cols[col].defaultvalue;
        
    if (parentrow != null)
        newsource[this.linkcolumn] = parentrow.data[this.linkcolumn];

    for (var i = 0; i < 10; i++)
    {
        var id = this.newid++;
        newsource[this.keycolumn] = String(id);
        this.rows[this.rows.length]	= new Row(this,newsource,id);
    }
}        

// Loads the state
DataTable.prototype.LoadPreferences = DataTable_LoadPreferences;
function DataTable_LoadPreferences()
{    
    var retries = '';
     
    for (var i in this.prefs)
    {
        var lines = this.prefs[i].value.split('\n');
        
        for (var j in lines)
        {
            try
            {
                eval(lines[j]);
            }
            catch (e)
            {
                retries += lines[j] + '\n';
            }
        }
    }
    
    this.prefs = [{value:retries}];
}

DataTable.prototype.LoadWysiwyg = DataTable_LoadWysiwyg;
function DataTable_LoadWysiwyg()
{
    if (this.wysiwygloaded)
        return;

    this.wysiwygloaded = true;
    tinyMCE.init({
        mode : "none",
        theme : "advanced",
        plugins : "table,contextmenu",
        theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,tablecontrols,bullist,numlist,|,indent,outdent,|,link,unlink,|,formatselect,fontselect,fontsizeselect",
        theme_advanced_buttons2 : "",
        theme_advanced_buttons3 : "",
        theme_advanced_toolbar_location : "top",
        theme_advanced_toolbar_align : "left",
        theme_advanced_statusbar_location : "none",
        force_br_newlines : true,
        forced_root_block : '', // Needed for 3.x
        setup : WysiwygSetup
       });
}

DataTable.prototype.Table = DataTable_Table;
function DataTable_Table(tablename)
{
    if (tablename == this.table)
        return this;
        
    for (var i in this.children)
    {
        var obj = this.children[i].Table(tablename)
        if (obj)
            return obj;
    }
        
    return null;
}

DataTable.prototype.Row = DataTable_Row;
function DataTable_Row(key)
{
    for (var y in this.rows)
    {
        if (this.rows[y].key == key)
            return this.rows[y];
    }
    
    return null;
}

DataTable.prototype.Rows = DataTable_Rows;
function DataTable_Rows()
{
	if (this.showcalendar)
	{
		return this.calendarrows;
	}
	else
	{
		return this.rows;
	}
}

// Displays all the rows
DataTable.prototype.Html = DataTable_Html;
function DataTable_Html(node)
{
    if (node.sourcerows == null)
    {
        node.Request();
        return "Loading...";
    }

    var rows		= node.Rows();
    var table       = new Table({border: '1', width: '100%', id: node.id});
    var yy          = 0, xx = 2;
    var colselect   = table.Cell(0,0);
    var rowselect   = table.Cell(1,0);
    
    colselect.control.onclick   = "ObjectFind(this,'datatable').ColumnMenu(this)";
    colselect.control.value     = '>';
    colselect.control.title     = 'Select the columns you was to see';
    colselect.control.Class     = 'tool';
    rowselect.control.onclick	= "ObjectFind(this,'datatable').RowSelect(this)";
    rowselect.control.type		= 'checkbox';
    
    for (var col in this.cols)
    {
        if (!this.cols[col].head)
            continue;
            
        var column	= this.cols[col];
        var head 	= table.Cell(xx++,0).control;
        
        head.value    = col;
        head.Class    = column.Class();
        head.onclick  = "ObjectFind(this,'datatable').Sort('" + col + "')";
        head.title    = column.comment;
        
        if (column.sortorder == 1)
            head.Class = this.sortdirection > 0 ? 'colsorted' : 'colsorteddescending';
    }
    
    for (var y in rows)
    {
        var row     = rows[y];
        var xx      = 0;
        var exp		= table.Cell(xx++,++yy).control;
        var sel		= table.Cell(xx++,yy).control;
    
        table.Row(yy).id    = 'tr'+row.id;
        exp.id              = 'exp'+row.id;
        exp.onclick         = "ObjectFind(this,'datarow').Open()";
        exp.value           = row.open ? '-' : '+';
        exp.Class           = 'tool';
        exp.title			= 'Hide or show this item';
        sel.id              = 'sel'+row.id;
        sel.type            = 'checkbox';
        sel.onclick         = "ObjectFind(this,'datarow').Selected()";
        sel.title			= 'Select this item';
                
        for (var col in this.cols)
        {
            if (this.cols[col].head)
            {
                var head = table.Cell(xx++,yy);
                head.props.id      = 'head' + row.id + col;
                head.props.title   = this.cols[col].comment;
            }
        }
        
        var rowcell = table.Cell(0,++yy);

        rowcell.props.id       = 'row' + row.id;
        rowcell.props.colspan  = xx;
        rowcell.html           = row.Html();
        row.sortorder		   = yy;
    }
    
    return table.Html();
}

DataTable.prototype.DisplayRows = DataTable_DisplayRows;
function DataTable_DisplayRows(node)
{
    $('node_' + node.id).datatable = this;
    $('node_' + node.id).datanode  = node;
    $('node_' + node.id).innerHTML = this.Html(node);
}

// Updates the states of all the rows
DataTable.prototype.UpdateRows = DataTable_UpdateRows;
function DataTable_UpdateRows(node,start)
{
    var rows = node.Rows();
    var stop = this.parent == null ? Math.min(rows.length,start+100) : rows.length

    for (var y = start; y < stop; y++)
        rows[y].Update();
        
    if (stop == rows.length)
        root.UpdateStatus();        
    else
        Actions("root.UpdateRows(root," + stop + "," + rows.length + ")");
}

DataTable.prototype.GetStatus = DataTable_GetStatus;
function DataTable_GetStatus(statuses)
{
    var status = {Changed: 0, New: 0, Deleted: 0, Found: 0, Total: 0};
    
    for (var y in this.rows)
    {
        var row = this.rows[y];
        
        if (this.parent == null)			status.Found += row.found;
        if (row.newid == 0 || row.changed)	status.Total++;
        if (row.newid >  0 && row.changed)	status.New++;
        else if (row.deleted)           	status.Deleted++;
        else if (row.changed)           	status.Changed++;
    }
    
    statuses[statuses.length] = status;
    
    for (var i in this.children)
        this.children[i].GetStatus(statuses);
}

// Updates the status, returns non-zero if there have been any edits
DataTable.prototype.UpdateStatus = DataTable_UpdateStatus;
function DataTable_UpdateStatus(clearmenu)
{
    var statuses	= [];
    var html		= '';
    
    this.GetStatus(statuses);

    for (var cat in statuses[0])
    {
        var list = '';
        
        for (var i in statuses)
            list += (i == '0' ? '' : '/') + statuses[i][cat];
            
        html += '<span class=row'+cat.toLowerCase()+'>&nbsp;'+cat+': '+list+'&nbsp;</span> ';
    }

    if (clearmenu == null || clearmenu)
        $('menu').innerHTML = "";
        
    $('status').innerHTML =   html;
    $('undo').title = "Undo " + undo.Last().name;
    $('redo').title = "Redo " + redo.Last().name;
}

DataTable.prototype.CalendarDate = DataTable_CalendarDate;
function DataTable_CalendarDate(date,x)
{
	var key = DateToString(DateAdd(date,0,0,x));
	
	if (this.calendardates[key] == null)
		this.calendardates[key] = [];
		
	return this.calendardates[key];
}

DataTable.prototype.CalendarMake = DataTable_CalendarMake;
function DataTable_CalendarMake()
{
	var calendarstart	= null;
	var calendarstop	= null;
	var calendarslots	= 0;
	var slotid			= 0;

	this.calendardates 	= {};
	this.calendaritems 	= [];
	this.calendarblanks = {};


	for (var y in this.rows)
	{
		var row 	= this.rows[y];

		if (row.newid > 0 && !row.changed)
			continue;
	
		var items	= this.CalendarRowItems(row.data, row);
		
		for (var i in items)
		{
			items[i].row = row;
			this.calendaritems[this.calendaritems.length] = items[i];
		}
	}
	
	for (var i in this.calendaritems)
	{
		var item	= this.calendaritems[i];
		var span	= Math.max(1,Number(item.span));
		var start	= DateFromString(item.date || item.row.data.date);
		var stop	= DateAdd(start,0,0,span-1);
		var x 		= 0;
		
		item.start = new Date(start);
		item.stop  = new Date(stop);
		item.slots = [];
		item.field = item.field || 'date';
		
		for (var y = 1; ; y++)
		{
			while (this.CalendarDate(start,x)[y] == null && x < span)
				x++;
			
			if (x < item.span)
				continue;
			
			for (x = 0; x < span; x++)
				this.CalendarDate(start,x)[y] = item.slots[x] = {x:x, item:item, id: 'slot'+slotid++};
				
			
			calendarslots = Math.max(calendarslots,y+1);
			if (calendarstart == null)
			{
				calendarstart = start;
				calendarstop = stop;
			}
			else
			{
				if (calendarstart > start)
					calendarstart = start;
				if (calendarstop < stop)
					calendarstop = stop;
			}
				
				
			break;
		}			
	}
	
	this.calendarslots = calendarslots;
	this.calendarstart = calendarstart;
	this.calendarstart.setDate(1);
	this.calendarstop = calendarstop;
	this.calendarstop.setDate(32);
	this.calendarstop.setDate(0);
}

DataTable.prototype.CalendarHtml = DataTable_CalendarHtml;
function DataTable_CalendarHtml()
{
	if (!this.showcalendar)
		return "";
	
	var calendar = new Table();
	var mm		 = 0;
	var offset	 = this.calendaroffset || 0;
	var blankid	 = 0;

	this.CalendarMake();

	for (var dMonth = this.calendarstart; dMonth < this.calendarstop; dMonth = DateAdd(dMonth,0,1,0))
	{
		var month = new Table();
		
		month.Cell(0,0).html = months[dMonth.getMonth()] + ' ' + dMonth.getFullYear();
		month.Cell(0,0).props.colspan = 7;
		
	    for (var d = 0; d < 7; d++)
		{
			var xx   = (d + offset) % 7;
			
			month.Cell(xx,1).props.width=30;
			month.Cell(xx,1).html 		= days[d].substr(0,3);
		}

		
		for (var dDay = dMonth, yy = 2; dDay.getMonth() == dMonth.getMonth(); dDay = DateAdd(dDay,0,0,1))
		{
			var xx 		= (dDay.getDay() + offset) % 7;
			var day		= month.Cell(xx,yy);
			var slots	= this.CalendarDate(dDay,0);
			
			day.html = dDay.getDate();
			
			for (var i = 0; i < this.calendarslots; i++)
			{
				var cell = month.Cell(xx,yy + i).props;
				var slot = slots[i];
				
				cell.Class = days[dDay.getDay()].toLowerCase();
				
				if (slot)
				{
					cell.height		= 10;
					cell.id			= slot.id;
					cell.onclick	= "ObjectFind(this,'datatable').CalendarSelect([this.datarow])";
					cell.style		= "border-top: solid black 1px; "+
									  "border-bottom: solid black 1px;"+
									  "background-color:" + slot.item.color + ";";
					
					if (dDay <= slot.item.start)
						cell.style += "border-left: solid black 1px;";
					
					if (dDay >= slot.item.stop)
						cell.style += "border-right: solid black 1px;";
				}
				else
				{
					cell.id			= 'blank' + blankid++;
					cell.onclick	= "ObjectFind(this,'datatable').CalendarSelectDate(this.datadate)";
					this.calendarblanks[cell.id] 	= new Date(dDay);
				}
			}
			
			yy += xx == 6 ? (1+this.calendarslots) : 0;
		}
		
		calendar.Cell(mm++,0).html = month.Html();
	}	
	
	return calendar.Html();
}

DataTable.prototype.UpdateCalendar = DataTable_UpdateCalendar;
function DataTable_UpdateCalendar()
{
	if ($('calendar') == null)
		return;

	$('calendar').innerHTML = this.CalendarHtml();
	$('calendar').datatable = this;
	
	for (var i in this.calendarblanks)
		$(i).datadate = this.calendarblanks[i];
	
	for (var i in this.calendaritems)
	{
		var item = this.calendaritems[i];
		
		for (var x in item.slots)
		{
			var slot = item.slots[x];
			var cell = $(slot.id);
			
			cell.dataslot	= slot;
			cell.datarow	= item.row;
			cell.title 		= item.text;
		}
	}
}

DataTable.prototype.CalendarSelect = DataTable_CalendarSelect;
function DataTable_CalendarSelect(rows)
{
	this.calendarrows = [];
	
	for (var i in rows)
		this.calendarrows[this.calendarrows.length] = rows[i];
	
	for (var i in rows)
		rows[i].open = this.calendarrows.length == 1;
	
    Actions( 'root.DisplayRows(root)',
             'root.UpdateRows(root,0)');
}

DataTable.prototype.CalendarSelectDate = DataTable_CalendarSelectDate;
function DataTable_CalendarSelectDate(date)
{
	var rows = {};
	
	for (var i in this.calendaritems)
	{
		var item = this.calendaritems[i];
		var row  = item.row;
	
		if (date >= item.start && date <= item.stop)
			rows[row.id] = row;
	}

	this.CalendarSelect(rows);
}

DataTable.prototype.PostTable = DataTable_PostTable;
function DataTable_PostTable(post)
{
    var table	= this.table.replace(new RegExp('\\.','g'),',');
    var jsdt	= 'root.Table("' + this.table + '")';
    
    post.prefs += jsdt + '.sortdirection=' + this.sortdirection + ';\n';
    for ( var col in this.cols )
    {
        post.prefs += jsdt + '.cols.'+col+'.sortorder=' + this.cols[col].sortorder + ';\n';
        post.prefs += jsdt + '.cols.'+col+'.head='      + this.cols[col].head + ';\n';
    }

    for (var y in this.rows)
    {
        var row = this.rows[y];
        
        if (row.newid == 0 && row.open != Boolean(this.rowopen))
            post.prefs += jsdt + '.Row("' + row.key + '").open='+row.open+';\n';
        
        if (row.newid == 0 && row.selected)
        {
            post.prefs += jsdt + '.Row("' + row.key + '").selected='+row.selected+';\n';
            
            if (!this.ro && (post.filtertable == '' || post.filtertable == this.table))
            {
                post.filtertable	= this.table;
                post.filterids		+= row.key + ',';
            }
        }
        
        if (row.deleted)
        {
            post['delete,' + table + ',' + row.key] = '1';
            continue;
        }
    
        if (row.changed)
        {
            for (var col in this.cols)
            {
                if (col == this.keycolumn)
                    continue;
            
                if (row.newid)
                {
                    if (row.data[col] != this.cols[col]['default'])
                        post['create,' + table + ',' + row.key + ',' + col] = row.data[col];
                }
                else 
                {
                    if (row.data[col] != row.orig[col])
                         post['update,' + table + ',' + row.key + ',' + col] = row.data[col];
                }
            }
        }
    }
    
    for (var i in this.children)
        this.children[i].PostTable(post);

    if (post.filtertable == '' && !this.ro)
        post.filtertable = this.table;
}


// Updates the data, prior to submitting the form
DataTable.prototype.PostBuild = DataTable_PostBuild;
function DataTable_PostBuild(generatefile)
{
    this.post = {datetime: DateTimeToString(new Date()), filtertable:'', filterids:'', prefs: ''};
    this.PostTable(this.post);

    this.post['generatefile'] 	= generatefile;
    this.post['usersetting']	= window.location.pathname;
    this.post['uservalue'] 		= this.post.prefs;
}

DataTable.prototype.PostAssign = DataTable_PostAssign;
function DataTable_PostAssign()
{
    var html = '';
    
    for (var i in this.post)
    {
        if (this.post[i] != null)
            html += '<input type=hidden name="' + i + '" />';
    }
        
    $('postdata').innerHTML = html;

    for (var i in this.post)
    {
        if (this.post[i] != null)
            document.newsletterform[i].value = this.post[i];
    }
}

// Updates all the rows
DataTable.prototype.UpdateAllRows = DataTable_UpdateAllRows;
function DataTable_UpdateAllRows()
{
    if (this.parent == null)
        undo.New("Updating all rows");
    
    var order = [];

    for (var y in this.rows)
    {
        var row = this.rows[y];
        
        if (row.newid > 0)
            continue;
    
        order[y] = row.data.order;
        row.Make();
    }
    
    if (this.cols.order != null)
    {
        order.sort(SortByNumber);
        for (var y in order)
            undo.Set(this.rows[y],'order',order[y]);
    }
    
    for (var i in this.children)
        this.children[i].UpdateAllRows();
}


DataTable.prototype.SetSelected = DataTable_SetSelected;
function DataTable_SetSelected(col,value)
{
    for (var y in this.rows)
    {
        var row = this.rows[y];
    
        if (this.cols[col] != null && row.selected)
        {
            undo.Set(this.rows[y],col,value);
            row.Update();
        }
    }

    for (var child in this.children)
        this.children[child].SetSelected(col,value);
}

DataTable.prototype.DeleteSelected = DataTable_DeleteSelected;
function DataTable_DeleteSelected()
{
    for (var y in this.rows)
    {
        var row = this.rows[y];
        
        if (!row.selected || row.newid || this.ro)
            continue;
            
        if (root.deleted == null)            
        {
            root.deleted = !row.deleted;
            undo.New(root.deleted ? "Delete" : "Undelete");
        }
            
        undo.Set(row,'deleted',root.deleted);
        
        if (row.open)
            row.Open();
    }
    
    for (var i in this.children)
        this.children[i].DeleteSelected();
}

DataTable.prototype.RowSelect = DataTable_RowSelect;
function DataTable_RowSelect(obj)
{
    var rows = ObjectFind(obj,'datanode').Rows();
    
    for (var y in rows)
    {
        var row = rows[y];
        
        $('sel'+row.id).checked = row.selected = obj.checked;
    }
}

// Shows the column menu
DataTable.prototype.ColumnMenu = DataTable_ColumnMenu;
function DataTable_ColumnMenu(obj)
{
    var table   = new MenuTable(obj, null, '');
    var yy      = 1;
    
    table.Cell(1,0).html = '<u>Selected</u>';
    table.Cell(2,0).html = '<u>Column</u>';
    table.Cell(3,0).html = this.sortdirection > 0 ? '<u>Sort Order</u>' : '<u>Sort Order (descending)</u>';
    
    for (var col in this.cols)
    {
        table.Cell(1,yy).control.type    = 'checkbox';
        table.Cell(1,yy).control.onclick = "ObjectFind(this,'datatable').ColumnHead('"+col+"')";
        table.Cell(1,yy).control.id      = 'columnselect' + yy;
        table.Cell(2,yy).html            = '<label for="columnselect'+yy+'">' + col + '</label>';
        table.Cell(3,yy).html            = this.cols[col].sortorder > 3 ? '' : this.cols[col].sortorder;
        table.Cell(2,yy).props.Class     = 
        table.Cell(3,yy).props.Class     = this.cols[col].Class();
        
        if (this.cols[col].head)
            table.Cell(1,yy).control.checked  = 'yes';

        yy++;
    }
    
    table.Show().datatable = this;
}

DataTable.prototype.ColumnHead = DataTable_ColumnHead;
function DataTable_ColumnHead(col)
{
    this.cols[col].head = !this.cols[col].head;
    Actions('root.DisplayRows(root)',
            'root.UpdateRows(root,0)');
}

// Sorts the rows using the specified sort column
DataTable.prototype.SortRows = DataTable_SortRows;
function DataTable_SortRows(sort)
{
    if (sort)
    {
        if (this.cols[sort].sortorder == 1)
            this.sortdirection = Number(this.sortdirection) < 0 ? 1 : -1;
        else
            this.sortdirection = 1;
            
        this.cols[sort].sortorder = 0;
    }

    // Rebuild the sort list
    this.sortlist = [];
    for (var col in this.cols)
        this.sortlist[this.sortlist.length] = this.cols[col];
    this.sortlist.sort(function(a,b){return a.sortorder - b.sortorder;});
    for (var col in this.sortlist)
        this.sortlist[col].sortorder = Number(col)+1;

    // build a collection of the numeric columns
    var numeric = {};
    for (var col in this.cols)
    {
        if (this.cols[col].type.indexOf('int') == 0)
            numeric[col] = true;            
    }
    
    // Make the numeric columns numeric, makes for simpler sorting
    for (var y in this.rows)
    {
        var row = this.rows[y];
        
        if (row.newid)
            break;
        
        for (var col in numeric)
            row.data[col] = Number(row.data[col]);
    }
    
    this.rows.sort(SortByRow);
    
    // Make the numeric columns strings again
    for (var y in this.rows)
    {
        var row = this.rows[y];
    
        if (row.newid)
            break;
            
        for (var col in numeric)
            row.data[col] = String(row.data[col]);
    }        
    
    for (var i in this.children)
        this.children[i].SortRows();
}

// Sorts the columns
DataTable.prototype.Sort = DataTable_Sort; 
function DataTable_Sort(sort)
{
    root.sorttable = this;
    Actions('root.sorttable.SortRows("'+sort+'")',
            'root.DisplayRows(root)',
            'root.UpdateRows(root,0)');
}

function Node(datatable,parentrow,id)
{
    this.datatable		= datatable;
    this.parentrow		= parentrow;
    this.id				= id;
}

Node.prototype.Request = Node_Request;
function Node_Request()
{
    if (this.request != null || this.sourcerows != null)
        return;
    
    var node	= this;
    var query	= "SELECT " + this.datatable.columns + " FROM " + this.datatable.table +
                    " WHERE `" + this.datatable.linkcolumn + "` = " + 
                            "'" +	this.parentrow.data[this.datatable.linkcolumn] + "'" +
                    (this.datatable.where == null ? "" : (" AND " + this.datatable.where));
    
    this.query	 = query.replace(new RegExp('%','g'),'%25');
    this.request = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("MSXML2.XMLHTTP.3.0");
    this.request.open( "GET", "json.php?object=" + this.query + "&random=" + DateTimeToRandom(), true );
    this.request.onreadystatechange = function() {node.StateChange(); };
    this.request.send(null);
}

Node.prototype.StateChange = Node_StateChange;
function Node_StateChange()
{
    if ( this.request == null || this.request.readyState != 4)
        return;
        
    try
    {
        this.sourcerows = eval( "(" + this.request.responseText + ")" );
        this.request	= null;
    }
    catch(e)
    {
        alert(this.query + "\r\n" + this.request.responseText);
        return;
    }

    this.datatable.LoadRows(this.sourcerows, this.parentrow);
    root.LoadPreferences();
    this.datatable.SortRows();
    this.datatable.DisplayRows(this);
    this.datatable.UpdateRows(this,0);
}

Node.prototype.Rows = Node_Rows;
function Node_Rows()
{
    var rows = [];

    for (var y in this.datatable.rows)
    {
        var row = this.datatable.rows[y];
    
        if (row.data[this.datatable.linkcolumn] == this.parentrow.data[this.datatable.linkcolumn])
            rows[rows.length] = row;
    }
    
    return rows;
}

function AlwaysFalse()
{
    return false;
}

function AlwaysTrue()
{
    return true;
}

// Creates a date from a string
function DateAdd(date,yy,mm,dd)
{
    return new Date(date.getFullYear() + yy,
					date.getMonth()    + mm,
					date.getDate()     + dd);
}


// Creates a date from a string
function DateFromString(date)
{
    var yy = Number(date.substr(0,4));
    var mm = Number(date.substr(5,2))-1;
    var dd = Number(date.substr(8,2));
    
    return new Date(yy,mm,dd);
}

// Creates a string from a date
function DateToString(date)
{
    return date.getFullYear() + '-' + 
            (date.getMonth()+101).toString().substr(1,2) + '-' + 
            (date.getDate()+100).toString().substr(1,2);
}            

// Creates a string from a date and time
function DateTimeToString(date)
{
    return DateToString(date) + ' ' + 
            (date.getHours()+100).toString().substr(1,2) + '-' + 
            (date.getMinutes()+100).toString().substr(1,2) + '-' + 
            (date.getSeconds()+100).toString().substr(1,2);
}            

// Creates a string from a date and time
function DateTimeToRandom(date)
{
    date = date == null ? new Date() : date;

    return date.getFullYear() +
            (date.getMonth()+101).toString().substr(1,2) +
            (date.getDate()+100).toString().substr(1,2) + "_" +
            (date.getHours()+100).toString().substr(1,2) +
            (date.getMinutes()+100).toString().substr(1,2) +
            (date.getSeconds()+100).toString().substr(1,2);
}            

// Callback function to sort an array by number
function SortByNumber(a,b)
{
    if (Number(a) < Number(b)) return -1;
    if (Number(a) > Number(b)) return  1;
    return 0;
}

// Callback function to sort an array using the selected columns
function SortByRow(a,b)
{
    if (a.newid < b.newid) return -1;
    if (a.newid > b.newid) return  1;

    var sortlist	= a.datatable.sortlist;
    var direction	= a.datatable.sortdirection;
    
    for (var i in sortlist)
    {
        var col = sortlist[i].field;
    
        if (a.data[col] < b.data[col]) return -direction;
        if (a.data[col] > b.data[col]) return  direction;
    }
    
    if (a.key < b.key) return -direction;
    if (a.key > b.key) return  direction;
    return 0;
}

function WysiwygSetup(ed)
{
    ed.onChange.add(WysiwygOnChange);
}

function WysiwygOnChange(ed)
{
    var row = ObjectFind($(ed.id),'datarow');
    var col = ObjectFind($(ed.id),'datacol');
    
    undo.New("Edit " + col);
    undo.Set(row,col,ed.getContent());
}

function WysiwygFunction(data)
{
    return data.wysiwyg == '1';
}
