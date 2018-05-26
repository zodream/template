<?php
namespace Zodream\Template;


use Zodream\Disk\Directory;
use Zodream\Disk\File;

abstract class Theme {

    /**
     * @return Directory
     */
    abstract public function getRoot();

    /**
     * @param $name
     * @return File
     */
    abstract public function getFile($name);
}