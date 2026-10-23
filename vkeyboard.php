<?php

/**
 * vkeyboard — virtual keyboard for login fields (keylogger mitigation)
 *
 * @version 1.9.1
 * @author  Roland 'rosali' Liebl / MyRoundcube; Elastic port for rc_lite
 */

class vkeyboard extends rcube_plugin
{
    /** Login/logout only (settings can be re-enabled if desired). */
    public $task = 'login|logout';
    public $noajax = true;

    private $rcmail;

    static private $version = '1.9.3';
    static private $date = '18-09-2026';

    /**
     * Roundcube 1.5+ / 1.7 plugin metadata.
     */
    public static function info()
    {
        return [
            'name' => 'vkeyboard',
            'vendor' => 'MyRoundcube / rc_lite port',
            'version' => self::$version,
            'license' => 'GPL-3.0-or-later',
            'uri' => 'https://github.com/roundcube/roundcubemail',
            'require' => [],
        ];
    }

    function init()
    {
        $this->rcmail = rcmail::get_instance();

        if ($this->rcmail->task == 'login' || $this->rcmail->task == 'logout') {
            $this->add_texts('localization/', true);
            $this->add_hook('render_page', [$this, 'render_page']);
        }
    }

    /**
     * Legacy MyRoundcube metadata helper (kept for older plugin managers).
     */
    static public function about($keys = false)
    {
        $ret = [
            'plugin' => 'vkeyboard',
            'version' => self::$version,
            'date' => self::$date,
            'author' => 'myroundcube@mail4us.net',
            'comments' => 'Elastic / rc_lite; Roundcube 1.4–1.7',
            'licence' => 'GPL',
            'requirements' => [
                'Roundcube' => '1.4',
                'PHP' => '7.4',
            ],
            'config' => [],
        ];

        if (is_array($keys)) {
            $return = ['plugin' => 'vkeyboard'];
            foreach ($keys as $key) {
                if (array_key_exists($key, $ret)) {
                    $return[$key] = $ret[$key];
                }
            }
            return $return;
        }

        return $ret;
    }

    /**
     * Allow only canonical plugin skin paths (no traversal).
     */
    private function is_safe_skin_path($path)
    {
        return is_string($path) && (bool) preg_match('#^skins/[a-z0-9_-]+$#i', $path);
    }

    /**
     * Resolve plugin skin folder.
     * Modern Elastic-family skins always use skins/elastic (never Larry fallback first).
     */
    private function resolve_skin_path()
    {
        $configured = (string) $this->rcmail->config->get('skin', 'elastic');
        $elastic_family = [
            'elastic', 'rc_lite', 'droid_plus', 'droid+',
        ];
        $prefer_elastic = in_array($configured, $elastic_family, true)
            || strpos($configured, 'elastic') !== false
            || strpos($configured, 'rc_lite') !== false;

        if ($prefer_elastic && is_file($this->home . '/skins/elastic/keyboard.css')) {
            return 'skins/elastic';
        }

        $skin_path = $this->local_skin_path();

        if ($this->is_safe_skin_path($skin_path)
            && is_file($this->home . '/' . $skin_path . '/keyboard.css')
        ) {
            return $skin_path;
        }

        foreach (['skins/elastic', 'skins/larry', 'skins/classic'] as $fallback) {
            if (is_file($this->home . '/' . $fallback . '/keyboard.css')) {
                return $fallback;
            }
        }

        return 'skins/elastic';
    }

    /**
     * Public URL for a plugin asset (RC 1.7: must go through static.php / assets_path).
     * Docroot is public_html — bare plugins/… paths 404.
     */
    private function asset_url($relative)
    {
        $url = $this->url($relative);
        if (method_exists($this->rcmail->output, 'asset_url')) {
            return $this->rcmail->output->asset_url($url);
        }
        return $url;
    }

    /**
     * Icon URL + intrinsic size for the VKI initiator image.
     */
    private function resolve_icon($skin_path)
    {
        $svg = $this->home . '/' . $skin_path . '/keyboard.svg';
        if (is_file($svg)) {
            return [
                'url' => $this->asset_url($skin_path . '/keyboard.svg'),
                'width' => 24,
                'height' => 24,
            ];
        }

        $png = $this->home . '/' . $skin_path . '/keyboard.png';
        $size = is_file($png) ? @getimagesize($png) : false;

        return [
            'url' => $this->asset_url($skin_path . '/keyboard.png'),
            'width' => $size ? (int) $size[0] : 24,
            'height' => $size ? (int) $size[1] : 24,
        ];
    }

    /**
     * Session language → safe BCP47-ish token for HTML lang= and VKI.
     */
    private function safe_language()
    {
        $lang = $_SESSION['language'] ?? 'en_US';
        if (!is_string($lang) || !preg_match('/^[a-z]{2}([_-][A-Za-z0-9]+)*$/', $lang)) {
            return 'en_US';
        }
        return $lang;
    }

    function render_page($args)
    {
        $skin_path = $this->resolve_skin_path();
        if (!$this->is_safe_skin_path($skin_path)) {
            $skin_path = 'skins/elastic';
        }

        // RC 1.7 include_stylesheet() requires a real filesystem path — no ?query.
        // Cache-bust is automatic via static.php ?s=<mtime>.
        $this->include_stylesheet($skin_path . '/keyboard.css');
        $this->include_script('vkeyboard.js');
        $this->include_script('keyboard/keyboard.js');

        $icon = $this->resolve_icon($skin_path);
        $this->rcmail->output->set_env('vkskin', basename($skin_path));
        $this->rcmail->output->set_env('vkwidth', $icon['width']);
        $this->rcmail->output->set_env('vkheight', $icon['height']);
        $this->rcmail->output->set_env('vkicon', $icon['url']);
        $this->rcmail->output->set_env('vklang', $this->safe_language());

        $this->rcmail->output->add_script(
            'if (window.VKI_buildKeyboardInputs) { VKI_buildKeyboardInputs(); }',
            'docready'
        );

        return $args;
    }
}
