<?php
namespace Zodream\Template\Concerns;

use Zodream\Helpers\Arr;
use Zodream\Infrastructure\Support\Html;
use Zodream\Template\AssetFile;
use Zodream\Template\View;
use Exception;

trait RegisterAssets {

    protected array $registerAssets = [
        'metaTags' => [],
        'linkTags' => [],
        'js' => [],
        'jsFiles' => [],
        'css' => [],
        'cssFiles' => []
    ];

    protected array $currentRegisterAssets = [
        'metaTags' => [],
        'linkTags' => [],
        'js' => [],
        'jsFiles' => [],
        'css' => [],
        'cssFiles' => []
    ];

    protected array $assetsMaps = [];

    protected array $sections = [];


    public function registerAssetsMap($files, $url = null) {
        if (!is_array($files)) {
            $files = [(string)$files => $url];
        }
        foreach ($files as $key => $item) {
            $this->assetsMaps[$key] = empty($item) ? $key : $item;
        }
        return $this;
    }

    public function getAssetFromMaps($file) {
        return is_string($file) && isset($this->assetsMaps[$file])
            ? $this->assetsMaps[$file] : $file;
    }

    public function setAssetsDirectory(string $directory) {
        $this->assetsDirectory = '/'.trim($directory, '/');
        if ($this->assetsDirectory != '/') {
            $this->assetsDirectory .= '/';
        }
        return $this;
    }

    /**
     * GET ASSET FILE
     * @param string $file
     * @return AssetFile
     * @throws Exception
     */
    public function getAssetFile($file) {
        if (is_file($file)) {
            return new AssetFile($file);
        }
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (str_starts_with($file, '@') && ($ext == 'js' || $ext == 'css')) {
            $file = $ext.'/'. substr($file, 1);
        }
        return new AssetFile(public_path()->file($this->assetsDirectory.$file));
    }

    /**
     * @param $file
     * @return string
     * @throws Exception
     */
    public function getAssetUri($file): string
    {
        $file = $this->getAssetFromMaps($file);
        if (is_file($file)) {
            return (new AssetFile($file))->getUrl();
        }
        if (str_starts_with($file, '/')
            || str_contains($file, '//')) {
            return $file;
        }
        if (str_starts_with($file, './')) {
            return url($file, true, false);
        }
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (str_starts_with($file, '@') && ($ext == 'js' || $ext == 'css')) {
            $file = $ext.'/'. substr($file, 1);
        }
        return url()->asset($this->assetsDirectory.$file);
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
            $this->registerAssets[$key] = $this->_mergeAssets($this->registerAssets[$key], $item, $append);
            $this->currentRegisterAssets[$key] = [];
        }
        return $this;
    }

    private function _mergeAssets(array $base, array $args, $append) {
        if (empty($base)) {
            return $args;
        }
        foreach ($args as $key => $item) {
            if (!isset($base[$key])) {
                $base[$key] = $item;
                continue;
            }
            if (!is_array($item) || !is_array($base[$key])) {
                continue;
            }
            $base[$key] = $append
                ? array_merge($base[$key], $item)
                : array_merge($item, $base[$key]);
        }
        return $base;
    }

    /**
     * @param string $content
     * @param array $options
     * @param null $key
     * @return $this
     */
    public function registerMetaTag($content, array $options = array(), $key = null) {
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
    public function registerLinkTag($url, array $options = array(), $key = null) {
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

    /**
     * @param $urls
     * @param array $options
     * @param null $key
     * @return static
     * @throws Exception
     */
    public function registerCssFile($urls, array $options = array(), $key = null) {
        $options['rel'] = 'stylesheet';
        foreach ((array)$urls as $url) {
            $k = $key ?: $url;
            $this->currentRegisterAssets['cssFiles'][$k] = Html::link($this->getAssetUri($url), $options);
        }
        return $this;
    }

    public function registerJs($js, $position = View::HTML_FOOT, $key = null) {
        $key = $key ?: md5($js);
        $this->currentRegisterAssets['js'][$position][$key] = $js;
        return $this;
    }

    public function registerJsFile($urls, $options = [], $key = null) {
        $position = Arr::remove($options, 'position', View::HTML_FOOT);
        foreach ((array)$urls as $url) {
            $k = $key ?: $url;
            $options['src'] = $this->getAssetUri($url);
            $this->currentRegisterAssets['jsFiles'][$position][$k] = Html::script(null, $options);
        }
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
                __('The section name "content" is reserved.')
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
                __('You must start a section before you can stop it.')
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
        return $this->renderHeader();
    }

    public function footer() {
        $this->moveRegisterAssets(false);
        return $this->renderFooter();
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

    /**
     * @return string
     */
    public function renderFooter(): string {
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

    /**
     * @return string
     */
    public function renderHeader(): string {
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
}