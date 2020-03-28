<?php
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/3
 * Time: 9:48
 */
use Zodream\Helpers\Time;
use Zodream\Infrastructure\Traits\ConfigTrait;
use Zodream\Service\Factory;
use Zodream\Infrastructure\Caching\FileCache;
use Zodream\Disk\Directory;
use Zodream\Disk\File;
use Zodream\Template\Concerns\RegisterAssets;
use Zodream\Template\Concerns\RegisterTheme;
use Zodream\Template\Engine\EngineObject;
use Zodream\Disk\FileException;
use Zodream\Infrastructure\Base\MagicObject;
use Zodream\Template\Events\ViewCompiled;

class ViewFactory extends MagicObject {

    use ConfigTrait, RegisterAssets, RegisterTheme;

    protected $configs = [];

    protected $configKey = 'view';


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
     * @var EngineObject
     */
    protected $engine;

    protected $layout = false;

    /**
     * @var FileCache
     */
    protected $cache;

    protected $assetsDirectory;
    
    public function __construct() {
        $this->loadConfigs([
            'driver' => null,
            'directory' => 'UserInterface/'.app('app.module'),
            'suffix' => '.php',
            'asset_directory' => 'assets',
            'cache' => 'data/views'
        ]);
        if (class_exists($this->configs['driver'])) {
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

    /**
     * 设置文件夹
     * @param $directory
     * @return $this
     */
    public function setDirectory($directory) {
        if (!$directory instanceof Directory) {
            $directory = Factory::root()->childDirectory($directory);
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
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * @param string $layout
     * @return ViewFactory
     */
    public function setLayout($layout) {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @param EngineObject $engine
     * @return ViewFactory
     */
    public function setEngine($engine) {
        $this->engine = is_string($engine) ? new $engine($this) : $engine;
        return $this;
    }

    /**
     * @return EngineObject
     */
    public function getEngine() {
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
     * @param $file
     * @return File
     * @throws FileException
     */
    public function getCompleteFile($file) {
        $first = substr($file, 0, 1);
        if ($first !== '@') {
            return $this->directory->childFile($file);
        }
        if (strpos($file, '/') === false) {
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
    public function make($file) {
        if (!$file instanceof File && is_file($file)) {
            $file = new File($file);
        }
        if (!$file instanceof File) {
            $file = $this->getCompleteFile($this->fileSuffix($file));
        }
        if (!$file->exist()) {
            throw new FileException($file);
        }
        if (!$this->engine instanceof EngineObject) {
            return new View($this, $file);
        }
        /** IF HAS ENGINE*/
        $cacheFile = $this->cache->getCacheFile($file->getFullName());
        if (!$cacheFile->exist() || $cacheFile->modifyTime() < $file->modifyTime()) {
            $start = Time::millisecond();
            $this->engine->compile($file, $cacheFile);
            event(new ViewCompiled($file, $cacheFile, Time::elapsedTime($start)));
        }
        return new View($this, $file, $cacheFile);
    }

    /**
     * GET HTML
     * @param string|File $file
     * @param array $data
     * @param callable $callback
     * @return string
     * @throws FileException
     * @throws \Exception
     */
    public function render($file, array $data = array(), callable $callback = null) {
        $content = $this->setAttribute($data)
            ->getView($file)->render($callback);
        $layout = $this->findLayoutFile();
        if (empty($layout)) {
            return $content;
        }
        return $this->getView($layout)
            ->renderWithData(['content' => $content]);
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
            ->make($file);
    }



    public function clear() {
        $this->clearAttribute();
        $this->clearAssets();
    }
}