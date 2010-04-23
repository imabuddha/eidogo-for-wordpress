<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered blog with the EidoGo SGF viewer and editor.
Version: 0.8.9
Author: Thomas Schumm
Author URI: http://www.fortmyersgo.org/
*/

/*	Copyright © 2009-2010 Thomas Schumm <phong@phong.org>

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

class WpEidoGoRandomProblemWidget extends WP_Widget { # {{{

	function WpEidoGoRandomProblemWidget() { # {{{
		$widget_ops = array(
			'classname' => 'widget-random-go-problem',
			'description' => __('A random go problem from your media library'),
		);
		$this->WP_Widget('random_go_problem', __('Random Go Problem'), $widget_ops);
	} # }}}

	function widget($args, $instance) { # {{{
		global $wpdb;

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

		$joins = '';
		$where = '';

		foreach (array('width', 'height') as $key) {
			if (!$instance['max_'.$key])
				continue;
			$max_val = (int)$instance['max_'.$key];
			$joins .= "INNER JOIN $wpdb->postmeta AS pm_$key ON pm_$key.post_id = p.ID AND pm_$key.meta_key = '_wpeidogo_pattern_$key'\n";
			$where .= "AND pm_$key.meta_value <= $max_val\n";
		}

		foreach (array('category', 'difficulty') as $tax) {
			$ptax = 'problem_'.$tax;
			if (!is_array($instance[$ptax]) || $instance[$ptax]['all'])
				continue;
			$joins .= "INNER JOIN $wpdb->term_relationships AS tr_$tax ON tr_$tax.object_id = p.ID\n";
			$joins .= "INNER JOIN $wpdb->term_taxonomy AS tt_$tax ON tt_$tax.term_taxonomy_id = tr_$tax.term_taxonomy_id AND tt_$tax.taxonomy = '$ptax'\n";
			$ids = array();
			foreach ($instance[$ptax] as $term_id => $use)
				if ($use) $ids[] = (int)$term_id;
			$where .= "AND tt_$tax.term_id in (" . join(', ', $ids) . ")\n";
		}

		if (!get_option('wpeidogo_show_unpublished_problems')) {
			$joins .= "INNER JOIN $wpdb->posts AS p2 on p2.ID = p.post_parent\n";
			$where .= "AND (p.post_status = 'publish' OR (p.post_status = 'inherit' AND p2.post_status = 'publish'))\n";
		}

		$query = "
			SELECT DISTINCT p.ID, p.post_excerpt, p.guid
			FROM $wpdb->posts AS p
			INNER JOIN $wpdb->postmeta pmt ON pmt.post_id = p.ID AND pmt.meta_key = '_wpeidogo_theme'
			$joins
			WHERE p.post_type = 'attachment'
				AND p.post_mime_type = 'application/x-go-sgf'
				AND pmt.meta_value = 'problem'
				$where
			ORDER BY rand()
			LIMIT 1
			";

		$posts = $wpdb->get_results($query);
		if (sizeof($posts)) {
			$problem = wpeidogo_embed_attachment($posts[0], null, '', '');
			$cat = get_the_term_list($posts[0]->ID, 'problem_category', '', ', ', '');
			$dif = get_the_term_list($posts[0]->ID, 'problem_difficulty', '', ', ', '');
			$problem .= <<<html
			<p class='problem-info'>
				<span class='problem-category'>$cat</span>
				<span class='problem-difficulty'>$dif</span>
			</p>
html;
		} else {
			$problem = '<p>' . __('No suitable problems were found.') . '</p>';
		}
		#$problem = '<pre class="debug" style="position: relative; width: 5000px;">'.htmlspecialchars($query).'</pre>';
		/*
		$posts = get_posts($query_args);
		foreach ($posts as $post) {

			$custom = get_post_custom($post->ID);
			if (!$custom['_wpeidogo_pattern_width'] || !$custom['_wpeidogo_pattern_width'][0])
				continue;
			if (!$custom['_wpeidogo_pattern_height'] || !$custom['_wpeidogo_pattern_height'][0])
				continue;
			$width = $custom['_wpeidogo_pattern_width'][0];
			$height = $custom['_wpeidogo_pattern_height'][0];

			if ($instance['max_width'])
				if (!$width || $width > $instance['max_width'])
					continue;

			if ($instance['max_height'])
				if (!$height || $height > $instance['max_height'])
					continue;

			$problem = wpeidogo_embed_attachment($post, null, null, '');
			break;
		}
		*/

		echo $before_widget . $before_title . $title . $after_title . $problem . $after_widget;

		wp_reset_query();
	} # }}}

	function update($new_instance, $old_instance) { # {{{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['max_width'] = (int)$new_instance['max_width'];
		$instance['max_height'] = (int)$new_instance['max_height'];
		unset($instance['exclude_unpublished']); # unused option
		foreach (array('problem_category', 'problem_difficulty') as $taxonomy) {
			$instance[$taxonomy] = array('all' => ($new_instance["$taxonomy-all"] ? 1 : 0));
			$terms = get_terms($taxonomy, array('hide_empty' => false, 'fields' => 'ids'));
			foreach ($terms as $term_id)
				if ($new_instance["$taxonomy-$term_id"])
					$instance[$taxonomy][$term_id] = 1;
		}

		return $instance;
	} # }}}

	function draw_simple_field($name, $label, $value, $type='text') { # {{{
		$f_name = $this->get_field_name($name);
		$f_id = $this->get_field_id($name);
		$value = attribute_escape($value);
		$label = htmlspecialchars($label);
		if ($type == 'text') {
			return <<<html
			<p><label for="{$f_id}">{$label}
				<input id="{$f_id}" name="{$f_name}" value="{$value}" class="widefat" /></p>
html;
		} elseif ($type == 'checkbox') {
			$checked = ($value ? 'checked="checked"' : '');
			return <<<html
			<input id="{$f_id}" name="{$f_name}" type="{$type}" {$checked} value="1" />
				<label for="{$f_id}">{$label}</label>
html;
		}
	} # }}}

	function terms_checkboxes($title, $all, $taxonomy, $current_terms) { # {{{
		$checks = array();
		$terms = get_terms($taxonomy, array('hide_empty' => false));

		$name = "{$taxonomy}-all";
		$checked = ($current_terms['all'] ? 1 : 0);
		$checks[] = $this->draw_simple_field($name, $all, $checked, 'checkbox');

		foreach ($terms as $t) {
			$name = "{$taxonomy}-{$t->term_id}";
			$checked = ($current_terms[$t->term_id] ? 1 : 0);
			$checks[] = $this->draw_simple_field($name, $t->name, $checked, 'checkbox');
		}

		# TODO: Add some script to disable checkboxes when "all" is checked

		return '<h5 class="wpeidogo_checkgroup_title">' . $title . '</h5>' .
			'<p class="wpeidogo_checkgroup">' . join("<br />", $checks) . '</p>';
	} # }}}

	function form($instance) { # {{{
		if (!$max_width = (int)$instance['max_width'])
			$max_width = '';
		if (!$max_height = (int)$instance['max_height'])
			$max_height = '';

		if (!$problem_category = $instance['problem_category'])
			$problem_category = array('all' => 1);
		if (!$problem_difficulty = $instance['problem_difficulty'])
			$problem_difficulty = array('all' => 1);

		echo $this->draw_simple_field('title', __('Title:'), $instance['title']) .
			$this->draw_simple_field('max_width', __('Max Width:'), $max_width) .
			$this->draw_simple_field('max_height', __('Max Height:'), $max_height) .
			$this->terms_checkboxes(__('Problem Category:'), __('All Categories'),
				'problem_category', $problem_category) .
			$this->terms_checkboxes(__('Problem Difficulty:'), __('All Difficulties'),
				'problem_difficulty', $problem_difficulty);
	} # }}}

} # }}}

