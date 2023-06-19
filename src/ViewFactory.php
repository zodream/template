<?php
declare(strict_types=1);
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/3
 * Time: 9:48
 */
use Zodream\Helpers\Time;
use Zodream\Infrastructure\Concerns\ConfigTrait;
use Zodream\Infrastructure\Caching\FileCache;
use Zodream\Disk\Directory;
use Zodream\Disk\File;
use Zodream\Template\Concerns\RegisterAssets;
use Zodream\Template\Concerns\RegisterTheme;
use Zodream\Disk\FileException;
use Zodream\Infrastructure\Base\MagicObject;
use Zodream\Template\Engine\ITemplateCompiler;
use Zodream\Template\Engine\ITemplateEngine;
use Zodream\Template\Engine\ITemplateExecutor;
use Zodream\Template\Events\ViewCompiled;

class ViewFactory extends MagicObject {

    use ConfigTrait, RegisterAssets, RegisterTheme;

    protected array $configs = [];

    protected string $configKey = 'view';


    /**
     * 当前模块的文件夹
     * @var Directory
     */
    protected $directory;
    /**
     * 原始根文件夹
     * @var Directory
     */
    protected $rootDirectory;

    /**
     * @var ITemplateCompiler|ITemplateExecutor|null
     */
    protected ITemplateCompiler|ITemplateExecutor|null $engine = null;

    protected string|File $layout = '';

    /**
     * @var FileCache
     */
    protected $cache;

    protected $assetsDirectory;

    protected $defaultFile;

    /**
     * 模板是允许缓存
     * @var bool
     */
    protected bool $templateCacheable = true;
    
    public function __construct() {
        $this->loadConfigs([
            'driver' => '',
            'directory' => 'UserInterface/'.app('app.module'),
            'suffix' => '.php',
            'asset_directory' => 'assets',
            'cache' => 'data/views'
        ]);
        if (!empty($this->configs['driver']) && class_exists($this->configs['driver'])) {
            $this->setEngine($this->configs['driver']);
        }
        if (isset($this->configs['theme'])) {
            $this->registerTheme($this->configs['theme']);
        }
        $this->setAssetsDirectory($this->configs['asset_directory']);
        $this->cache = new FileCache();
        $this->cache->setDirectory($this->configs['cache'])
            ->setConfigs([
                'extension' => '.phtml'
            ]);
        $this->setDirectory($this->configs['directory']);
        $this->set('__zd', $this);
        if (!app()->isDebug()
            && isset($this->configs['assets'])
            && is_array($this->configs['assets'])) {
            $this->registerAssetsMap($this->configs['assets']);
        }
    }

    public function config(string $name) {
        return $this->configs[$name];
    }

    /**
     * 设置文件夹
     * @param $directory
     * @return $this
     */
    public function setDirectory($directory) {
        if (!$directory instanceof Directory) {
            $directory = app_path()->childDirectory($directory);
        }
        if (empty($this->rootDirectory)) {
            $this->rootDirectory = $directory->parent();
        }
        $this->directory = $directory;
        return $this;
    }

    /**
     * @return Directory
     */
    public function getDirectory(): Directory {
        return $this->directory;
    }

