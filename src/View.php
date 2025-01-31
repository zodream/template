<?php
declare(strict_types=1);
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/3
 * Time: 9:19
 */
use Zodream\Disk\File;
use Zodream\Disk\FileException;
use Zodream\Helpers\Html;
use Zodream\Helpers\Time;
use Zodream\Infrastructure\Error\RuntimeException;
use Zodream\Infrastructure\Error\TemplateException;
use Zodream\Template\Concerns\ConditionTrait;
use Exception;
use Zodream\Template\Events\ViewRendered;

/**
 * Class View
 * @package Zodream\Domain\View
 * @property string $title
 *
 * @method ViewFactory getAssetFile($file)
 * @method ViewFactory get($key, $default = null)
 * @method ViewFactory set($key, $value = null)
 * @method string header($complete = true)
 * @method string footer($complete = true)
 * @method ViewFactory start($name)
 * @method ViewFactory stop()
 * @method ViewFactory section($name, $default = null)
 */
class View {

    use ConditionTrait;

    const HTML_HEAD = 'html head';

    const HTML_FOOT = 'html body end';

    const JQUERY_LOAD = 'jquery load';
    const JQUERY_READY = 'jquery ready';
    const LAYOUT_CONTENTS = '__layouts_contents';

    /**
     * 具体执行的文件
     * @var File
     */
    protected File|null $file;

    /**
     * 原始文件
     * @var File
     */
    protected File|null $sourceFile;

    public function __construct(
                                protected ViewFactory $factory,
                                mixed $file = null, File|null $sourceFile = null) {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        $this->file = $file;
        $this->sourceFile = empty($sourceFile) ? $file : $sourceFile;
    }

    /**
     * SET Source FILE
     * @param File|string $file
     * @return $this
     */
    public function setFile(mixed $file): static {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        $this->sourceFile = $file;
        return $this;
    }

    /**
     * @return File
     */
    public function getFile(): File {
        return $this->file;
    }

    /**
     * @return File
     */
    public function getSourceFile(): File {
        return $this->sourceFile;
    }

    /**
     *
     * @param callable|null $callback
     * @return string
     * @throws \Exception
     */
    public function render(callable|null $callback = null) {
        return $this->renderWithData([], $callback);
    }

    /**
     * @param array $data
     * @param callable|null $callback
     * @return mixed|null|string
     * @throws \Exception
     */
    public function renderWithData(array $data = [], callable|null $callback = null): mixed {
        $contents = $this->renderContent($data);
        $response = isset($callback) ? call_user_func($callback, $this, $contents) : null;
        return !is_null($response) ? $response : $contents;
    }

    /**
     * @param array $renderOnlyData
     * @return string
     * @throws Exception
     * @throws FileException
     */
    protected function renderContent(array $renderOnlyData = []): string {
        if (!$this->file->exist()) {
            throw new FileException(
                __('{file} not exist!', [
                    'file' => $this->file
                ])
            );
        }
        $start = Time::millisecond();
        $result = $this->renderFile((string)$this->file, $this->factory->merge($renderOnlyData));
        event(new ViewRendered($this->sourceFile, $this->file,
            $this,
            Time::elapsedTime($start)));
        return $result;
    }

    /**
     * @param string $renderViewFile
     * @param array $renderViewData
     * @return string
     * @throws Exception
     */
    protected function renderFile(string $renderViewFile, array $renderViewData): string {
        unset($renderViewData[static::LAYOUT_CONTENTS]);
        $obLevel = ob_get_level();
        ob_start();
        extract($renderViewData, EXTR_SKIP);
        try {
            include $renderViewFile;
            /*eval('?>'.$content);*/
        } catch (\Throwable $e) {
            $this->handleViewException($e, $obLevel);
        }
        return ltrim(ob_get_clean());
    }

