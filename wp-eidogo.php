<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered blog with the EidoGo SGF viewer and editor.
Version: 0.8.5
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

class WpEidoGoRandomProblemWidget extends WP_Widget {

	function WpEidoGoRandomProblemWidget() { # {{{
		$widget_ops = array(
			'classname' => 'widget-random-go-problem',
			'description' => __('Show a random go problem from your media library'),
		);
		$this->WP_Widget('random_go_problem', __('Random Go Problem'), $widget_ops);
	} # }}}

	function widget($args, $instance) { # {{{
		$title = apply_filters('widget_title',
			(empty($instance['title']) ? __('Random Go Problem') : $instance['title']));

		extract($args);

		# This could be made more efficient rather than looping like this
		$query_args = array(
			'orderby' => 'rand',
			'meta_key' => '_wpeidogo_theme',
			'meta_value' => 'problem',
			'post_type' => 'attachment',
		);

		$problem = __('No suitable problems were found.');
		$posts = get_posts($query_args);
		foreach ($posts as $post) {

			$custom = get_post_custom($post->ID);
			if (!$custom['_wpeidogo_sgf_metadata'] || !$custom['_wpeidogo_sgf_metadata'][0])
				continue;
			$width = $custom['_wpeidogo_sgf_metadata'][0]['pattern_width'];
			$height = $custom['_wpeidogo_sgf_metadata'][0]['pattern_height'];

			if ($instance['max_width'])
				if (!$width || $width > $instance['max_width'])
					continue;

			if ($instance['max_height'])
				if (!$height || $height > $instance['max_height'])
					continue;

			$problem = wpeidogo_embed_attachment($post, null, null, '');
			break;
		}

		echo $before_widget . $before_title . $title . $after_title . $problem . $after_widget;

		wp_reset_query();
	} # }}}

	function update($new_instance, $old_instance) { # {{{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['max_width'] = (int)$new_instance['max_width'];
		$instance['max_height'] = (int)$new_instance['max_height'];

		return $instance;
	} # }}}

	function form($instance) { # {{{
		$title = attribute_escape($instance['title']);

		if (!$max_width = (int)$instance['max_width'])
			$max_width = '';

		if (!$max_height = (int)$instance['max_height'])
			$max_height = '';

		$title_id = $this->get_field_id('title');
		$max_width_id = $this->get_field_id('max_width');
		$max_height_id = $this->get_field_id('max_height');

		$title_name = $this->get_field_name('title');
		$max_width_name = $this->get_field_name('max_width');
		$max_height_name = $this->get_field_name('max_height');

		$title_label = __('Title:');
		$max_width_label = __('Max Width:');
		$max_height_label = __('Max Height:');

		echo <<<html
			<p><label for="{$title_id}">{$title_label}
				<input id="{$title_id}" name="{$title_name}" value="{$title}" class="widefat" /></label></p>
			<p><label for="{$max_width_id}">{$max_width_label}</label>
				<input id="{$max_width_id}" name="{$max_width_name}" value="{$max_width}" /></label></p>
			<p><label for="{$max_height_id}">{$max_height_label}</label>
				<input id="{$max_height_id}" name="{$max_height_name}" value="{$max_height}" /></label></p>
html;
	} # }}}

}

class WpEidoGoPlugin {

	var $sgf_count = 0;
	var $sgf_prepared_markup = array();
	var $sgf_mime_type = 'application/x-go-sgf';

	/* Initialization */
	function WpEidoGoPlugin() { # {{{
		$this->plugin_url = WP_PLUGIN_URL . '/eidogo-for-wordpress';
		$this->plugin_dir = WP_PLUGIN_DIR . '/eidogo-for-wordpress';
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

		add_filter('the_content_rss',  array(&$this, 'prepare_markup'), 9);
		add_filter('the_excerpt_rss',  array(&$this, 'prepare_markup'), 9);
		add_filter('comment_text_rss', array(&$this, 'prepare_markup'), 9);
		add_filter('the_content_rss',  array(&$this, 'embed_markup'), 99);
		add_filter('the_excerpt_rss',  array(&$this, 'embed_markup'), 99);
		add_filter('comment_text_rss', array(&$this, 'embed_markup'), 99);

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

		# For the random problem widget
		add_action('widgets_init', array(&$this, 'register_widgets'));
	} # }}}

