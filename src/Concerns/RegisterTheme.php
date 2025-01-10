<?php
declare(strict_types=1);
namespace Zodream\Template\Concerns;

use Zodream\Template\ITheme;

trait RegisterTheme {

    /**
     * @var ITheme|null
     */
    protected ITheme|null $theme = null;


    public function registerTheme(string|ITheme $theme) {
        $this->theme = is_string($theme) ? new $theme($this) : $theme;
        return $this;
    }

    /**
     * @return ITheme
     */
    public function getTheme(): ITheme {
        return $this->theme;
    }

    /**
     * @param string $func
     * @return bool
     */
    public function canTheme(string $func): bool {
        return $this->theme && method_exists($this->theme, $func);
    }

    /**
     * @param string $func
     * @param array $vars
     * @return mixed
     */
    public function invokeTheme(string $func, array $vars): mixed {
        if (!$this->theme) {
            return null;
        }
        return call_user_func_array([$this->theme, $func], $vars);
    }
}