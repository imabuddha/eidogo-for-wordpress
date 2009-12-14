<?php
/*
Plugin Name: EidoGo for WordPress
Plugin URI: http://www.fortmyersgo.org/eidogo-for-wordpress/
Description: Embeds the EidoGo SGF viewer/editor into a WordPress-powered blog
Version: 0.5
Author: Thomas Schumm
Author URI: http://www.fortmyersgo.org/
*/

$sgf_count = 0;

function parse_attributes($params) { # {{{
    $pattern = '/(\\w+)\s*=\\s*("[^"]*"|\'[^\']*\'|[^"\'\\s>]*)/';
    preg_match_all($pattern, $params, $matches, PREG_SET_ORDER);
    $attrs = array();

    foreach ($matches as $match) {
        if (($match[2][0] == '"' || $match[2][0] == "'") && $match[2][0] == $match[2][strlen($match[2])-1])
            $match[2] = substr($match[2], 1, -1);
        $attrs[strtolower($match[1])] = html_entity_decode($match[2]);
    }

    return $attrs;
} # }}}

function embed_sgf($more, $params, $sgf, $theme='compact') { # {{{
    global $sgf_count;
    $wpu = WP_PLUGIN_URL;

    # Clean up the SGF data
    if (!trim($sgf))
        $sgf_data = "(;GM[1]FF[4]CA[UTF-8]SZ[19])";
    else
        $sgf_data = strip_tags($sgf);

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

    # Shortcut for loadPath
    if ($params['movenumber'] && is_numeric($params['movenumber']))
        $params['loadpath'] = array($params['movenumber']+1, 0);

    if (!$params['mode'] && $theme != 'problem')
        $params['mode'] = 'view';

    if ($theme == 'compact-inline') { # {{{
        $embed_method = 'inline';
        $js_config = array(
            'theme'             => "compact",
            'showComments'      => true,
            'showPlayerInfo'    => true,
            'showGameInfo'      => false,
            'showTools'         => true,
            'showOptions'       => true,
            'markCurrent'       => true,
            'markVariations'    => true,
            'markNext'          => false,
            'problemMode'       => false,
            'enableShortcuts'   => false,
        );
    # }}}

    } elseif ($theme == 'problem') { # {{{
        $embed_method = 'inline';
        $js_config = array(
            'theme'             => "problem",
            'problemMode'       => true,
            'markVariations'    => false,
            'markNext'          => false,
            'shrinkToFit'       => true,
            'problemColor'      => $params['problemcolor'],
        );

    # }}}

    } elseif ($theme == 'full') { # {{{
        $embed_method = 'iframe'; $frame_w = 720; $frame_h = 600;
        $js_config = array(
            'theme'             => "full",
            'enableShortcuts'   => true,
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

    } else { # ($theme == 'compact') {{{
        $embed_method = 'iframe'; $frame_w = 423; $frame_h = 621;
        $js_config = array(
            'theme'             => "compact",
            'enableShortcuts'   => true,
            'showComments'      => true,
            'showPlayerInfo'    => true,
            'showGameInfo'      => false,
            'showTools'         => true,
            'showOptions'       => true,
        );
    }
    # }}}

    $js_config['sgf'] = $sgf_data;
    $js_config['container'] = 'player-container-' . $sgf_count;
    foreach (array('loadPath', 'mode') as $key) {
        $lkey = strtolower($key);
        if ($params[$lkey])
            $js_config[$key] = $params[$lkey];
    }

    $js_config = json_encode($js_config);

    if ($embed_method == 'inline') {
        $player = <<<html
            <div class="player-container" id="player-container-{$sgf_count}"></div>
            <script type="text/javascript"><!--
                var wpeidogo_player{$sgf_count} = new eidogo.Player({$js_config});
            --></script>
html;
    } else {
        $player = <<<html
            <iframe class="player-container" id="player-container-{$sgf_count}"
                src="{$wpu}/wp-eidogo/iframe-player.html?id={$sgf_count}"
                frameborder="0" width="{$frame_w}" height="{$frame_h}" scrolling="no">
            </iframe>
            <script type="text/javascript"><!--
                document.getElementById('player-container-{$sgf_count}').eidogoConfig = {$js_config};
            --></script>
html;

    }

    $sgf_count++;

    return <<<html
        $more
        <div class="wp-eidogo wp-eidogo-{$theme}">
        $player
        $caption
        </div>
html;

} # }}}

function add_eidogo_tags($content) { # {{{
    $content = preg_replace(
        '/(<p>\s*)(<span id=".*"><\/span>)?\[sgf(.*?)\]([\w\W]*?)\[\/sgf\](\s*<\/p>)?/ie',
        'embed_sgf("$2", "$3", "$4", "compact")', $content);
    return $content;
} # }}}

function eidogo_head() { # {{{
    $wpu = WP_PLUGIN_URL;
    echo <<<html
    <link rel="stylesheet" media="all" type="text/css" href="{$wpu}/wp-eidogo/wp-eidogo.css" />
    <link rel="stylesheet" media="all" type="text/css" href="{$wpu}/wp-eidogo/eidogo-player-1.2/player/css/player.css" />
    <script type="text/javascript" src="{$wpu}/wp-eidogo/eidogo-player-1.2/player/js/all.compressed.js"></script>
html;
} # }}}

add_filter('the_content', 'add_eidogo_tags', 99);
add_filter('the_excerpt', 'add_eidogo_tags', 99);
add_filter('comment_text', 'add_eidogo_tags', 99);
add_action('wp_head', 'eidogo_head');
