<?php
declare(strict_types=1);
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

abstract class CompilerEngine implements ITemplateEngine {
    /**
     * @var ViewFactory
     */
    protected ?ViewFactory $factory;
    /**
     * @var File
     */
    protected ?File $file;

    /**
     * 不允许直接打开视图缓存文件
     * @var string
     */
    const DIE_HEADER = "defined('APP_DIR') or exit();\r\nuse Zodream\Template\View;\r\n/** @var \$this View */";
    // 初始化头部
    protected array $headers = [
        self::DIE_HEADER
    ];

    public function __construct(ViewFactory $factory = null) {
        if (empty($factory)) {
            return;
        }
        $this->factory = $factory;
    }

    /**
     * 添加头部
     * @param array|string $lines
     * @return $this
     */
    public function addHeader(array|string $lines): static {
        $this->headers = array_merge($this->headers, (array)$lines);
        return $this;
    }

    /**
     * 初始化头部
     */
    protected function initHeaders(): void {
        $this->headers = [
            self::DIE_HEADER
        ];
        if (!empty($this->file)) {
            $this->headers[] = sprintf('$this->setFile(\'%s\');', $this->file->getFullName());
        }
    }

    /**
     *
     * @return string
     */
    protected function compileHeaders(): string {
        return '<?php'.PHP_EOL.implode(PHP_EOL, $this->headers).PHP_EOL.'?>'.PHP_EOL;
    }

    /**
     * COMPILER FILE TO CACHE FILE
     *
     * @param File $file
     * @param File $cacheFile
     * @return bool
     */
    public function compileFile(File $file, File $cacheFile): bool {
        $this->file = $file;
        $content = $this->compile($file->read());
        return $cacheFile->write($this->compileHeaders().$content) !== false;
    }

    /**
     * COMPILE STRING
     * @param string $value
     * @return string
     */
    abstract public function compile(string $value): string;
}