class WpEidoGoProblemBrowser extends WP_Widget { # {{{

	function WpEidoGoProblemBrowser() { # {{{
		$widget_ops = array(
			'classname' => 'widget-go-problem-browser',
			'description' => __('Browse go problems by category and difficulty'),
		);
		$this->WP_Widget('go_problem_browser', __('Go Problem Browser'), $widget_ops);
	} # }}}

	function widget($args, $instance) { # {{{
		$title = apply_filters('widget_title',
			(empty($instance['title']) ? __('Go Problem Browser') : $instance['title']));
		global $wp_taxonomies;

		extract($args);

		$clouds =
			'<h3>' . $wp_taxonomies['problem_difficulty']->label . '</h3>' .
			wp_tag_cloud(array(
				'taxonomy' => 'problem_difficulty',
				'format' => 'list',
				'echo' => false,
				'unit' => '%',
				'smallest' => 100,
				'largest' => 100,
				'fix_counts' => true,
				)) .
			'<h3>' . $wp_taxonomies['problem_category']->label . '</h3>' .
			wp_tag_cloud(array(
				'taxonomy' => 'problem_category',
				'format' => 'list',
				'echo' => false,
				'unit' => '%',
				'smallest' => 100,
				'largest' => 100,
				'fix_counts' => true,
				));

		echo $before_widget . $before_title . $title . $after_title . $clouds . $after_widget;

	} # }}}

