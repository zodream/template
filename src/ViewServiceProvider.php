<?php
declare(strict_types=1);
namespace Zodream\Template;

use Zodream\Infrastructure\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider {

    public function register(): void {
        $this->app->singletonIf(ViewFactory::class);
        $this->app->alias(ViewFactory::class, 'view');
    }
}