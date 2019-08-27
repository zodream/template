<?php
namespace Zodream\Template\Events;

use Zodream\Disk\File;
use Zodream\Template\View;

class ViewRendered {
    /**
     * @var File
     */
    public $file;

    /**
     * @var View
     */
    public $view;

    /**
     * @var float
     */
    public $time;

    public function __construct(File $file, View $view, $time = null) {
        $this->file = $file;
        $this->view = $view;
        $this->time = $time;
    }
}