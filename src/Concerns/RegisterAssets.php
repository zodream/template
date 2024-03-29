<?php
declare(strict_types=1);
namespace Zodream\Template\Concerns;

use Zodream\Helpers\Arr;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Support\Html;
use Zodream\Template\AssetFile;
use Zodream\Template\AssetHelper;
use Zodream\Template\View;
use Exception;

trait RegisterAssets {

    /** @var array layout 之前注册的数据 */
    protected array $lastRegisterAssets = [];

    /**
     * @var array 全局数据
     */
    protected array $globeRegisterAssets = [
    ];

    /**
     * 当前页数据
     * @var array
     */
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
     * @param mixed $file
     * @return string
     * @throws Exception
     */
    public function getAssetUri(mixed $file): string {
        if (is_string($file) && str_starts_with($file, 'data:')) {
            return $file;
        }
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
        $file = $this->repairAssetFolder($file);
        return url()->asset($this->assetsDirectory.$file);
    }

    protected function repairAssetFolder(string $file): string {
        if (!str_starts_with($file, '@')) {
            return $file;
        }
        if (Str::isPathEndWith($file, '.js')) {
            return sprintf('js/%s',  substr($file, 1));
        }
        if (Str::isPathEndWith($file, '.css')) {
            return sprintf('css/%s',  substr($file, 1));
        }
        return $file;
    }

    /**
     * 合并当前注册的资源
     * @param bool $append
     * @return $this
     */
    protected function transferAssetToGlobe(bool $append = true): static {
        if (AssetHelper::isEmpty($this->currentRegisterAssets)) {
            return $this;
        }
        $this->globeRegisterAssets = AssetHelper::merge($this->globeRegisterAssets, $this->currentRegisterAssets, $append);
        $this->currentRegisterAssets = AssetHelper::clear($this->currentRegisterAssets);
        return $this;
    }

    protected function transferAssetToLast(): static {
        if (!AssetHelper::isEmpty($this->currentRegisterAssets)) {
            $this->lastRegisterAssets = AssetHelper::merge($this->globeRegisterAssets, $this->currentRegisterAssets, true);
            $this->currentRegisterAssets = AssetHelper::clear($this->currentRegisterAssets);
            $this->globeRegisterAssets = [];
        } else if (!AssetHelper::isEmpty($this->globeRegisterAssets)) {
            $this->lastRegisterAssets = $this->globeRegisterAssets;
            $this->globeRegisterAssets = [];
        }
        return $this;
    }

    protected function transferAssetComplete(bool $complete = true): array {
        if (!$complete) {
            return $this->lastRegisterAssets;
        }
        $this->transferAssetToGlobe(true);
        if (!AssetHelper::isEmpty($this->lastRegisterAssets)) {
            return AssetHelper::merge($this->globeRegisterAssets, $this->lastRegisterAssets, true);
        }
        return $this->globeRegisterAssets;
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

    public function registerJsFile($urls, array $options = [], mixed $key = null) {
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
    public function start(string $name) {
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
    public function section(string $name, mixed $default = null) {
        if (!isset($this->sections[$name])) {
            return $default;
        }
        return $this->sections[$name];
    }

    public function header(bool $complete = true) {
        return $this->renderHeader($this->transferAssetComplete($complete));
    }

    public function footer(bool $complete = true) {
        return $this->renderFooter($this->transferAssetComplete($complete));
    }

    protected function clearAssets() {
        $this->globeRegisterAssets = $this->lastRegisterAssets = $this->sections = [];
        $this->currentRegisterAssets = AssetHelper::clear($this->currentRegisterAssets);
    }

    /**
     * @return string
     */
    protected function renderFooter(array $assetItems): string {
        $lines = [];
        foreach ([
                     'jsFiles',
                     'js'
                 ] as $tag) {
            if (empty($assetItems[$tag])) {
                continue;
            }
            foreach ([
                         View::HTML_FOOT,
                         View::JQUERY_READY,
                         View::JQUERY_LOAD
                     ] as $order) {
                if (empty($assetItems[$tag][$order])) {
                    continue;
                }
                $items = $assetItems[$tag][$order];
                if ($tag !== 'js') {
                    $lines[] = implode(PHP_EOL, $items);
                    continue;
                }
                $js = implode("\n", $items);
                if ($order === View::JQUERY_READY) {
                    $js = "jQuery(document).ready(function () {\n" . $js . "\n});";
                } elseif ($order === View::JQUERY_LOAD) {
                    $js = "jQuery(window).load(function () {\n" . $js . "\n});";
                }
                $lines[] = Html::script($js, ['type' => 'text/javascript']);
            }
        }
        return empty($lines) ? '' : implode(PHP_EOL, $lines);
    }

    /**
     * @return string
     */
    protected function renderHeader(array $assetItems): string {
        $lines = [];
        foreach ([
                     'metaTags',
                     'linkTags',
                     'cssFiles',
                     'css',
                     'jsFiles',
                     'js'
                 ] as $tag) {
            if (empty($assetItems[$tag])) {
                continue;
            }
            $isScript = str_starts_with($tag, 'js');
            if ($isScript && empty($assetItems[$tag][View::HTML_HEAD])) {
                continue;
            }
            $items = $isScript ? $assetItems[$tag][View::HTML_HEAD] : $assetItems[$tag];
            $lines[] = $tag === 'js' ? Html::script(implode(PHP_EOL,
                $items), ['type' => 'text/javascript']) : implode(PHP_EOL, $items);
        }
        return empty($lines) ? '' : implode(PHP_EOL, $lines);
    }
}