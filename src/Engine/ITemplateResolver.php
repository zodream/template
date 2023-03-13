<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;

use Zodream\Template\ViewFactory;

/**
 * 解析器, 把模板拆成一个一个的 Token
 */
interface ITemplateResolver {

    public function __construct(ViewFactory $factory);

    public function parse(string $value): array;
}