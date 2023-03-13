<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/16
 * Time: 15:20
 */
use Zodream\Disk\File;

interface ITemplateEngine extends ITemplateCompiler {
    /**
     * COMPILER FILE TO CACHE FILE
     *
     * @param File $file
     * @param File $cacheFile
     * @return bool
     */
    public function compileFile(File $file, File $cacheFile): bool;
}