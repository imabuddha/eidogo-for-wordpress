<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: Embeds the EidoGo SGF viewer/editor into a WordPress-powered blog
Version: 0.6
Author: Thomas Schumm
Author URI: http://www.fortmyersgo.org/
*/

$sgf_count = 0;
$sgf_prepared_markup = array();

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

function prepare_sgf($params, $sgf_data="", $theme='compact') { # {{{
    global $sgf_count, $sgf_prepared_markup;
    $wpu = WP_PLUGIN_URL;

    # Clean up the SGF data
    if (!trim($sgf_data))
        $sgf_data = "(;GM[1]FF[4]CA[UTF-8]SZ[19])";
    else
        $sgf_data = trim($sgf_data);

    $params = parse_attributes($params);

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
        $iframe = json_encode('<iframe src="'.$wpu.'/wp-eidogo/iframe-player.html#'.$sgf_count.
            '" frameborder="0" width="'.$frame_w.'" height="'.$frame_h.'" scrolling="no"></iframe>');
        $player_js = <<<javascript
            var playerContainer{$sgf_count} = document.getElementById('player-container-{$sgf_count}');
            playerContainer{$sgf_count}.eidogoConfig = {$js_config};
            playerContainer{$sgf_count}.innerHTML = {$iframe};
javascript;

    } else {
        $player_js = "alert('Unknown wp-eidogo theme {$theme}.');";
    }

    $class = 'wp-eidogo wp-eidogo-' . $theme;
    if ($params['class'])
        $class .= ' ' . $params['class'];

    $ie6_warning = json_encode('<p class="ie6warning">Internet Explorer 6 is
        not currently supported by the EidoGo for WordPress plugin. Get a real
        browser already for crying out loud.</p>');

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

function embed_sgf($id) { # {{{
    global $sgf_prepared_markup;
    return $sgf_prepared_markup[$id];
} # }}}

function prepare_eidogo_markup($content) { # {{{
    # For [sgf] tags with content
    $content = preg_replace(
        '/\s*\[sgf(.*?)\](.*?)\[\/sgf\]\s*/sie',
        'prepare_sgf("$1", "$2")', $content);

    # For empty [sgf] tags
    $content = preg_replace(
        '/\s*\[sgf\s(.*?)\]\s*/sie',
        'prepare_sgf("$1")', $content);

    return $content;
} # }}}

function embed_eidogo_markup($content) { # {{{
    $sgf_pattern = '\[sgfPrepared\s+id="(\d+)"\]';

    # Handle cases that have been modified by wpautop, etc.
    $content = preg_replace(
        '!<p[^>]*>\s*'.$sgf_pattern.'\s*</p>!sie',
        'embed_sgf("$1")', $content);

    # Fallback in case those didn't happen
    $content = preg_replace(
        '!'.$sgf_pattern.'!sie',
        'embed_sgf("$1")', $content);

    return $content;
} # }}}

function eidogo_head() { # {{{
    $wpu = WP_PLUGIN_URL;
    echo <<<html
    <link rel="stylesheet" media="all" type="text/css" href="{$wpu}/wp-eidogo/wp-eidogo.css" />
    <link rel="stylesheet" media="all" type="text/css" href="{$wpu}/wp-eidogo/eidogo-player-1.2/player/css/player.css" />
    <script type="text/javascript">
        var broken_browser = false;
    </script>
    <!--[if lt IE 7]>
    <script type="text/javascript">
        broken_browser = true;
    </script>
    <![endif]-->
    <script type="text/javascript" src="{$wpu}/wp-eidogo/eidogo-player-1.2/player/js/all.compressed.js"></script>
html;
} # }}}

# We want to embed the SGF data that is wholy unmolested by wpautop and other
# built-in wordpress functions, so we need to do our parsing BEFORE any such
# filters are called. However, we also want to avoid such filters modifying our
# markup, so we need to do the actual embedding at the end of the filter chain.
add_filter('the_content', 'prepare_eidogo_markup', 9);
add_filter('the_excerpt', 'prepare_eidogo_markup', 9);
add_filter('comment_text', 'prepare_eidogo_markup', 9);
add_filter('the_content', 'embed_eidogo_markup', 99);
add_filter('the_excerpt', 'embed_eidogo_markup', 99);
add_filter('comment_text', 'embed_eidogo_markup', 99);

# For necessary stylesheets and javascript files
add_action('wp_head', 'eidogo_head');

