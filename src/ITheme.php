<?php
declare(strict_types=1);
namespace Zodream\Template;


use Zodream\Disk\Directory;
use Zodream\Disk\File;

interface ITheme {

    /**
     * @return Directory
     */
    public function getRoot(): Directory;

    /**
     * @param string $name
     * @return File
     */
    public function getFile(string $name): File;
}