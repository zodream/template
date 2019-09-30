<?php
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/3
 * Time: 9:19
 */
use Zodream\Disk\File;
use Zodream\Disk\FileException;
use Zodream\Helpers\Str;
use Zodream\Service\Factory;
use Zodream\Http\Uri;
use Zodream\Helpers\Time;
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
 * @method string header()
 * @method string footer()
 * @method string renderFooter()
 * @method string renderHeader()
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

    /**
     * @var File
     */
    protected $file;

    /**
     * @var File
     */
    protected $sourceFile;

    /**
     * @var ViewFactory
     */
    protected $factory;
    
    public function __construct($factory, $file = null, $sourceFile = null) {
        $this->factory = $factory;
        if (!empty($file)) {
            $this->setFile($file, $sourceFile);
        }
    }

    /**
     * SET FILE
     * @param File|string $file
     * @param File $sourceFile
     * @return $this
     */
    public function setFile($file, $sourceFile = null) {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        $this->file = $file;
        $this->sourceFile = empty($sourceFile) ? $file : $sourceFile;
        return $this;
    }

    /**
     * @return File
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * @return File
     */
    public function getSourceFile() {
        return $this->sourceFile;
    }

    /**
     *
     * @param callable|null $callback
     * @return string
     * @throws \Exception
     */
    public function render(callable $callback = null) {
        return $this->renderWithData([], $callback);
    }

    /**
     * @param array $data
     * @param callable|null $callback
     * @return mixed|null|string
     * @throws \Exception
     */
    public function renderWithData($data = [], callable $callback = null) {
        try {
            $contents = $this->renderContent($data);
            $response = isset($callback) ? call_user_func($callback, $this, $contents) : null;
            return !is_null($response) ? $response : $contents;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param array $renderOnlyData
     * @return string
     * @throws Exception
     * @throws FileException
     */
    protected function renderContent($renderOnlyData = []) {
        if (!$this->sourceFile->exist()) {
            throw new FileException(
                __('{file} not exist!', [
                    'file' => $this->sourceFile
                ])
            );
        }
        $start = Time::millisecond();
        $result = $this->renderFile((string)$this->sourceFile, $this->factory->merge($renderOnlyData));
        event(new ViewRendered($this->file, $this, Time::elapsedTime($start)));
        return $result;
    }

    /**
     * @param string $renderViewFile
     * @param array $renderViewData
     * @return string
     * @throws Exception
     */
    protected function renderFile($renderViewFile, array $renderViewData) {
        $obLevel = ob_get_level();
        ob_start();
        extract($renderViewData, EXTR_SKIP);
        try {
            include $renderViewFile;
            /*eval('?>'.$content);*/
        } catch (\Exception $e) {
            $this->handleViewException($e, $obLevel);
        } catch (\Throwable $e) {
            $this->handleViewException(new \Exception($e), $obLevel);
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
    protected function handleViewException(\Exception $e, $obLevel) {
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }
        throw $e;
    }

    /**
     * 输出格式化后的时间
     * @param integer|string $time
     * @return string
     */
    public function time($time = null) {
        if (is_null($time)) {
            return null;
        }
        return Time::format($time);
    }

    /**
     * 输出是多久以前
     * @param int $time
     * @return string
     */
    public function ago($time) {
        return Time::isTimeAgo($time);
    }

    /**
     * 翻译 {}
     * @param string $message
     * @param array $param
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function t($message, $param = [], $name = null) {
        return Factory::i18n()->translate($message, $param, $name);
    }

    /**
     * 转义并截取长度
     * @param string $html
     * @param int $length
     * @return string
     */
    public function text($html, $length = 0) {
        $text = htmlspecialchars($html);
        if ($length > 0) {
            return Str::substr($text, 0, $length, true);
        }
        return $text;
    }

    /**
     * GET COMPLETE URL
     * @param null $file
     * @param null $extra
     * @param bool $rewrite
     * @return string|Uri
     * @throws Exception
     */
    public function url($file = null, $extra = null, $rewrite = true) {
        if ($extra === false && $rewrite === true) {
            list($extra, $rewrite) = [null, $extra];
        }
        return url()->to($file, $extra, true, $rewrite);
    }

    /**
     * 获取资源文件路径
     * @param $file
     * @return string
     * @throws Exception
     */
    public function asset($file) {
        return $this->url($this->factory->getAssetUri($file));
    }

    /**
     * @param $file
     * @return string|Uri
     * @throws Exception
     */
    public function assetFile($file) {
        return $this->factory->getAssetFile($file);
    }

    /**
     * 获取路径
     * @param string $name
     * @return File| string
     */
    protected function getExtendFile($name) {
        if (strpos($name, '@') === 0) {
            return $this->factory->invokeTheme('getFile', [substr($name, 1)]);
        }
        if (strpos($name, './') === 0) {
            return $this->file->getDirectory()
                ->getFile($this->factory->fileSuffix(substr($name, 2)));
        }
        if (strpos($name, '../') === 0) {
            return $this->file->getDirectory()->parent()
                ->getFile($this->factory->fileSuffix($name));
        }
        return $name;
    }

    /**
     * 加载其他文件
     * @param $name
     * @param array $data
     * @return $this
     * @throws FileException
     * @throws \Exception
     */
    public function extend($name, $data = []) {
        foreach ((array)$name as $item) {
            echo $this->factory->getView($this->getExtendFile($item))
                ->renderWithData($data);
        }
        return $this;
    }

    /**
     *
     * @param string $content
     * @param array $options
     * @param null $key
     * @return View
     */
    public function registerMetaTag($content, $options = array(), $key = null) {
        $this->factory->registerMetaTag($content, $options, $key);
        return $this;
    }

    public function registerLinkTag($url, $options = array(), $key = null) {
        $this->factory->registerLinkTag($url, $options, $key);
        return $this;
    }

    public function registerCss($css, $key = null) {
        $this->factory->registerCss($css, $key);
        return $this;
    }

    public function registerCssFile($url, $options = array(), $key = null) {
        $this->factory->registerCssFile($url, $options, $key);
        return $this;
    }

    public function registerJs($js, $position = self::HTML_FOOT, $key = null) {
        $this->factory->registerJs($js, $position, $key);
        return $this;
    }

    public function registerJsFile($url, $options = [], $key = null) {
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

    public function __call($name, $arguments) {
        if (method_exists($this->factory, $name)) {
            return call_user_func_array([$this->factory, $name], $arguments);
        }
        if ($this->factory->canTheme($name)) {
            return $this->factory->invokeTheme($name, $arguments);
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