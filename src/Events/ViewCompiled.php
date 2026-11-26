<?php
declare(strict_types=1);
namespace Zodream\Template\Events;


use Zodream\Disk\File;

class ViewCompiled {

    public function __construct(
        public File $file,
        public File $cacheFile,
        public float $time = 0) {
    }
}