    /**
     * Handle a view exception.
     *
     * @param  \Exception  $e
     * @param  int  $obLevel
     * @return void
     *
     * @throws $e
     */
    protected function handleViewException(\Throwable $e, int $obLevel): void {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }
        throw $e instanceof RuntimeException ? $e : new TemplateException(
            $this->sourceFile,
            $this->file,
            $e->getMessage(), (int)$e->getCode(), $e);
    }

    /**
     * 输出格式化后的时间
     * @param integer|string|null $time
     * @return string
     */
    public function time(mixed $time = null): string {
        if (is_null($time)) {
            return '';
        }
        return Time::format($time);
    }

    /**
     * 输出是多久以前
     * @param int|string $time
     * @return string
     */
    public function ago(int|string $time): string {
        if (is_string($time) && (str_contains($time, '-') || str_contains($time, ':'))) {
            return Time::ago(strtotime($time));
        }
        return Time::ago(intval($time));
    }

    /**
     * 翻译 {}
     * @param string $message
     * @param array $param
     * @param string|null $name
     * @return mixed
     * @throws Exception
     */
    public function t(string $message, array $param = [], string|null $name = null): mixed {
        return trans($message, $param, $name);
    }

    /**
     * 转义并截取长度
     * @param string $html
     * @param int $length
     * @return string
     */
    public function text(mixed $html, int $length = 0): string {
        return Html::text((string)$html, $length);
    }

    /**
     * GET COMPLETE URL
     * @param null $file
     * @param array|bool $extra
     * @param bool $encode
     * @param bool|null $secure
     * @return string
     * @throws Exception
     */
    public function url(mixed $file = null, array|bool $extra = [], bool $encode = true,
                        bool|null $secure = null): string {
        if (is_bool($extra)) {
            list($extra, $encode) = [[], $extra];
        }
        return url()->to($file, $extra, $secure, $encode);
    }

    /**
     * 获取资源文件路径
     * @param $file
     * @return string
     * @throws Exception
     */
    public function asset(mixed $file): string {
        return $this->url($this->factory->getAssetUri($file));
    }

    /**
     * @param mixed $file
     * @return AssetFile
     * @throws Exception
     */
    public function assetFile(mixed $file): AssetFile {
        return $this->factory->getAssetFile($file);
    }

    /**
     * 获取路径
     * @param string $name
     * @return File| string
     */
    protected function getExtendFile(string $name) {
        if (str_starts_with($name, '@')) {
            return $this->factory->invokeTheme('getFile', [substr($name, 1)]);
        }
        if (str_starts_with($name, './')) {
            return $this->sourceFile->getDirectory()
                ->getFile($this->factory->fileSuffix(substr($name, 2)));
        }
        if (str_starts_with($name, '../')) {
            return $this->sourceFile->getDirectory()->parent()
                ->getFile($this->factory->fileSuffix($name));
        }
        return $name;
    }

    /**
     * 加载其他文件
     * @param array|string $name
     * @param array $data
     * @return $this
     */
    public function extend(array|string $name, array $data = []) {
        foreach ((array)$name as $item) {
            echo $this->renderPart((string)$item, $data);
        }
        return $this;
    }

    /**
     * 获取其他组件共享过来的内容
     * @return string
     */
    public function contents(): string {
        if ($this->factory->has(static::LAYOUT_CONTENTS)) {
            return (string)$this->factory->get(static::LAYOUT_CONTENTS);
        }
        return '';
    }

    /**
     * 获取渲染部分文件的内容
     * @param string|File $name
     * @param array $data
     * @return string
     * @throws FileException
     */
    public function renderPart(string|File $name, array $data = []): string {
        return $this->factory->getView($name instanceof File ? $name : $this->getExtendFile($name))
            ->renderWithData($data);
    }

    /**
     *
     * @param string $content
     * @param array $options
     * @param string|null $key
     * @return View
     */
    public function registerMetaTag(string $content, array $options = array(), string|null $key = null) {
        $this->factory->registerMetaTag($content, $options, $key);
        return $this;
    }

    public function registerLinkTag(mixed $url, array $options = array(), string|null $key = null) {
        $this->factory->registerLinkTag($url, $options, $key);
        return $this;
    }

    public function registerCss(string $css, string|null $key = null) {
        $this->factory->registerCss($css, $key);
        return $this;
    }

    public function registerCssFile($url, array $options = array(), $key = null) {
        $this->factory->registerCssFile($url, $options, $key);
        return $this;
    }

    public function registerJs(string $js, string $position = self::HTML_FOOT, string|null $key = null) {
        $this->factory->registerJs($js, $position, $key);
        return $this;
    }

    public function registerJsFile($url, array $options = [], $key = null) {
        $this->factory->registerJsFile($url, $options, $key);
        return $this;
    }
    
    public function __set($name, $value) {
        if ($name === 'layout') {
            $this->factory->setLayout($value);
            return;
        }
        $this->factory->set($name, $value);
    }

    public function __get($name) {
        return $this->factory->get($name);
    }
    
    public function __unset($name) {
        $this->factory->deleteAttribute($name);
    }

    public function __call($name, array $arguments = []) {
        if (method_exists($this->factory, $name)) {
            $res = call_user_func_array([$this->factory, $name], $arguments);
            return $res && $res instanceof ViewFactory ? $this : $res;
        }
        if ($this->factory->canTheme($name)) {
            return $this->factory->invokeTheme($name, $arguments);
        }
        if (!defined('DEBUG') || !DEBUG) {
            return null;
        }
        throw new \BadMethodCallException(
            __(
                '{name} METHOD NOT FIND!', compact('name')
            )
        );
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function __toString() {
        return $this->render();
    }
}