<?php
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2016/12/25
 * Time: 10:28
 */
use Zodream\Disk\File;
use Zodream\Infrastructure\Http\Request;
use Zodream\Service\Factory;

class AssetFile extends File {

    protected $realFile;

    protected $url;

    public function __construct($file) {
        parent::__construct($file);
        $this->getRealFile();
    }

    protected function getRealFile(){
        $root = Factory::public_path();
        $script = Factory::root();
        if ($root->isParent($this->directory)) {
            $this->realFile = $this->fullName;
            $this->url = $this->getRelative($script);
            return;
        }

        $md5 = md5($this->fullName, true);
        $this->url = sprintf(
            'assets/%s/%s.%s',
            $root,
            substr($md5, 0, 8),
            substr($md5, 8),
            $this->extension
        );
        $this->realFile = $script->file($this->url);
    }

    public function create() {
        if (is_file($this->realFile)) {
            return true;
        }
        $dir = dirname($this->realFile);
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return $this->copy($this->realFile);
    }

    /**
     * GET URL IN WEB ROOT
     * @return bool|string
     */
    public function getUrl() {
        return $this->url;
    }
}