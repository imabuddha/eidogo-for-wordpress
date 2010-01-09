/* To jazz up SGF administration a bit */
function wpeidogo_theme_change(id) {
	var checkProblem = document.getElementById('wpeidogo-eidogo_theme-'+id+'-problem');
	var methodRow = document.getElementById('wpeidogo-embed_method-'+id+'-iframe').parentNode.parentNode;
	var colorRow = document.getElementById('wpeidogo-problem_color-'+id+'-auto').parentNode.parentNode;
	var problemCategoryRow = document.getElementById('attachments['+id+'][problem_category]').parentNode.parentNode;
	var problemDifficultyRow = document.getElementById('attachments['+id+'][problem_difficulty]').parentNode.parentNode;
	var gameCategoryRow = document.getElementById('attachments['+id+'][game_category]').parentNode.parentNode;

	if (checkProblem.checked) {
		colorRow.style.display              = 'table-row';
		problemCategoryRow.style.display    = 'table-row';
		problemDifficultyRow.style.display  = 'table-row';
		methodRow.style.display             = 'none';
		gameCategoryRow.style.display       = 'none';
	} else {
		colorRow.style.display              = 'none';
		problemCategoryRow.style.display    = 'none';
		problemDifficultyRow.style.display  = 'none';
		methodRow.style.display             = 'table-row';
		gameCategoryRow.style.display       = 'table-row';
	}

	return true;
}

/* vim: set ts=4 sts=4 sw=4 noet : */
