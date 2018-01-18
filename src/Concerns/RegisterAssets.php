<?php
namespace Zodream\Template\Concerns;

use Zodream\Helpers\Arr;
use Zodream\Infrastructure\Support\Html;
use Zodream\Service\Routing\Url;
use Zodream\Template\AssetFile;
use Zodream\Template\View;
use Exception;

trait RegisterAssets {

    protected $registerAssets = [
        'metaTags' => [],
        'linkTags' => [],
        'js' => [],
        'jsFiles' => [],
        'css' => [],
        'cssFiles' => []
    ];

    protected $currentRegisterAssets = [
        'metaTags' => [],
        'linkTags' => [],
        'js' => [],
        'jsFiles' => [],
        'css' => [],
        'cssFiles' => []
    ];

    protected $sections = [];

    public function setAssetsDirectory($directory) {
        $this->assetsDirectory = '/'.trim($directory, '/');
        if ($this->assetsDirectory != '/') {
            $this->assetsDirectory .= '/';
        }
        return $this;
    }


    /**
     * GET ASSET FILE
     * @param string $file
     * @return string
     */
    public function getAssetFile($file) {
        if (is_file($file)) {
            return (new AssetFile($file))->getUrl();
        }
        if (strpos($file, '/') === 0
            || strpos($file, '//') !== false) {
            return $file;
        }
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (strpos($file, '@') === 0 && ($ext == 'js' || $ext == 'css')) {
            $file = $ext.'/'. substr($file, 1);
        }
        return $this->assetsDirectory.$file;
    }

    /**
     * 合并当前注册的资源
     * @param bool $append
     * @return $this
     */
    protected function moveRegisterAssets($append = true) {
        foreach ($this->currentRegisterAssets as $key => $item) {
            if (empty($item)) {
                continue;
            }
            $this->registerAssets[$key] = $append
                ? array_merge($this->registerAssets[$key], $item)
                : array_merge($item, $this->registerAssets[$key]);
            $this->currentRegisterAssets[$key] = [];
        }
        return $this;
    }

    /**
     * @param string $content
     * @param array $options
     * @param null $key
     * @return $this
     */
    public function registerMetaTag($content, $options = array(), $key = null) {
        if ($key === null) {
            $this->currentRegisterAssets['metaTags'][] = Html::meta($content, $options);
        } else {
            $this->currentRegisterAssets['metaTags'][$key] = Html::meta($content, $options);
        }
        return $this;
    }

    /**
     * @param $url
     * @param array $options
     * @param null $key
     * @return $this
     */
    public function registerLinkTag($url, $options = array(), $key = null) {
        if ($key === null) {
            $this->currentRegisterAssets['linkTags'][] = Html::link($url, $options);
        } else {
            $this->currentRegisterAssets['linkTags'][$key] = Html::link($url, $options);
        }
        return $this;
    }

    public function registerCss($css, $key = null) {
        $key = $key ?: md5($css);
        $this->currentRegisterAssets['css'][$key] = Html::style($css);
        return $this;
    }

    public function registerCssFile($url, $options = array(), $key = null) {
        $key = $key ?: $url;
        $options['rel'] = 'stylesheet';
        $this->currentRegisterAssets['cssFiles'][$key] = Html::link($this->getAssetFile($url), $options);
        return $this;
    }

    public function registerJs($js, $position = View::HTML_FOOT, $key = null) {
        $key = $key ?: md5($js);
        $this->currentRegisterAssets['js'][$position][$key] = $js;
        return $this;
    }

    public function registerJsFile($url, $options = [], $key = null) {
        $key = $key ?: $url;
        $position = Arr::remove($options, 'position', View::HTML_FOOT);
        $options['src'] = Url::to($this->getAssetFile($url));
        $this->currentRegisterAssets['jsFiles'][$position][$key] = Html::script(null, $options);
        return $this;
    }

    /**
     * Start a new section block.
     * @param  string $name
     * @throws Exception
     */
    public function start($name) {
        if ($name === 'content') {
            throw new Exception(
                'The section name "content" is reserved.'
            );
        }
        $this->sections[$name] = '';
        ob_start();
    }

    /**
     * Stop the current section block.
     * @throws Exception
     */
    public function stop() {
        if (empty($this->sections)) {
            throw new Exception(
                'You must start a section before you can stop it.'
            );
        }
        end($this->sections);
        $this->sections[key($this->sections)] = ob_get_clean();
    }
    /**
     * Returns the content for a section block.
     * @param  string      $name    Section name
     * @param  string      $default Default section content
     * @return string|null
     */
    public function section($name, $default = null) {
        if (!isset($this->sections[$name])) {
            return $default;
        }
        return $this->sections[$name];
    }

    public function header() {
        $this->moveRegisterAssets(false);
        $lines = [];
        if (!empty($this->registerAssets['metaTags'])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['metaTags']);
        }

        if (!empty($this->registerAssets['linkTags'])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['linkTags']);
        }
        if (!empty($this->registerAssets['cssFiles'])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['cssFiles']);
        }
        if (!empty($this->registerAssets['css'])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['css']);
        }
        if (!empty($this->registerAssets['jsFiles'][View::HTML_HEAD])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['jsFiles'][View::HTML_HEAD]);
        }
        if (!empty($this->registerAssets['js'][View::HTML_HEAD])) {
            $lines[] = Html::script(implode(PHP_EOL, $this->registerAssets['js'][View::HTML_HEAD]), ['type' => 'text/javascript']);
        }

        return empty($lines) ? '' : implode(PHP_EOL, $lines);
    }

    public function footer() {
        $this->moveRegisterAssets(false);
        $lines = [];
        if (!empty($this->registerAssets['jsFiles'][View::HTML_FOOT])) {
            $lines[] = implode(PHP_EOL, $this->registerAssets['jsFiles'][View::HTML_FOOT]);
        }
        if (!empty($this->registerAssets['js'][View::HTML_FOOT])) {
            $lines[] = Html::script(implode(PHP_EOL, $this->registerAssets['js'][View::HTML_FOOT]), ['type' => 'text/javascript']);
        }
        if (!empty($this->registerAssets['js'][View::JQUERY_READY])) {
            $js = "jQuery(document).ready(function () {\n" . implode("\n", $this->registerAssets['js'][View::JQUERY_READY]) . "\n});";
            $lines[] = Html::script($js, ['type' => 'text/javascript']);
        }
        if (!empty($this->registerAssets['js'][View::JQUERY_LOAD])) {
            $js = "jQuery(window).load(function () {\n" . implode("\n", $this->registerAssets['js'][View::JQUERY_LOAD]) . "\n});";
            $lines[] = Html::script($js, ['type' => 'text/javascript']);
        }

        return empty($lines) ? '' : implode(PHP_EOL, $lines);
    }

    protected function clearAssets() {
        $this->registerAssets = $this->currentRegisterAssets = [
            'metaTags' => [],
            'linkTags' => [],
            'js' => [],
            'jsFiles' => [],
            'css' => [],
            'cssFiles' => []
        ];
        $this->sections = [];
    }
}