<?php
namespace Zodream\Template\Concerns;

use Zodream\Template\Theme;

trait RegisterTheme {

    /**
     * @var Theme
     */
    protected $theme;


    public function registerTheme($theme) {
        $this->theme = is_string($theme) ? new $theme($this) : $theme;
        return $this;
    }

    /**
     * @return Theme
     */
    public function getTheme() {
        return $this->theme;
    }

    /**
     * @param $func
     * @return bool
     */
    public function canTheme($func) {
        return $this->theme && method_exists($this->theme, $func);
    }

    /**
     * @param $func
     * @param array $vars
     * @return mixed
     */
    public function invokeTheme($func, array $vars) {
        if (!$this->theme) {
            return;
        }
        return call_user_func_array([$this->theme, $func], $vars);
    }
}