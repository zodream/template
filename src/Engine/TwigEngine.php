<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;
/**
 * Created by PhpStorm.
 * User: zx648
 * Date: 2016/7/16
 * Time: 15:51
 */
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Zodream\Template\ViewFactory;

class TwigEngine implements ITemplateExecutor {

    protected Environment $compiler;

    public function __construct(ViewFactory $factory) {
        $loader = new FilesystemLoader((string)$factory->getDirectory());
        $this->compiler = new Environment($loader, [
            'cache' => (string)app_path()->childDirectory($factory->config('cache')),
            'debug' => app()->isDebug()
        ]);
    }

    public function execute(string $fileName, array $data = []): string {
        return $this->compiler->render($fileName, $data);
    }
}