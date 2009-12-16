<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered blog with the EidoGo SGF viewer and editor.
Version: 0.8
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

class WpEidoGoPlugin { # {{{

	var $sgf_count = 0;
	var $sgf_prepared_markup = array();
	var $sgf_mime_type = 'application/x-go-sgf';

	/* Initialization */
	function WpEidoGoPlugin() { # {{{
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

		# Support for SGF files in media library
		add_filter('upload_mimes', array(&$this, 'sgf_mimetypes'));
		add_filter('post_mime_types', array(&$this, 'add_media_tab'));
		add_filter('ext2type', array(&$this, 'sgf_extension')); # ?
		add_filter('attachment_fields_to_edit', array(&$this, 'sgf_media_form'), 10, 2);
		add_filter('media_send_to_editor', array(&$this, 'sgf_send_to_editor'), 10, 3);
	} # }}}

	/* HTML header */
	function eidogo_head_tags() { # {{{
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

	/* Media library */
	function sgf_media_form($form_fields, $post=null) { # {{{
		if ($post->post_mime_type != $this->sgf_mime_type)
			return $form_fields;

		$form_fields['embed_method'] = array(
			'label' => __('Embed Method'),
			'input' => 'html',
			'html' => '
				<input type="hidden" name="attachments['.$post->ID.'][mime_type]" value="'.htmlspecialchars($post->post_mime_type).'" />
				<input type="hidden" name="attachments['.$post->ID.'][src]" value="'.htmlspecialchars($post->guid).'" />
				<input type="radio" name="attachments['.$post->ID.'][embed_method]"
					id="wpeidogo-embed_method-iframe-'.$post->ID.'" value="iframe" checked="checked"
					/><label for="wpeidogo-embed_method-iframe-'.$post->ID.'">Iframe (recommended)</label>
				<input type="radio" name="attachments['.$post->ID.'][embed_method]"
					id="wpeidogo-embed_method-inline-'.$post->ID.'" value="inline"
					/><label for="wpeidogo-embed_method-inline-'.$post->ID.'">Inline</label>',
		);

		$form_fields['eidogo_theme'] = array(
			'label' => __('Theme'),
			'input' => 'html',
			'html' => '
				<input type="radio" name="attachments['.$post->ID.'][eidogo_theme]"
					id="wpeidogo-theme-compact-'.$post->ID.'" value="compact" checked="checked"
					/><label for="wpeidogo-theme-compact-'.$post->ID.'">Compact (default)</label>
				<input type="radio" name="attachments['.$post->ID.'][eidogo_theme]"
					id="wpeidogo-theme-full-'.$post->ID.'" value="full"
					/><label for="wpeidogo-theme-full-'.$post->ID.'">Full</label>
				<input type="radio" name="attachments['.$post->ID.'][eidogo_theme]"
					id="wpeidogo-theme-problem-'.$post->ID.'" value="problem"
					/><label for="wpeidogo-theme-problem-'.$post->ID.'">Problem</label>',
		);

		unset($form_fields['post_title']);
		unset($form_fields['post_content']);

		return $form_fields;
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
		if ($post['src'])
			$params .= ' sgfUrl="'.htmlspecialchars($post['src']).'"';
		if ($theme && $theme != 'compact')
			$params .= ' theme="'.htmlspecialchars($theme).'"';
		if ($post['post_excerpt'])
			$params .= ' caption="'.htmlspecialchars($post['post_excerpt']).'"';
		if ($post['url'])
			$params .= ' href="'.htmlspecialchars($post['url']).'"';
		return '[sgf'.$params.'][/sgf]';
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
		$wpu = WP_PLUGIN_URL;

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
			$iframe = json_encode('<iframe src="'.$wpu.'/eidogo-for-wordpress/iframe-player.html#'.$this->sgf_count.
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

} # }}}

$wpeidogo_plugin =& new WpEidoGoPlugin();
# TODO: Useful error handling if PHP or WordPress versions are too old

# vim:noet:ts=4
