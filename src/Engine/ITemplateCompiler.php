<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;

use Zodream\Template\ViewFactory;

/**
 * 编译器，把模板编译成另一种语言
 */
interface ITemplateCompiler {

    public function __construct(ViewFactory $factory);

    public function compile(string $value): string;

    /**
     * 注册自定义解析方法
     * @param string $tag
     * @param mixed|null $func
     * @param bool $isBlock
     * @return ITemplateCompiler
     */
    public function registerFunc(string $tag, mixed $func = null, bool $isBlock = false): ITemplateCompiler;

    public function disallowFunc(string $func): ITemplateCompiler;
}