/* To jazz up SGF administration a bit */
function wpeidogo_theme_change(id) {
	var checkProblem = document.getElementById('wpeidogo-eidogo_theme-'+id+'-problem');
	var methodRow = document.getElementById('wpeidogo-embed_method-'+id+'-iframe').parentNode.parentNode;
	var colorRow = document.getElementById('wpeidogo-problem_color-'+id+'-auto').parentNode.parentNode;

	if (checkProblem.checked) {
		methodRow.style.display = 'none';
		colorRow.style.display = '';
	} else {
		methodRow.style.display = '';
		colorRow.style.display = 'none';
	}

	return true;
}
// vim:noet:ts=4
