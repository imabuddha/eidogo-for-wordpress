<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered blog with the EidoGo SGF viewer and editor.
Version: 0.7.2
Author: Thomas Schumm
Author URI: http://www.fortmyersgo.org/
*/

/*
Copyright © 2009 Thomas Schumm <phong@phong.org>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$sgf_count = 0;
$sgf_prepared_markup = array();

function wpeidogo_parse_attributes($params) { # {{{
	$pattern = '/(\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^"\'\s>]*)/';
	preg_match_all($pattern, $params, $matches, PREG_SET_ORDER);
	$attrs = array();

	foreach ($matches as $match) {
		if (($match[2][0] == '"' || $match[2][0] == "'") && $match[2][0] == $match[2][strlen($match[2])-1])
			$match[2] = substr($match[2], 1, -1);
		$attrs[strtolower($match[1])] = html_entity_decode($match[2]);
	}

	return $attrs;
} # }}}

function wpeidogo_prepare_sgf( $matches, $theme='compact' ) { # {{{
	global $sgf_count, $sgf_prepared_markup;
	$wpu = WP_PLUGIN_URL;

	list($whole_tag, $params, $sgf_data) = $matches;

	# Clean up the SGF data
	if (!trim($sgf_data))
		$sgf_data = "(;GM[1]FF[4]CA[UTF-8]SZ[19])";
	else
		$sgf_data = trim($sgf_data);

	$params = wpeidogo_parse_attributes($params);

	# Allow theme="foo" to override the default theme
	if ($params['theme'])
		$theme = $params['theme'];

	# For the caption
	$caption = htmlspecialchars($params['caption']);
	if ($params['href']) {
		if (!$caption) $caption = '[link]';
		$caption = '<a href="'.htmlspecialchars($params['href']).'">' . $caption . '</a>';
	}
	if ($caption)
		$caption = '<p class="wp-caption-text">'.$caption.'</p>';

	# Try to figure out who is to play (only used for problem mode)
	if ($params['problemcolor'])
		$params['problemcolor'] = strtoupper(substr($params['problemcolor'], 0, 1));
	elseif (preg_match('/PL\[(W|B)\]/', $sgf_data, $pgroups))
		$params['problemcolor'] = $pgroups[1];

	# Default to view mode except if we're a problem
	if (!$params['mode'] && $theme != 'problem')
		$params['mode'] = 'view';

	$embed_method = ($theme == 'full' || $theme == 'compact' ? 'iframe' : 'inline');

	if ($theme == 'full-inline' || $theme == 'full') { # {{{
		$frame_w = 720; $frame_h = 600;
		$js_config = array(
			'theme'             => "full",
			'enableShortcuts'   => ($theme == 'full'),
			'showComments'      => true,
			'showPlayerInfo'    => true,
			'showGameInfo'      => true,
			'showTools'         => true,
			'showOptions'       => true,
			'showNavTree'       => true,
			#'saveUrl'           => "backend/save.php",
			#'searchUrl'         => "backend/search.php",
			#'sgfPath'           => "backend/download.php?id=",
			#'downloadUrl'       => "backend/download.php?id=",
			#'scoreEstUrl'       => "backend/gnugo.php",
		);
	# }}}

	} elseif ($theme == 'compact-inline' || $theme == 'compact') { # {{{
		$frame_w = 423; $frame_h = 621;
		$js_config = array(
			'theme'             => "compact",
			'enableShortcuts'   => ($theme == 'compact'),
			'showComments'      => true,
			'showPlayerInfo'    => true,
			'showGameInfo'      => false,
			'showTools'         => true,
			'showOptions'       => true,
		);
	# }}}

	} elseif ($theme == 'problem') { # {{{
		$js_config = array(
			'theme'             => "problem",
			'enableShortcuts'   => false,
			'problemMode'       => true,
			'markVariations'    => false,
			'markNext'          => false,
			'shrinkToFit'       => true,
			'problemColor'      => $params['problemcolor'],
		);
	# }}}

	} else {
		$embed_method = 'unknown';
	}

	# Shortcut for loadPath
	if ($params['movenumber'] && is_numeric($params['movenumber']))
		$params['loadpath'] = array(0, $params['movenumber']);

	if (!$params['sgfurl'])
		$js_config['sgf'] = $sgf_data;
	$js_config['container'] = 'player-container-' . $sgf_count;
	foreach (array('loadPath', 'mode', 'sgfUrl') as $key) {
		$lkey = strtolower($key);
		if ($params[$lkey])
			$js_config[$key] = $params[$lkey];
	}

	$js_config = json_encode($js_config);

	if ($embed_method == 'inline') {
		$player_js = "var wpeidogo_player{$sgf_count} = new eidogo.Player({$js_config});";

	} elseif ($embed_method == 'iframe') {
		$iframe = json_encode('<iframe src="'.$wpu.'/eidogo-for-wordpress/iframe-player.html#'.$sgf_count.
			'" frameborder="0" width="'.$frame_w.'" height="'.$frame_h.'" scrolling="no"></iframe>');
		$player_js = <<<javascript
			var playerContainer{$sgf_count} = document.getElementById('player-container-{$sgf_count}');
			playerContainer{$sgf_count}.eidogoConfig = {$js_config};
			playerContainer{$sgf_count}.innerHTML = {$iframe};
javascript;

	} else {
		$unknown_theme = sprintf(__('Unknown wp-eidogo theme "%s".'), $theme);
		$player_js = 'alert(' . json_encode($unknown_theme) . ');';
	}

	$class = 'wp-eidogo wp-eidogo-' . $theme;
	if ($params['class'])
		$class .= ' ' . $params['class'];

	$ie6_warning = json_encode('<p class="ie6warning">' .  __(
		'Internet Explorer 6 is not currently supported by the EidoGo for
		WordPress plugin. Please, <a href="http://www.getfirefox.com/">get a
		real web browser</a>.') .
		'</p>');

	$sgf_prepared_markup[$sgf_count] = <<<html
		<div class="{$class}">
		<div class="player-container" id="player-container-{$sgf_count}"></div>
		<script type="text/javascript"><!--
			if (broken_browser) {
				document.getElementById('player-container-{$sgf_count}').innerHTML = {$ie6_warning};
			} else {
				$player_js
			}
		--></script>
		$caption
		</div>
html;

	return "\n\n[sgfPrepared id=\"".($sgf_count++)."\"]\n\n";

} # }}}

function wpeidogo_embed_sgf($matches) { # {{{
	global $sgf_prepared_markup;

	list($whole_tag, $id) = $matches;

	return $sgf_prepared_markup[$id];
} # }}}

function wpeidogo_prepare_markup($content) { # {{{
	$content = preg_replace_callback(
		'|\s*\[sgf(.*?)\](.*?)\[/sgf\]\s*|si',
		'wpeidogo_prepare_sgf', $content);

	return $content;
} # }}}

function wpeidogo_embed_markup($content) { # {{{
	$sgf_pattern = '\[sgfPrepared\s+id="(\d+)"\]';

	# Handle cases that have been modified by wpautop, etc.
	$content = preg_replace_callback(
		'|<p[^>]*>\s*'.$sgf_pattern.'\s*</p>|si',
		'wpeidogo_embed_sgf', $content);

	# Fallback in case those didn't happen
	$content = preg_replace_callback(
		'|'.$sgf_pattern.'|si',
		'wpeidogo_embed_sgf', $content);

	return $content;
} # }}}

function wpeidogo_head() { # {{{
	$wpu = WP_PLUGIN_URL;
	echo <<<html
	<link rel="stylesheet" media="all" type="text/css" href="{$wpu}/eidogo-for-wordpress/wp-eidogo.css" />
	<link rel="stylesheet" media="all" type="text/css" href="{$wpu}/eidogo-for-wordpress/eidogo-player-1.2/player/css/player.css" />
	<script type="text/javascript">
		var broken_browser = false;
	</script>
	<!--[if lt IE 7]>
	<script type="text/javascript">
		broken_browser = true;
	</script>
	<![endif]-->
	<script type="text/javascript" src="{$wpu}/eidogo-for-wordpress/eidogo-player-1.2/player/js/all.compressed.js"></script>
html;
} # }}}

function wpeidogo_mimetypes($mimes=null) {
	if (is_null($mimes))
		$mimes = array();
	$mimes['sgf'] = 'application/x-go-sgf';
	return $mimes;
}

# We want to embed the SGF data that is wholy unmolested by wpautop and other
# built-in wordpress functions, so we need to do our parsing BEFORE any such
# filters are called. However, we also want to avoid such filters modifying our
# markup, so we need to do the actual embedding at the end of the filter chain.
add_filter('the_content', 'wpeidogo_prepare_markup', 9);
add_filter('the_excerpt', 'wpeidogo_prepare_markup', 9);
add_filter('comment_text', 'wpeidogo_prepare_markup', 9);
add_filter('the_content', 'wpeidogo_embed_markup', 99);
add_filter('the_excerpt', 'wpeidogo_embed_markup', 99);
add_filter('comment_text', 'wpeidogo_embed_markup', 99);

# For necessary stylesheets and javascript files
add_action('wp_head', 'wpeidogo_head');

# Support for SGF files in media library
add_filter('upload_mimes', 'wpeidogo_mimetypes');

# vim:noet:ts=4
