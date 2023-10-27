<?php
declare(strict_types=1);
namespace Zodream\Template\Events;

use Zodream\Disk\File;
use Zodream\Template\View;

class ViewRendered {

    public function __construct(
        public File $file,
        public File $compiledFile,
        public View $view,
        public float $time = 0) {
    }
}