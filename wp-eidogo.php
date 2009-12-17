<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered blog with the EidoGo SGF viewer and editor.
Version: 0.8.3
Author: Thomas Schumm
Author URI: http://www.fortmyersgo.org/
*/

/*	Copyright © 2009 Thomas Schumm <phong@phong.org>

	This program is free software: you can redistribute it and/or modify it
	under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or (at your
	option) any later version.

	This program is distributed in the hope that it will be useful, but WITHOUT
	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
	FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public
	License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

class WpEidoGoPlugin {

	var $sgf_count = 0;
	var $sgf_prepared_markup = array();
	var $sgf_mime_type = 'application/x-go-sgf';

	/* Initialization */
	function WpEidoGoPlugin() { # {{{
		$this->plugin_url = WP_PLUGIN_URL . '/eidogo-for-wordpress';
		$this->setup_hooks();
	} # }}}

	function setup_hooks() { # {{{
		# We want to embed the SGF data that is wholy unmolested by wpautop and other
		# built-in wordpress functions, so we need to do our parsing BEFORE any such
		# filters are called. However, we also want to avoid such filters modifying our
		# markup, so we need to do the actual embedding at the end of the filter chain.
		add_filter('the_content',  array(&$this, 'prepare_markup'), 9);
		add_filter('the_excerpt',  array(&$this, 'prepare_markup'), 9);
		add_filter('comment_text', array(&$this, 'prepare_markup'), 9);
		add_filter('the_content',  array(&$this, 'embed_markup'), 99);
		add_filter('the_excerpt',  array(&$this, 'embed_markup'), 99);
		add_filter('comment_text', array(&$this, 'embed_markup'), 99);

		# For necessary stylesheets and javascript files
		add_action('wp_head', array(&$this, 'eidogo_head_tags'));
		add_action('admin_head', array(&$this, 'eidogo_head_tags_admin'));

		# Support for SGF files in media library
		add_filter('upload_mimes', array(&$this, 'sgf_mimetypes'));
		add_filter('post_mime_types', array(&$this, 'add_media_tab'));
		add_filter('ext2type', array(&$this, 'sgf_extension'));
		add_filter('wp_mime_type_icon', array(&$this, 'sgf_icon'), 10, 3);
		add_filter('attachment_fields_to_edit', array(&$this, 'sgf_media_form'), 10, 2);
		add_filter('media_send_to_editor', array(&$this, 'sgf_send_to_editor'), 10, 3);
		add_filter('attachment_fields_to_save', array(&$this, 'save_sgf_info'), 10, 3);
	} # }}}

	/* HTML header */
	function eidogo_head_tags() { # {{{
		echo <<<html
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/wp-eidogo.css" />
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/eidogo-player-1.2/player/css/player.css" />
		<script type="text/javascript">
			var broken_browser = false;
		</script>
		<!--[if lt IE 7]>
		<script type="text/javascript">
			broken_browser = true;
		</script>
		<![endif]-->
		<script type="text/javascript" src="{$this->plugin_url}/eidogo-player-1.2/player/js/all.compressed.js"></script>
html;
	} # }}}

	function eidogo_head_tags_admin() { # {{{
		echo <<<html
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/wp-eidogo-admin.css" />
		<script type="text/javascript" src="{$this->plugin_url}/wp-eidogo-admin.js"></script>
html;
	} # }}}

	/* Media library */
	function simple_radio($field_name, $options, $post_id, $current=null, $onchange=false) { # {{{
		# Very simple code for generating radio button groups; assumes
		# $field_name and option keys don't have spaces or anything funny
		$name = "attachments[$post_id][$field_name]";
		$id_prefix = "wpeidogo-$field_name-$post_id";
		$elements = array();
		if ($onchange)
			$oc = " onchange='return wpeidogo_theme_change($post_id);'";
		foreach ($options as $key => $label) {
			$id = "$id_prefix-$key";
			$checked = ($current == $key ? " checked='checked'" : '');
			$elements[] = "<input type='radio' name='$name' id='$id' value='$key'$checked$oc />" .
				"<label for='$id'>$label</label>";
		}
		return join("\n", $elements);
	} # }}}

	function sgf_media_form($form_fields, $post=null) { # {{{
		if ($post->post_mime_type != $this->sgf_mime_type)
			return $form_fields;

		$form_fields['align'] = array(
			'label' => __('Alignment'),
			'input' => 'html',
			'html'  => image_align_input_fields($post, get_option('image_default_align')),
		);

		$meta = get_post_custom($post->ID);
		if (!$meta['_wpeidogo_theme']) $meta['_wpeidogo_theme'] = array('compact');
		if (!$meta['_wpeidogo_embed_method']) $meta['_wpeidogo_embed_method'] = array('iframe');
		if (!$meta['_wpeidogo_problem_color']) $meta['_wpeidogo_problem_color'] = array('auto');

		$themes = array('compact' => 'Compact', 'full' => 'Full', 'problem' => 'Problem');
		$form_fields['eidogo_theme'] = array(
			'label' => __('Theme'),
			'input' => 'html',
			'html' => $this->simple_radio('eidogo_theme', $themes, $post->ID, $meta['_wpeidogo_theme'][0], true),
		);

		$methods = array('iframe' => 'Iframe', 'inline' => 'Inline');
		$form_fields['embed_method'] = array(
			'label' => __('Embed Method'),
			'input' => 'html',
			'html' => $this->simple_radio('embed_method', $methods, $post->ID, $meta['_wpeidogo_embed_method'][0]),
		);

		$fmime = '<input type="hidden" name="attachments['.$post->ID.'][mime_type]"
				value="'.htmlspecialchars($post->post_mime_type).'" />';
		$sgf_url = $post->guid;
		$site_url = get_option('siteurl');
		if (substr($sgf_url, 0, strlen($site_url)) == $site_url)
			$sgf_url = substr($sgf_url, strlen($site_url));
		$furl = '<input type="hidden" name="attachments['.$post->ID.'][sgf_url]"
				value="'.htmlspecialchars($sgf_url).'" />';

		$formscript = "<script type='text/javascript'>wpeidogo_theme_change({$post->ID});</script>";

		$colors = array('auto' => 'Auto', 'B' => 'Black', 'W' => 'White');
		$form_fields['problem_color'] = array(
			'label' => __('Problem Color'),
			'input' => 'html',
			'html' => $this->simple_radio('problem_color', $colors, $post->ID, $meta['_wpeidogo_problem_color'][0]) .
				$fmime . $furl . $formscript,
		);

		return $form_fields;
	} # }}}

	function save_sgf_info($post, $input) { # {{{
		if (!$input['mime_type'] || $input['mime_type'] != $this->sgf_mime_type)
			return $post;

		if (!$post['ID'])
			return $post;

		update_post_meta($post['ID'], '_wpeidogo_theme', $input['eidogo_theme']);
		update_post_meta($post['ID'], '_wpeidogo_embed_method', $input['embed_method']);
		update_post_meta($post['ID'], '_wpeidogo_problem_color', $input['problem_color']);

		return $post;
	} # }}}

	function sgf_send_to_editor($html, $id, $post) { # {{{
		if (!$post['mime_type'] || $post['mime_type'] != $this->sgf_mime_type)
			return $html;

		$theme = $post['eidogo_theme'];
		if (!$theme)
			$theme = "compact";
		if ($post['embed_method'] == 'inline' && $theme != 'problem')
			$theme .= "-inline";

		$params = '';

		if ($post['sgf_url'])
			$params .= ' sgfUrl="'.htmlspecialchars($post['sgf_url']).'"';

		if ($theme && $theme != 'compact')
			$params .= ' theme="'.htmlspecialchars($theme).'"';

		if ($theme == 'problem' && $post['problem_color'] && $post['problem_color'] != 'auto')
			$params .= ' problemColor="'.htmlspecialchars($post['problem_color']).'"';

		if ($post['post_excerpt'])
			$params .= ' caption="'.htmlspecialchars($post['post_excerpt']).'"';

		if ($post['url'])
			$params .= ' href="'.htmlspecialchars($post['url']).'"';

		if ($post['align'] && $post['align'] != 'none')
			$params .= ' class="align'.htmlspecialchars($post['align']).'"';

		return '[sgf'.$params.'][/sgf]';
	} # }}}

	function sgf_icon($icon, $mime_type, $post_id) { # {{{
		if ($mime_type != $this->sgf_mime_type)
			return $icon;
		# The filename must be the same as one of the default icons
		# of the same dimensions or WordPress gets confused
		return $this->plugin_url . '/default.png';
	} # }}}

	function sgf_extension($types) { # {{{
		$types['interactive'][] = 'sgf';
		return $types;
	} # }}}

	function sgf_mimetypes($mimes=null) { # {{{
		if (is_null($mimes))
			$mimes = array();
		$mimes['sgf'] = $this->sgf_mime_type;
		return $mimes;
	} # }}}

	function add_media_tab($post_mime_types) { # {{{
		$post_mime_types[$this->sgf_mime_type] = array(
			__('SGF Files'), __('Manage SGF Files'), array(__('SGF File (%s)'), __('SGF Files (%s)')));
		return $post_mime_types;
	} # }}}

	/* Embedding */
	function parse_attributes($params) { # {{{
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

	function prepare_sgf($matches, $theme='compact') { # {{{
		list($whole_tag, $params, $sgf_data) = $matches;

		# Clean up the SGF data
		if (!trim($sgf_data))
			$sgf_data = "(;GM[1]FF[4]CA[UTF-8]SZ[19])";
		else
			$sgf_data = trim($sgf_data);

		$params = $this->parse_attributes($params);

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
		$js_config['container'] = 'player-container-' . $this->sgf_count;
		foreach (array('loadPath', 'mode', 'sgfUrl') as $key) {
			$lkey = strtolower($key);
			if ($params[$lkey])
				$js_config[$key] = $params[$lkey];
		}

		$js_config = json_encode($js_config);

		if ($embed_method == 'inline') {
			$player_js = "var wpeidogo_player{$this->sgf_count} = new eidogo.Player({$js_config});";

		} elseif ($embed_method == 'iframe') {
			$iframe = json_encode('<iframe src="'.$this->plugin_url.'/iframe-player.html#'.$this->sgf_count.
				'" frameborder="0" width="'.$frame_w.'" height="'.$frame_h.'" scrolling="no"></iframe>');
			$player_js = <<<javascript
				var playerContainer{$this->sgf_count} = document.getElementById('player-container-{$this->sgf_count}');
				playerContainer{$this->sgf_count}.eidogoConfig = {$js_config};
				playerContainer{$this->sgf_count}.innerHTML = {$iframe};
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

		$this->sgf_prepared_markup[$this->sgf_count] = <<<html
			<div class="{$class}">
			<div class="player-container" id="player-container-{$this->sgf_count}"></div>
			<script type="text/javascript"><!--
				if (broken_browser) {
					document.getElementById('player-container-{$this->sgf_count}').innerHTML = {$ie6_warning};
				} else {
					$player_js
				}
			--></script>
			$caption
			</div>
html;

		return "\n\n[sgfPrepared id=\"".($this->sgf_count++)."\"]\n\n";

	} # }}}

	function embed_sgf($matches) { # {{{
		list($whole_tag, $id) = $matches;

		return $this->sgf_prepared_markup[$id];
	} # }}}

	function prepare_markup($content) { # {{{
		$content = preg_replace_callback(
			'|\s*\[sgf(.*?)\](.*?)\[/sgf\]\s*|si',
			array(&$this, 'prepare_sgf'), $content);

		return $content;
	} # }}}

	function embed_markup($content) { # {{{
		$sgf_pattern = '\[sgfPrepared\s+id="(\d+)"\]';

		# Handle cases that have been modified by wpautop, etc.
		$content = preg_replace_callback(
			'|<p[^>]*>\s*'.$sgf_pattern.'\s*</p>|si',
			array(&$this, 'embed_sgf'), $content);

		# Fallback in case those didn't happen
		$content = preg_replace_callback(
			'|'.$sgf_pattern.'|si',
			array(&$this, 'embed_sgf'), $content);

		return $content;
	} # }}}

}

$wpeidogo_plugin =& new WpEidoGoPlugin();
# TODO: Useful error handling if PHP or WordPress versions are too old

# vim:noet:ts=4
