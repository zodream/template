<?php
namespace Zodream\Template\Engine;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/16
 * Time: 10:24
 */
use SebastianBergmann\CodeCoverage\Report\PHP;
use Zodream\Template\ViewFactory;
use Zodream\Disk\File;

abstract class CompilerEngine implements EngineObject {
    /**
     * @var ViewFactory
     */
    protected $factory;

    /**
     * 不允许直接打开视图缓存文件
     * @var string
     */
    const DIE_HEADER = "defined('APP_DIR') or exit();\r\n/** @var \$this \Zodream\Template\View */";
    // 初始化头部
    protected $headers = [
        self::DIE_HEADER
    ];

    public function __construct(ViewFactory $factory) {
        $this->factory = $factory;
    }

    /**
     * 添加头部
     * @param $lines
     * @return $this
     */
    protected function addHeader($lines) {
        $this->headers = array_merge($this->headers, (array)$lines);
        return $this;
    }

    /**
     * 初始化头部
     */
    protected function initHeaders() {
        $this->headers = [self::DIE_HEADER];
    }

    /**
     *
     * @return string
     */
    protected function compileHeaders() {
        return '<?php'.PHP_EOL.implode(PHP_EOL, $this->headers).PHP_EOL.'?>'.PHP_EOL;
    }

    /**
     * COMPILER FILE TO CACHE FILE
     *
     * @param File $file
     * @param File $cacheFile
     * @return bool
     */
    public function compile(File $file, File $cacheFile) {
        $content = $this->compileString($file->read());
        return $cacheFile->write($this->compileHeaders().$content) !== false;
    }

    /**
     * COMPILE STRING
     * @param string $arg
     * @return string
     */
    abstract public function compileString($arg);
}