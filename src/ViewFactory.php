<?php
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/8/3
 * Time: 9:48
 */
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

class ViewFactory extends MagicObject {

    use ConfigTrait, RegisterAssets, RegisterTheme;

    protected $configs = [];

    protected $configKey = 'view';


    /**
     * @var Directory
     */
    protected $directory;

    /**
     * @var EngineObject
     */
    protected $engine;


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
            'assets' => 'assets',
            'cache' => 'data/views'
        ]);
        if (class_exists($this->configs['driver'])) {
            $this->setEngine($this->configs['driver']);
        }
        if (isset($this->configs['theme'])) {
            $this->registerTheme($this->configs['theme']);
        }
        $this->setAssetsDirectory($this->configs['assets']);
        $this->cache = new FileCache();
        $this->cache->setDirectory($this->configs['cache'])
            ->setConfigs([
                'extension' => '.phtml'
            ]);
        $this->setDirectory($this->configs['directory']);
        $this->set('__zd', $this);
    }

    
    public function setDirectory($directory) {
        if (!$directory instanceof Directory) {
            $directory = Factory::root()->childDirectory($directory);
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
     * MAKE VIEW
     * @param string|File $file
     * @return View
     * @throws FileException
     * @throws \Exception
     */
    public function make($file) {
        if (is_file($file)) {
            $file = new File($file);
        }
        if (!$file instanceof File) {
            $file = $this->directory->childFile($this->fileSuffix($file));
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
            $this->engine->compile($file, $cacheFile);
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
        return $this->setAttribute($data)
            ->getView($file)
            ->render($callback);
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