	function update($new_instance, $old_instance) { # {{{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];

		return $instance;
	} # }}}

	function form($instance) { # {{{
		$title = attribute_escape($instance['title']);

		$title_id = $this->get_field_id('title');
		$title_name = $this->get_field_name('title');
		$title_label = __('Title:');

		echo <<<html
			<p><label for="{$title_id}">{$title_label}
				<input id="{$title_id}" name="{$title_name}" value="{$title}" class="widefat" /></label></p>
html;
	} # }}}

} # }}}

class WpEidoGoGameBrowser extends WP_Widget { # {{{

	function WpEidoGoGameBrowser() { # {{{
		$widget_ops = array(
			'classname' => 'widget-go-game-browser',
			'description' => __('Browse go games by category'),
		);
		$this->WP_Widget('go_game_browser', __('Go Game Browser'), $widget_ops);
	} # }}}

	function widget($args, $instance) { # {{{
		$title = apply_filters('widget_title',
			(empty($instance['title']) ? __('Go Game Browser') : $instance['title']));
		global $wp_taxonomies;

		extract($args);

		$clouds =
			'<h3>' . $wp_taxonomies['game_category']->label . '</h3>' .
			wp_tag_cloud(array(
				'taxonomy' => 'game_category',
				'format' => 'list',
				'echo' => false,
				'unit' => '%',
				'smallest' => 100,
				'largest' => 100,
				'fix_counts' => true,
				));

		echo $before_widget . $before_title . $title . $after_title . $clouds . $after_widget;

	} # }}}

	function update($new_instance, $old_instance) { # {{{
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];

		return $instance;
	} # }}}

	function form($instance) { # {{{
		$title = attribute_escape($instance['title']);

		$title_id = $this->get_field_id('title');
		$title_name = $this->get_field_name('title');
		$title_label = __('Title:');

		echo <<<html
			<p><label for="{$title_id}">{$title_label}
				<input id="{$title_id}" name="{$title_name}" value="{$title}" class="widefat" /></label></p>
html;
	} # }}}

} # }}}

class WpEidoGoPlugin {

	var $sgf_count = 0;
	var $sgf_prepared_markup = array();
	var $sgf_mime_type = 'application/x-go-sgf';
	var $fudge_unpublished = false;

	/* Initialization */
	function WpEidoGoPlugin() { # {{{
		$this->plugin_url = WP_PLUGIN_URL . '/eidogo-for-wordpress';
		$this->plugin_dir = WP_PLUGIN_DIR . '/eidogo-for-wordpress';
		$this->setup_hooks();
	} # }}}

	function setup_options() { # {{{
		add_settings_section('wpeidogo_sgf_options', __('SGF File Handling'),
			array(&$this, 'sgf_options_section'), 'media');
		add_settings_field('wpeidogo_show_unpublished_problems', 'Unpublished Problems',
			array(&$this, 'show_unpublished_problems_option'), 'media', 'wpeidogo_sgf_options');
		register_setting('media', 'wpeidogo_show_unpublished_problems');
	} # }}}

