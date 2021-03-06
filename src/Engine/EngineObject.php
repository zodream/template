<?php
namespace Zodream\Template\Engine;

/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/16
 * Time: 15:20
 */
use Zodream\Disk\File;

interface EngineObject {
    /**
     * COMPILER FILE TO CACHE FILE
     *
     * @param File $file
     * @param File $cacheFile
     * @return bool
     */
    public function compile(File $file, File $cacheFile);
}