	function register_widgets() { # {{{
		register_widget('WpEidoGoRandomProblemWidget');
	} # }}}

	/* HTML header */
	function eidogo_head_tags() { # {{{
		echo <<<html
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/wp-eidogo.css" />
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/eidogo/player/css/player.css" />
		<script type="text/javascript">
			var broken_browser = false;
		</script>
		<!--[if lt IE 7]>
		<script type="text/javascript">
			broken_browser = true;
		</script>
		<![endif]-->
		<script type="text/javascript" src="{$this->plugin_url}/eidogo/player/js/all.compressed.js"></script>
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
			$oc = " onchange='return wpeidogo_theme_change($post_id);' onclick='return wpeidogo_theme_change($post_id);'";
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

	function clean_sgf_text($val) { # {{{
		# Normalize linebreaks
		$val = str_replace("\r\n", "\n", $val);
		$val = str_replace("\n\r", "\n", $val);
		$val = str_replace("\r", "\n", $val);

		# remove soft linebreaks
		$val = str_replace("\\\n", "", $val);

		# Handle escaping
		$val = preg_replace('/\\\\(.)/s', '$1', $val);

		# TODO: Convert non-newline whitespace to space
		# TODO: Handle encoding?

		return $val;
	} # }}}

	function clean_sgf_simpletext($val) { # {{{
		$val = $this->clean_sgf_text($val);
		$val = trim(preg_replace('/\s+/', ' ', $val));
		return $val;
	} # }}}

	function clean_sgf_composed($val) { # {{{
		$parts = split(':', $val);
		$ret = array();
		$current = null;
		foreach ($parts as $part) {
			if (is_null($current)) {
				$current = $part;
			} else {
				$endslashes = strlen($current) - strlen(rtrim($current, '\\'));
				# Check to see if the current element ends in an odd number of backslashes
				# (indicating that the : is escaped and is preceeded by 0 or more escaped
				# backslashes), and if so, join up the next split element
				if ($endslashes % 2) {
					$current .= $part;
				} else {
					$ret[] = $current;
					$current = $part;
				}
			}
		}
		if (!is_null($current))
			$ret[] = $current;
		return $ret;
	} # }}}

	function is_pass($point, $size) { # {{{
		# Empty ponits area always passes
		if ($point == '')
			return true;
		# In older SGF versions 'tt' is a pass if the board is 19x19 or smaller
		if ($point != 'tt')
			return false;
		# This is necessary because SZ is a composed value to allow for rectangular boards
		foreach ($size as $dim)
			if ($dim > 19)
				return false;
		return true;
	} # }}}

	function get_sgf_metadata($post) { # {{{
		$fn = get_attached_file($post['ID']);
		$meta = array();
		$contents = file_get_contents($fn, 0, null, 0, 65536);
		$matches = array();

		# These are all game-info or root type nodes, and none are list types.
		# The parsing method will have to be rewritten if list types are 
		# admitted here.
		# NOTE: This only really handles one occurance of each of these node
		# types and therefore may not work that well on game collections
		$game_attrs = array(
			'AN' => array('simpletext', __('Annotated By')),
			'AP' => array('simpletext-composed', __('Application')),
			'BR' => array('simpletext', __('Black Rank')),
			'BT' => array('simpletext', __('Black Team')),
			'CA' => array('simpletext', __('Character Set')),
			'CP' => array('simpletext', __('Copyright')),
			'DT' => array('simpletext', __('Dates Played')),
			'EV' => array('simpletext', __('Event')),
			'FF' => array('number',     __('SGF Version')),
			'GC' => array('text',       __('Game Comment')),
			'GM' => array('number',     __('Game')),
			'GN' => array('simpletext', __('Game Name')),
			'HA' => array('number',     __('Handicap')),
			'KM' => array('real',       __('Komi')),
			'ON' => array('simpletext', __('Opening')),
			'OT' => array('simpletext', __('Overtime')),
			'PB' => array('simpletext', __('Black Player')),
			'PC' => array('simpletext', __('Place')),
			'PW' => array('simpletext', __('White Player')),
			'RE' => array('simpletext', __('Result')),
			'RO' => array('simpletext', __('Round')),
			'RU' => array('simpletext', __('Rules')),
			'SO' => array('simpletext', __('Source')),
			'ST' => array('number',     __('Variations Style')),
			'SZ' => array('number-composed', __('Board Size')),
			'TM' => array('real',       __('Time Limit')),
			'US' => array('simpletext', __('Transcriber')),
			'WR' => array('simpletext', __('White Rank')),
			'WT' => array('simpletext', __('White Team')),
		);
		preg_match_all('/('.join('|', array_keys($game_attrs)).')\[([^\]]+|\\\])*\]/',
				$contents, $matches, PREG_SET_ORDER);
		foreach ($matches as $m) {
			list($type, $label) = $game_attrs[$m[1]];
			$val = $m[2];
			switch ($type) {
				case 'text':
					$val = $this->clean_sgf_text($val);
					break;
				case 'simpletext':
					$val = $this->clean_sgf_simpletext($val);
					break;
				case 'simpletext-composed':
					$parts = $this->clean_sgf_composed($val);
					$val = array();
					foreach ($parts as $v)
						$val[] = $this->clean_sgf_simpletext($v);
					break;
				case 'number':
					$val = (int)intval(trim($val));
					break;
				case 'number-composed':
					$parts = $this->clean_sgf_composed($val);
					$val = array();
					foreach ($parts as $v)
						$val[] = (int)intval(trim($v));
					break;
				case 'real':
					$val = (float)floatval(trim($val));
					break;
			}
			$meta[$m[1]] = $val;
		}

		# Only process go SGF files
		if (!$meta['GM'] || $meta['GM'] != 1)
			return $meta;

		# Searches same set of SGF attributes as EidoGo does for problem mode
		preg_match_all('/(W|B|AW|AB|LB)((\[([a-z]{2}(:[a-z]{2})?)\]\s*)+)/s', $contents, $matches);
		$l = $r = $b = $t = null;
		foreach ($matches[2] as $pointlist) {
			$pointlist = trim($pointlist);
			$points = preg_split('/(\]\s*\[|:)/', substr($pointlist, 1, strlen($pointlist)-2));
			foreach ($points as $p) {
				if ($this->is_pass($p, $meta['SZ']))
					continue; # skip passes
				$x = ord($p[0]) - ord('a');
				$y = ord($p[1]) - ord('a');
				if (is_null($l) || $x < $l)
					$l = $x;
				if (is_null($r) || $x > $r)
					$r = $x;
				if (is_null($t) || $y < $t)
					$t = $y;
				if (is_null($b) || $y > $b)
					$b = $y;
			}
		}

		if (is_null($l)) {
			$meta['pattern_width'] = 0;
			$meta['pattern_height'] = 0;
		} else {
			$meta['pattern_width'] = $r-$l+1;
			$meta['pattern_height'] = $b-$t+1;
		}

		return $meta;
	} # }}}

	function save_sgf_info($post, $input) { # {{{
		if (!$input['mime_type'] || $input['mime_type'] != $this->sgf_mime_type)
			return $post;

		if (!$post['ID'])
			return $post;

		if (!current_user_can('edit_post', $post['ID']))
			return $post;

		update_post_meta($post['ID'], '_wpeidogo_theme', $input['eidogo_theme']);
		update_post_meta($post['ID'], '_wpeidogo_embed_method', $input['embed_method']);
		update_post_meta($post['ID'], '_wpeidogo_problem_color', $input['problem_color']);
		update_post_meta($post['ID'], '_wpeidogo_sgf_metadata', $this->get_sgf_metadata($post));

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

	function embed_static($params, $sgf_data) { # {{{
		global $wp_query;

		if ($params['caption'])
			$fallback = "\n\n[Embedded SGF File: ".htmlspecialchars($params['caption'])."]\n\n";
		else
			$fallback = "\n\n[Embedded SGF File]\n\n";

		if ($wp_query && $wp_query->post && $wp_query->post->ID)
			$uniq = $wp_query->post->ID;
		else
			$uniq = 'X';
		$uniq .= '-' . md5(serialize($params) . serialize($sgf_data));

		$svg_file = $this->plugin_dir . "/static/$uniq.svg";
		$png_file = $this->plugin_dir . "/static/$uniq.png";
		$png_url  = $this->plugin_url . "/static/$uniq.png";

		# Figure out where we're really getting the SGF data from
		$sgfurl = $params['sgfurl'];
		if ($sgfurl) {
			if (substr($sgfurl, 0, strlen(WP_CONTENT_URL)) == WP_CONTENT_URL) {
				# absolute URL, but local
				$sgf_file = WP_CONTENT_DIR . substr($sgfurl, strlen(WP_CONTENT_URL));
			} elseif (preg_match('!https?://!', $sgfurl)) {
				# remote
				$sgf_file = '-';
				$sgf_data = file_get_contents($sgfurl, 0, null, -1, 65536);
			} elseif (substr($sgfurl, 0, 1) == '/') {
				# relative URL, local
				$sgf_file = ABSPATH . ltrim($sgfurl, '/');
			} else {
				# no idea
				return $fallback;
			}
		} else {
			# using sgf data
			$sgf_file = '-';
		}

		# Avoid errors and possible nasties
		if ($sgf_file != '-' && !is_readable($sgf_file))
			return $fallback;

		# Check to see if the cached version is OK
		if (file_exists($png_file) && is_readable($png_file)) {
			$ok_cache = True;

			$file_check = array(
				$this->plugin_dir . '/wp-eidogo.php',
				$this->plugin_dir . '/sgf2svg/sgfboard.py',
				$this->plugin_dir . '/sgf2svg/sgf2svg',
				$svg_file,
			);
			if ($sgf_file != '-')
				$file_check[] = $sgf_file;

			$png_mtime = filemtime($png_file);
			foreach ($file_check as $fc) {
				if (!file_exists($fc) || !is_readable($fc) || $png_mtime < filemtime($fc)) {
					$ok_cache = false;
					break;
				}
			}

			if ($ok_cache)
				return $this->embed_image($params, $png_file, $png_url);
		}

		# Create sgf file command
		$cmd = $this->plugin_dir . '/sgf2svg/sgf2svg -o ' . escapeshellarg($svg_file);
		if ($params['theme'] == 'problem')
			$cmd .= ' --crop-whole-tree';
		if (isset($params['movenumber']))
			$cmd .= ' --move-number=' . escapeshellarg($params['movenumber']);
		elseif ($params['theme'] != 'problem')
			$cmd .= ' --move-number=1000'; # for static images, jump to end of game by default
		$cmd .= ' ' . escapeshellarg($sgf_file);

		# Run the command
		$dspec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$process = proc_open($cmd, $dspec, $pipes);
		if (!$process)
			return $fallback;
		if ($sgf_file == '-')
			fwrite($pipes[0], $sgf_data);
		fclose($pipes[0]);
		$stdout_result = stream_get_contents($pipes[1]);
		$stderr_result = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		if (!file_exists($svg_file) || !is_readable($svg_file))
			return $fallback;

		$cmd = 'convert -quiet ' . escapeshellarg($svg_file) . ' ' . escapeshellarg($png_file);
		@system($cmd);

		if (!file_exists($png_file) || !is_readable($png_file))
			return $fallback;

		# cleanup old svg and png files that have not been accessed in a long time?
		# or perhaps add and admin screen option to clear the cache

		return $this->embed_image($params, $png_file, $png_url);

	} # }}}

	function embed_image($params, $filename, $url) { # {{{
		$info = getimagesize($filename);
		$tag = '<img src="'.$url.'" width="'.$info[0].'" height="'.$info[1].'" alt="SGF Diagram"' .
			($params['caption'] ? '' : ' class="'.$params['class'].'"') . ' />';

		if ($params['href'])
			$tag = '<a href="'.htmlspecialchars($params['href']).'">'.$tag.'</a>';

		if (!$params['caption'])
			return $tag;

		return "\n\n".'[caption id="" align="'.htmlspecialchars($params['class']).
			'" width="'.$info[0].'" caption="'.htmlspecialchars($params['caption']).'"]'.$tag."[/caption]\n\n";
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
		# TODO: Work for sgfUrl things

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

		if ($params['loadpath'] && preg_match('/^\s*\d[\d,\s]+\d\s*$/', $params['loadpath']))
			$params['loadpath'] = preg_split('/\s*,\s*/', trim($params['loadpath']));

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

		if (is_feed() || $params['image'])
			return $this->embed_static($params, $sgf_data);
		else
			return "\n\n[sgfPrepared id=\"".($this->sgf_count++)."\"]\n\n";

	} # }}}

	function embed_sgf($matches) { # {{{
		list($whole_tag, $id) = $matches;

		if (is_feed())
			return '<p>[Embedded SGF File]</p>';
		else
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

	function embed_attachment($post, $class=null, $caption=null, $href=null, $theme=null, $method=null) { # {{{
		$meta = get_post_custom($post->ID);

		if (is_null($theme))
			$theme = ($meta['_wpeidogo_theme'] ? $meta['_wpeidogo_theme'][0] : 'compact');
		if (is_null($method))
			$method = ($meta['_wpeidogo_embed_method'] ? $meta['_wpeidogo_embed_method'][0] : 'iframe');
		if ($method && $method != 'iframe' && $theme && $theme != 'problem')
			$theme .= '-' . $method;

		$problem_color = ($meta['_wpeidogo_problem_color'] ? $meta['_wpeidogo_problem_color'][0] : null);

		if (is_null($caption))
			$caption = $post->post_excerpt;
		if (is_null($href))
			$href = $post->guid;

		$params = array('sgfUrl="'.$post->guid.'"');
		if ($theme != 'compact')
			$params[] = 'theme="'.$theme.'"';
		if ($problem_color && $theme == 'problem' && strtolower($problem_color) != 'auto')
			$params[] = 'problemColor="'.$problem_color.'"';
		if ($caption)
			$params[] = 'caption="'.htmlspecialchars($caption).'"';
		if ($href)
			$params[] = 'href="'.htmlspecialchars($href).'"';
		if ($class)
			$params[] = 'class="'.htmlspecialchars($class).'"';
		$params = join(' ', $params);

		$content = "[sgf $params][/sgf]";
		return $this->embed_markup($this->prepare_markup($content));
	} # }}}

}

$wpeidogo_plugin =& new WpEidoGoPlugin();

function wpeidogo_embed_attachment($post, $class=null, $caption=null, $href=null, $theme=null, $method=null) { # {{{
	global $wpeidogo_plugin;
	return $wpeidogo_plugin->embed_attachment($post, $class, $caption, $href, $theme, $method);
} # }}}

# TODO: Useful error handling if PHP or WordPress versions are too old

# vim:noet:ts=4