	function sgf_options_section() { # {{{
		echo <<<html
		<p>EidoGo for WordPress will normally only select problems that have
		been published (e.g. attached to a page or post) for the random problem
		widget and only show published problems when browsing problems by
		category or difficulty. This option will let you see unpublished and
		unattached problem (useful if you have uploaded a bunch of problem
		files and don't really want to write a post just to get them to
		display).</p>
html;
	} # }}}

	function show_unpublished_problems_option() { # {{{
		$checked = '';
		if (get_option('wpeidogo_show_unpublished_problems'))
			$checked = 'checked="checked"';
		echo <<<html
		<label for="wpeidogo_show_unpublished_problems"><input type="checkbox" value="1"
			{$checked} name="wpeidogo_show_unpublished_problems" id="wpeidogo_show_unpublished_problems" />
			Show Unpublished Problems</label>
html;
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

		# For the random problem and problem and game browser widgets
		add_action('widgets_init', array(&$this, 'register_widgets'));
		add_filter('get_terms', array(&$this, 'fix_term_count'), 10, 3);

		# For admin menu options
		add_action('admin_menu', array(&$this, 'add_admin_menu_options'));
		add_action('admin_init', array(&$this, 'setup_options'));

		# When the plugin is activated
		register_activation_hook(__FILE__, array(&$this, 'activate_plugin'));

		if (get_option('wpeidogo_show_unpublished_problems'))
			add_filter('posts_where', array(&$this, 'include_unpublished_problems'), 10, 3);
	} # }}}

	function include_unpublished_problems($where) { # {{{
		global $wpdb;
		if (!$this->fudge_unpublished)
			return $where;

		$where = str_replace("({$wpdb->posts}.post_status = 'publish')",
			"({$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_type = 'attachment')",
			$where);

		$this->fudge_unpublished = false;
		return $where;
	} # }}}

	function fix_term_count($terms, $taxonomies, $args) { # {{{
		# Currently, when get_terms is used with a custom taxonomy, it returns
		# a count value that includes unpublished content. So wp_tag_cloud will
		# produce unexpected results. The tooltip will show the count including
		# unpublished content, but the page it links to will only show published
		# items. If a term is only attached to unpublished content, then it'll
		# be a 404, which is even worse. So this filter does its own query to
		# get an accurate count and filters things accordingly.

		# This may be unnecessary in a future WordPress version.

		# Check to see if "display unpublished option is on
		if (get_option('wpeidogo_show_unpublished_problems') && sizeof($taxonomies) == 1 &&
				($taxonomies[0] == 'problem_category' || $taxonomies[0] == 'problem_difficulty')) {
			$this->fudge_unpublished = true;
			return $terms;
		}

		if (!$args['fix_counts'])
			return $terms;

		global $wpdb;

		$term_ids = array();
		foreach ($terms as $term)
			$term_ids[] = $term->term_id;

		$posts_where = "AND $wpdb->posts.post_type != 'revision' AND (($wpdb->posts.post_status = 'publish') OR ($wpdb->posts.post_status = 'inherit' AND (p2.post_status = 'publish')))";
		$posts_where = apply_filters('posts_where', $posts_where);

		$query = "
			SELECT COUNT(1) as count, t.term_id
				FROM $wpdb->terms AS t
				INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
				INNER JOIN $wpdb->term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN $wpdb->posts ON $wpdb->posts.ID = tr.object_id
				LEFT JOIN $wpdb->posts AS p2 on $wpdb->posts.post_parent = p2.ID
				WHERE t.term_id IN (".join(', ', $term_ids).")
					$posts_where
				GROUP BY t.name, t.term_id, tt.parent
				";

		$counts = $wpdb->get_results($query);
		$_terms = array();
		$_counts = array();
		foreach ($counts as $c)
			$_counts[$c->term_id] = $c->count;

		foreach ($terms as $t) {
			if (!$_counts[$t->term_id]) {
				if ($args['hide_empty'])
					continue;
				$t->count = 0;
			} else {
				$t->count = $_counts[$t->term_id];
			}
			$_terms[] = $t;
		}

		# TODO: Worry about hierarchy and padding parent counts or just be
		# satisifed knowing that I didn't set up any of my taxonomies as
		# hierarchical?

		return $_terms;
	} # }}}

