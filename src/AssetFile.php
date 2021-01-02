<?php
declare(strict_types=1);
namespace Zodream\Template;

/**
 * Created by PhpStorm.
 * User: ZoDream
 * Date: 2016/12/25
 * Time: 10:28
 */

use Exception;
use Zodream\Disk\File;

class AssetFile extends File {

    /**
     * @var string
     */
    protected $realFile;

    /**
     * @var string
     */
    protected $url;

    public function __construct($file) {
        parent::__construct($file);
        try {
            $this->getRealFile();
        } catch (Exception $e) {
        }
    }

    /**
     * @throws Exception
     */
    protected function getRealFile(){
        $root = public_path();
        $script = app_path();
        if ($root->isParent($this->directory)) {
            $this->realFile = $this->fullName;
            $this->url = $this->getRelative($script);
            return;
        }

        $md5 = md5($this->fullName, true);
        $this->url = sprintf(
            '%s/assets/%s/%s.%s',
            $root,
            substr($md5, 0, 8),
            substr($md5, 8),
            $this->getExtension()
        );
        $this->realFile = $script->file($this->url);
    }

    public function create(): bool {
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
     * @return string
     */
    public function getUrl(): string {
        return $this->url;
    }
}