function ObjectCopy(src,dst)
{
	if (src == null)
		return;
	for (var i in src)
		dst[i] = src[i];
	return dst;
}

function ObjectInherit(object,baseclassobject)
{
	for (var i in baseclassobject.prototype)
	{
		if (object[i] == null)
			object[i] = baseclassobject.prototype[i];
	}
	
	return baseclassobject;
}

function ObjectFind(obj,field)
{
	while (obj && obj[field] == null)
		obj = obj.parentNode;
	
	return obj && obj[field];
}

function $(id)
{
	return document.getElementById(id);
}