	function add_admin_menu_options() { # {{{
		add_submenu_page('upload.php', __('Game Categories'), __('Game Categories'), 'manage_categories',
			'edit-tags.php?taxonomy=game_category');
		add_submenu_page('upload.php', __('Problem Categories'), __('Problem Categories'), 'manage_categories',
			'edit-tags.php?taxonomy=problem_category');
		add_submenu_page('upload.php', __('Problem Difficulties'), __('Problem Difficulties'), 'manage_categories',
			'edit-tags.php?taxonomy=problem_difficulty');
	} # }}}

	function register_widgets() { # {{{
		# Weirdly, I seem to have to call this here...
		# TODO: Move this because it's stupid here
		$this->register_taxonomies();

		# Register widgets
		register_widget('WpEidoGoRandomProblemWidget');
		register_widget('WpEidoGoProblemBrowser');
		register_widget('WpEidoGoGameBrowser');
	} # }}}

	function register_taxonomies() { # {{{
		# taxonomies for problems and games
		register_taxonomy('problem_category', array('attachment'), array(
			'hierarchical' => false,
			'label' => __('Problem Category'),
			'query_var' => 'problem_category',
			'rewrite' => array('slug' => 'problem-category'),
			));
		register_taxonomy('problem_difficulty', array('attachment'), array(
			'hierarchical' => false,
			'label' => __('Problem Difficulty'),
			'query_var' => 'problem_difficulty',
			'rewrite' => array('slug' => 'problem-difficulty'),
			));
		register_taxonomy('game_category', array('attachment'), array(
			'hierarchical' => false,
			'label' => __('Game Category'),
			'query_var' => 'game_category',
			'rewrite' => array('slug' => 'game-category'),
			));
	} # }}}

	function activate_plugin() { # {{{
		global $wp_rewrite, $wpdb;

		$this->register_taxonomies();
		$wp_rewrite->flush_rules();

		$query = "
			SELECT ID
			FROM $wpdb->posts
			WHERE post_mime_type = '$this->sgf_mime_type'
			";
		$sgf_posts = $wpdb->get_results($query);
		foreach ($sgf_posts as $post) {
			list($meta, $pattern_width, $pattern_height) = $this->get_sgf_metadata($post->ID);
			update_post_meta($post->ID, '_wpeidogo_pattern_width', $pattern_width);
			update_post_meta($post->ID, '_wpeidogo_pattern_height', $pattern_height);
			update_post_meta($post->ID, '_wpeidogo_sgf_metadata', $meta);
		}
	} # }}}

