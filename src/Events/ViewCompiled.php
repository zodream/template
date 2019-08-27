<?php
namespace Zodream\Template\Events;


use Zodream\Disk\File;

class ViewCompiled {

    /**
     * @var File
     */
    public $file;

    /**
     * @var File
     */
    public $cacheFile;

    /**
     * @var float
     */
    public $time;

    public function __construct(File $file, File $cacheFile, $time = null) {
        $this->file = $file;
        $this->cacheFile = $cacheFile;
        $this->time = $time;
    }
}