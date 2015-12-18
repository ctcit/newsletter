ObjectInherit(MenuTable,Table);

// Constructor for the Table object
function Table(props)
{
    this.row   = [];
    this.cell  = [];
    this.props = props || {};
    this.xmax  = 0;
    this.ymax  = 0;
}

// Creates row
Table.prototype.Row = Table_Row;
function Table_Row(y)
{
    if (this.row[y] == null)
        this.row[y] = {};
        
    return this.row[y];
}

// Creates cell
Table.prototype.Cell = Table_Cell;
function Table_Cell(x,y)
{
    if (this.cell[x] == null)
        this.cell[x] = [];
        
    if (this.cell[x][y] == null)
        this.cell[x][y] = {	html:'', 
							props:{rowspan:1, colspan:1}, 
							controltag:'input',
							controlhtml:'',
							control:{type:'button'}};
        
    this.xmax = Math.max(this.xmax,x+1);
    this.ymax = Math.max(this.ymax,y+1);
    return this.cell[x][y];
}

// Generates properties for an object
function TableProps(tag, obj, html)
{
    var props = '';

    for (var i in obj)
        props += ' ' + i + '="' + obj[i] + '"';
    
    if (html == '') return '<' + tag + props + ' />';
    else    	    return '<' + tag + props + '>' + html + '</' + tag + '>';
}

// Generates html for a table
Table.prototype.Html = Table_Html;
function Table_Html()
{
    var html = '';

    for (var y = 0; y < this.ymax; y++)
    {
		var rowhtml = '';
	
        for (var x = 0; x < this.xmax; x++)
        {
            var cell = this.Cell(x,y);
            
            if (cell.spanned)
                continue;
                
            if (cell.control.value	!= null 	|| 
				cell.control.type	!= 'button'	|| 
				cell.controltag		!= 'input' 		)
                cell.html = TableProps(cell.controltag, cell.control, cell.controlhtml);
                
            rowhtml += TableProps('td', cell.props, cell.html);
            
            for (var xx = 0; xx < cell.props.colspan; xx++)
            {
                for (var yy = 0; yy < cell.props.rowspan; yy++)
                    this.Cell(x+xx,y+yy).spanned = xx > 0 || yy > 0;
            }
        }
		
        html += TableProps('tr',this.Row(y),rowhtml);
    }
    
    return TableProps('table', this.props, html);
}

// Constructor for the MenuTable object
function MenuTable(owner, filter)
{
	this.Table = ObjectInherit(this,Table);
    this.Table({id:'menutable'});
    this.owner        		= owner;
    this.filter         	= filter;
	this.div     			= $("menu");
    this.Cell(0,0).control	= {type:"button", onclick:"ObjectFind(this,'menuobject').Hide()",
							   value:"X", title:"Close menu", id:"menuclose"};
}

MenuTable.prototype.Show = MenuTable_Show;
function MenuTable_Show(x,y)
{
	var ownerpos = new Rectangle(this.owner,(x || 0),(y || 0) + this.owner.offsetHeight);
	var menupos	 = new Rectangle(this.div);
	
    if (this.filter     	== null         &&
        this.div.innerHTML	!= ''           && 
        menupos.x	    	== ownerpos.x   &&
        menupos.y       	== ownerpos.y       )
    {
		this.Hide();
		return this;
    }
    else
    {
	    this.div.style.left	= ownerpos.x;
	    this.div.style.top	= ownerpos.y;
        this.div.innerHTML	= this.Html();
		$('menutable').menuobject = this;
		
        var selected    = $('selected');
        var menuselect  = $('menuselect');
        var menuclose   = $('menuclose');
        
        (selected || menuselect || menuclose).focus();
        
        if (menuselect)
            menuselect.style.width = menuselect.offsetParent.offsetWidth;
			
		return this.div;
    }
}

MenuTable.prototype.Hide = MenuTable_Hide;
function MenuTable_Hide()
{
    this.owner.focus();
    setTimeout("$('menu').innerHTML = ''");  
}

function Rectangle(obj,x,y)
{
	this.obj	= obj;
	this.x 		= x || 0;
	this.y 		= y || 0;
	this.sx 	= obj.offsetWidth;
	this.sy 	= obj.offsetHeight;
	
	while (obj)
	{
		this.x	+= obj.offsetLeft;
		this.y	+= obj.offsetTop;
		obj	    =  obj.offsetParent;
	}
}

Rectangle.prototype.Contains = Rectangle_Contains;
function Rectangle_Contains(x,y)
{
	return x >= this.x && y >= this.y && x < this.x+this.sx && y < this.y+this.sy;
}