	/* HTML header */
	function eidogo_head_tags() { # {{{
		$ie6_warning = json_encode('<p class="ie6warning">' .  __(
			'Internet Explorer 6 is not currently supported by the EidoGo for
			WordPress plugin. Consider downloading one of the following:<br />
			<a href="http://www.microsoft.com/windows/internet-explorer/default.aspx">Internet Explorer 8</a><br />
			<a href="http://www.getfirefox.com/">Mozilla Firefox</a><br />
			<a href="http://www.google.com/chrome">Google Chrome</a><br />
			<a href="http://www.opera.com/">Opera</a>
			') .
			'</p>');

		$theme_styles = '';
		if (file_exists(get_stylesheet_directory() . '/wp-eidogo.css'))
			$theme_styles = '<link id="wp-eidogo-styles-theme" rel="stylesheet" type="text/css" href="'.
				get_stylesheet_directory_uri() . '/wp-eidogo.css" />';
		echo <<<html
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/eidogo/player/css/player.css" />
		<link rel="stylesheet" media="all" type="text/css" href="{$this->plugin_url}/wp-eidogo.css" />
		$theme_styles
		<script type="text/javascript"><!--
			var broken_browser = false;
		--></script>
		<!--[if lt IE 7]>
		<script type="text/javascript">
			broken_browser = {$ie6_warning};
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

	function sgf_media_form($default_fields, $post=null) { # {{{
		if ($post->post_mime_type != $this->sgf_mime_type)
			return $default_fields;

		$my_fields = array();
		$my_fields['align'] = array(
			'label' => __('Alignment'),
			'input' => 'html',
			'html'  => image_align_input_fields($post, get_option('image_default_align')),
		);

		$meta = get_post_custom($post->ID);
		if (!$meta['_wpeidogo_theme']) $meta['_wpeidogo_theme'] = array('compact');
		if (!$meta['_wpeidogo_embed_method']) $meta['_wpeidogo_embed_method'] = array('iframe');
		if (!$meta['_wpeidogo_problem_color']) $meta['_wpeidogo_problem_color'] = array('auto');

		$themes = array('compact' => 'Compact', 'full' => 'Full', 'problem' => 'Problem');
		$my_fields['eidogo_theme'] = array(
			'label' => __('Theme'),
			'input' => 'html',
			'html' => $this->simple_radio('eidogo_theme', $themes, $post->ID, $meta['_wpeidogo_theme'][0], true),
		);

		$methods = array('iframe' => 'Iframe', 'inline' => 'Inline');
		$my_fields['embed_method'] = array(
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
		$my_fields['problem_color'] = array(
			'label' => __('Problem Color'),
			'input' => 'html',
			'html' => $this->simple_radio('problem_color', $colors, $post->ID,
				$meta['_wpeidogo_problem_color'][0]),
		);

		$form_fields = array();
		foreach ($default_fields as $key => $val) {
			if ($key == 'problem_category')
				$form_fields = array_merge($form_fields, $my_fields);
			$form_fields[$key] = $val;
		}

		$form_fields['wpeidogo_meta'] = array(
			'label' => 'Meta',
			'input' => 'html',
			'html' => $fmime . $furl . $formscript
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

	function get_sgf_metadata($post_id) { # {{{
		$fn = get_attached_file($post_id);
		$meta = array();
		$contents = @file_get_contents($fn, 0, null, 0, 65536);
		if (!$contents)
			return array(null, null, null);
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
			return array($meta, null, null);

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
			$pattern_width = null;
			$pattern_height = null;
		} else {
			# Get board dimensions
			$sz = ($meta['SZ'] ? $meta['SZ'] : array(19));
			if (sizeof($sz) > 1) {
				$width = $sz[0];
				$height = $sz[1];
			} else {
				$width = $height = $sz[0];
			}
			# Include padding
			if ($l > 0) $l -= 1;
			if ($t > 0) $t -= 1;
			if ($r < $width-1) $r += 1;
			if ($b < $height-1) $b += 1;
			$pattern_width = $r-$l+1;
			$pattern_height = $b-$t+1;
		}

		return array($meta, $pattern_width, $pattern_height);
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
		list($meta, $pattern_width, $pattern_height) = $this->get_sgf_metadata($post['ID']);
		update_post_meta($post['ID'], '_wpeidogo_pattern_width', $pattern_width);
		update_post_meta($post['ID'], '_wpeidogo_pattern_height', $pattern_height);
		update_post_meta($post['ID'], '_wpeidogo_sgf_metadata', $meta);

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
			($params['caption'] ? '' : ' class="'.htmlspecialchars($params['class']).'"') . ' />';

		if ($params['href'])
			$tag = '<a href="'.htmlspecialchars($params['href']).'">'.$tag.'</a>';

		$params['caption'] = htmlspecialchars($params['caption']);
		if ($params['htmlcaption'])
			$params['caption'] = $params['htmlcaption'];

		if (!$params['caption'])
			return $tag;

		return "\n\n".'[caption id="" align="'.htmlspecialchars($params['class']).
			'" width="'.$info[0].'" caption="'.$params['caption'].'"]'.$tag."[/caption]\n\n";
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
		# TODO: Make this work for sgfUrl things

		# Default to view mode except if we're a problem
		if (!$params['mode'] && $theme != 'problem')
			$params['mode'] = 'view';

		$embed_method = ($theme == 'full' || $theme == 'compact' ? 'iframe' : 'inline');
		$styles = '';

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
			);
			if ($params['problemcolor'])
				$js_config['problemColor'] = $params['problemcolor'];
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

		$this->sgf_prepared_markup[$this->sgf_count] = <<<html
			<div class="{$class}">
			<div class="player-container" id="player-container-{$this->sgf_count}"{$styles}></div>
			<script type="text/javascript"><!--
				if (broken_browser) {
					document.getElementById('player-container-{$this->sgf_count}').innerHTML = broken_browser;
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