    /**
     * @param string|File $layout
     * @return ViewFactory
     */
    public function setLayout(string|File $layout) {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @param mixed $defaultFile
     */
    public function setDefaultFile($defaultFile)
    {
        $this->defaultFile = $defaultFile;
        return $this;
    }

    /**
     * @param ITemplateCompiler|ITemplateExecutor|string $engine
     * @return ViewFactory
     */
    public function setEngine(ITemplateCompiler|ITemplateExecutor|string $engine, ?bool $cacheable = null) {
        if (is_bool($cacheable)) {
            $this->templateCacheable = $cacheable;
        }
        $this->engine = is_string($engine) ? new $engine($this) : $engine;
        return $this;
    }

    /**
     * @return ITemplateCompiler|ITemplateExecutor|null
     */
    public function getEngine(): ITemplateCompiler|ITemplateExecutor|null {
        return $this->engine;
    }

    /**
     * 获取或添加后缀
     * @param null $name
     * @return string
     */
    public function fileSuffix($name = null) {
        return $name.$this->configs['suffix'];
    }

    /**
     * 判断模板文件是否存在
     * @param string $file
     * @return bool
     */
    public function exist($file) {
        return $this->directory->hasFile($this->fileSuffix($file));
    }

    /**
     * 获取完整的路径
     * @param string $file
     * @return File
     * @throws FileException
     */
    public function getCompleteFile(string $file) {
        $first = substr($file, 0, 1);
        if ($first !== '@') {
            return $this->directory->childFile($file);
        }
        if (!str_contains($file, '/')) {
            return $this->rootDirectory->childFile($file);
        }
        list($prefix, $path) = explode('/', $file, 2);
        if ($prefix === '@root') {
            return $this->rootDirectory->childFile($path);
        }
        throw new FileException($file);
    }

    /**
     * MAKE VIEW
     * @param string|File $file
     * @return View
     * @throws FileException
     * @throws \Exception
     */
    protected function renderView(File|string $file) {
        if (!$file instanceof File && is_file($file)) {
            $file = new File($file);
        }
        if (!$file instanceof File) {
            $file = $this->getCompleteFile($this->fileSuffix($file));
        }
        if (!$file->exist()) {
            throw new FileException($file);
        }
        if ($this->engine instanceof ITemplateEngine) {
            /** IF IT HAS ENGINE*/
            $cacheFile = $this->cache->getCacheFile($file->getFullName());
            if (!$this->templateCacheable || !$cacheFile->exist() || $cacheFile->modifyTime() < $file->modifyTime()) {
                $start = Time::millisecond();
                $this->engine->compileFile($file, $cacheFile);
                event(new ViewCompiled($file, $cacheFile, Time::elapsedTime($start)));
            }
            return new View($this, $file, $cacheFile);
        }
        if ($this->engine instanceof ITemplateCompiler) {
            /** IF IT HAS ENGINE*/
            $cacheFile = $this->cache->getCacheFile($file->getFullName());
            if (!$this->templateCacheable || !$cacheFile->exist() || $cacheFile->modifyTime() < $file->modifyTime()) {
                $start = Time::millisecond();
                $cacheFile->write($this->engine->compile($file->read()));
                event(new ViewCompiled($file, $cacheFile, Time::elapsedTime($start)));
            }
            return new View($this, $file, $cacheFile);
        }
        return new View($this, $file);
    }

    /**
     * GET HTML
     * @param string|File $file
     * @param array $data
     * @param callable|null $callback
     * @return string
     */
    public function render(string|File $file, array $data = [], ?callable $callback = null): mixed {
        $content = $this->renderJust(empty($file) ? $this->defaultFile : $file, $data, $callback);
        $layout = $this->findLayoutFile();
        if (empty($layout)) {
            return $content;
        }
        $layoutData = $this->merge($data);
        $layoutData['content'] = $content;
        return $this->renderJust($layout, $layoutData);
    }

    /**
     * 仅生成文件，不调用公共模板
     * @param string|File $file
     * @param array $data
     * @param callable|null $callback
     * @return mixed|string|null
     * @throws FileException
     */
    public function renderJust(string|File $file, array $data = [], ?callable $callback = null): mixed {
        if ($this->engine instanceof ITemplateExecutor) {
            $content = $this->engine->execute((string)$file, $this->merge($data));
            if (!empty($callback)) {
                return call_user_func($callback, $callback);
            }
            return $content;
        }
        $this->setAttribute($data);
        return $this->getView(empty($file) ? $this->defaultFile : $file)
            ->renderWithData($data, $callback);
    }

    /**
     * 临时使用,一些设置需要回滚
     * @param callable $cb
     * @return mixed
     * @throws FileException
     */
    public function temporary(callable $cb): mixed {
        $folder = $this->directory;
        $layout = $this->layout;
        $engine = $this->engine;
        $configs = $this->configs;
        $res = call_user_func($cb, $this);
        $this->configs = $configs;
        $this->directory = $folder;
        $this->layout = $layout;
        $this->engine = $engine;
        return $res;
    }

    public function findLayoutFile() {
        if (empty($this->layout)) {
            return false;
        }
        if ($this->layout instanceof File || is_file($this->layout)) {
            return $this->layout;
        }
        $code = substr($this->layout, 0, 1);
        if ($code == '/') {
            return $this->layout;
        }
        return 'layouts/'.$this->layout;
    }

    /**
     * @param $file
     * @return View
     * @throws FileException
     * @throws \Exception
     */
    public function getView($file) {
        return $this->moveRegisterAssets()
            ->renderView($file);
    }



    public function clear() {
        $this->clearAttribute();
        $this->clearAssets();
    }
}