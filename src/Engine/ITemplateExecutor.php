<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;


use Zodream\Template\ViewFactory;

/**
 * 执行代码，运行模板，直接获得最终内容
 */
interface ITemplateExecutor {

    public function __construct(ViewFactory $factory);

    public function execute(string $fileName, array $data = []): string;
}