function changeParent(cat_name, parent_name) {
	if (document.getElementsByName(parent_name)[0].value == 0)
		document.getElementsByName(cat_name)[0].disabled=false;
	else 
		document.getElementsByName(cat_name)[0].disabled=true;
}

window.onload = function() { changeParent('cat_id', 'parent_id'); }