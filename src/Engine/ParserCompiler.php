<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;

use Zodream\Disk\File;
use Zodream\Helpers\Str;

/**
{>}         <?php
{>css}
{>js}
{/>}        ?>
{> a=b}     <?php a = b?>
{| a==b}    <?php if (a==b):?>
{+ a > c}   <?php elseif (a==b):?>
{+}         <?php else:?>
{-}         <?php endif;?>
{~}         <?php for():?>
{/~}        <?php endfor;?>

{name}      <?php echo name;?>
{name.a}    <?php echo name[a];?>
{name,hh}   <?php echo isset(name) ? name : hh;?>

{for:name}                      <?php while(name):?>
{for:name,value}                <?php foreach(name as value):?>
{for:name,key=>value}           <?php foreach(name as key=>value):?>
{for:name,key=>value,length}     <?php $i = 0; foreach(name as key=>value): $i ++; if ($i > length): break; endif;?>
{for:name,key=>value,>=h}        <?php foreach(name as key=>value): if (key >=h):?>
{for:$i,$i>0,$i++}              <?php for($i; $i>0; $i++):?>
{/for}                           <?php endforeach;?>

{name=qq?v}                     <?php name = qq ? qq : v;?>
{name=qq?v:b}                   <?php name = qq ? v : b;?>

{if:name=qq}                    <?php if (name = qq):?>
{if:name=qq,hh}                 <?php if (name = qq){ echo hh; }?>
{if:name>qq,hh,gg}              <?php if (name = qq){ echo hh; } else { echo gg;}?>
{/if}                           <?php endif;?>
{else}                          <?php else:?>
{elseif}                        <?php elseif:?>

{switch:name}
{switch:name,value}
{case:hhhh>0}
{/switch}

{extend:file,hhh}

{name=value}                <?php name = value;?>
{arg,...=value,...}         <?php arg = value;. = .;?>

' string                    ''
t f bool                    true false
0-9 int                     0-9
[] array                    array()
 **/

class ParserCompiler extends CompilerEngine {

    protected string $beginTag = '{';

    protected string $endTag = '}';

    protected string $salePattern = '/\<\?(.|\r\n|\s)*\?\>/U';

    protected string|bool $blockTag = false; // 代码块开始符

    protected array $forTags = [];

    protected bool $allowFilters = true;

    /**
     * 临时替代变量
     * @var string
     */
    protected string $tplHash = 'c7a9cdc11f7d259de872d3e6ff9739be';

    protected array $funcList = [
        'header' => '$this->header',
        'footer' => '$this->footer',
    ];

    protected array $blockTags = [];

    /**
     * 设置提取标签
     * @param string $begin
     * @param string $end
     * @return $this
     */
    public function setTag(string $begin, string $end) {
        $this->beginTag = $begin;
        $this->endTag = $end;
        return $this;
    }

    /**
     * 注册方法
     * @param string $tag
     * @param $func
     * @param bool $isBlock
     * @return $this
     */
    public function registerFunc(string $tag, mixed $func = null, bool $isBlock = false): ITemplateEngine {
        $this->funcList[$tag] = empty($func) ? $tag : $func;
        if ($isBlock) {
            $this->blockTags[] = $tag;
        }
        return $this;
    }

    /**
     * 判断是否有方法
     * @param string $tag
     * @return bool
     */
    public function hasFunc(string $tag) {
        return array_key_exists($tag, $this->funcList);
    }

    /**
     * 执行方法
     * @param string $tag
     * @param string $args
     * @return bool|string
     */
    public function invokeFunc(string $tag, string $args) {
        if (!$this->hasFunc($tag)) {
            return false;
        }
        return $this->invokeFuncParse($this->funcList[$tag], $this->parseFuncParameters($args));
    }

    public function parseFuncParameters(string $args) {
        if (preg_match_all('/(\w+?)=((\[.+?])|(".+?")|(\'.+?\')|(\S+))/', $args, $matches, PREG_SET_ORDER)) {
            $args = sprintf('[%s]', implode(',', array_map(function ($item) {
                $first = substr($item[2], 0, 1);
                $value = $item[2];
                if (in_array($first, ['[', '"', '\''])) {
                } elseif ($first === '$') {
                    $value = $this->getRealVal($item[2]);
                } elseif (is_numeric($value)) {
                } else {
                    $value = sprintf('\'%s\'', $item[2]);
                }
                return sprintf('\'%s\' => %s', $item[1], $value);
            }, $matches)));
        } elseif ($args === ''
            ||
            preg_match('/^(([A-Z_]+)|(\d+)|(\'.+\')|(".+"))$/', $args, $match)) {
        } else {
            $args = implode(',', array_map(function ($item) {
                return $this->getRealVal($item);
            }, explode(',', $args)));
        }
        return $args;
    }

    protected function invokeFuncParse(mixed $func, string $args) {
        if (is_string($func)) {
            return $this->setValueToFunc($func, $args);
        }
        if (is_callable($func)) {
            return call_user_func($func, $args);
        }
        return false;
    }

    protected function setValueToFunc(string $func, string $args) {
        if (!str_contains($func, '%')) {
            return $this->echo('%s(%s)', $func, $args);
        }
        if (substr_count($func, '%') == 1) {
            return sprintf($func, $args);
        }
        return sprintf($func, ...explode(',', $args));
    }

    public function parseFile(File $file) {
        return $this->parse($file->read());
    }

    public function parse(string $content) {
        $this->initHeaders();
        $content = preg_replace($this->salePattern, '', $content);
        $pattern = sprintf('/%s[ 　]*(.+?)[ 　]*%s/i', $this->beginTag, $this->endTag);
        return preg_replace_callback($pattern, [$this, 'replaceCallback'], $content);
    }

    public function compile(string $arg): string {
        return $this->parse($arg);
    }

    protected function replaceCallback(array $match) {
        $content = $match[1];
        // 判断文本快结束符
        if ($content == '/>' && $this->blockTag !== false) {
            return $this->parseEndBlock();
        }
        // 判断是否处在文本块中
        if (empty($content) || $this->blockTag !== false) {
            return $match[0];
        }
        // 转化引入文件
        if (false !== ($line = $this->parseInclude($content))) {
            return $line;
        }
        // 转化注释
        if (false !== ($line = $this->parseNote($content))) {
            return $line;
        }
        // 转化字符串标签
        if (false !== ($line = $this->parseTag($content))) {
            return $line;
        }
        // 根据第一个字符转化
        if (false !== ($line = $this->parseFirstTag($content))) {
            return $line;
        }
        // 根据 : 转化
        if (strpos($content, ':') > 0
            && false !== ($line = $this->parseBlockTag($content))) {
            return $line;
        }
        // 转化 ?: 表达式
        if (false !== ($line = $this->parseLambda($content))) {
            return $line;
        }
        // 转化 this.
        if (false !== ($line = $this->parseThis($content))) {
            return $line;
        }
        // 转化赋值语句
        if (false !== ($line = $this->parseAssign($content))) {
            return $line;
        }
        // 转化设置变量
        if ($this->hasOrderTag($content, ['$', '='])) {
            return '<?php '.$this->parsePhp($content).';?>';
        }
        // 转化输出默认值
        if (preg_match('/^(\$[^\s,]+?),(\d+|\$.+|".+"|\'.+\')$/i', $content, $args)) {
            $args[1] = $this->parseVal($args[1]);
            return sprintf('<?=isset(%s) ? %s : %s?>', $args[1], $args[1], $args[2]);
        }
        // 转化输出值
        if (preg_match('/^(\$|this.)[_\w\.-\>\[\]\|\$]+$/i', $content)) {
            return sprintf('<?=%s?>', $this->parseVal($content));
        }
        return $match[0];
    }

    protected function parseThis(string $content) {
        if (!str_starts_with($content, 'this.')) {
            return false;
        }
        if (!str_contains($content, '=')) {
            return false;
        }
        list($tag, $val) = explode('=', substr($content, 5));
        return sprintf('<?php $this->%s = %s;?>', $tag, $this->getRealVal($val));
    }

    protected function arrayToLink(array $args, $format) {
        return implode('', array_map(function($item) use ($format) {
            return sprintf($format, $this->getRealVal($item));
        }, $args));
    }

    /**
     * 转化为方法的参数
     * @param string $content 'item=$item 1 map=$this.a'
     * @return string '['item'=>$item], 1, ['map'=>$this.a]'
     */
    protected function parseParameters(string $content): string {
        $items = [];
        $inArr = false;
        foreach (explode(' ', $content) as $block) {
            if (empty($block)) {
                continue;
            }
            if (str_contains($block, '=')) {
                list($k, $v) = explode('=', $block);
                if ($inArr) {
                    $items[] = sprintf('%s=>%s', $this->getRealVal($k), $this->getRealVal($v));
                } else {
                    $inArr = true;
                    $items[] = sprintf('[%s=>%s', $this->getRealVal($k), $this->getRealVal($v));
                }
                continue;
            }
            if ($inArr) {
                $items[] = ']';
            }
            $items[] = $this->getRealVal($block);
            $inArr = false;
        }
        if ($inArr) {
            $items[] = ']';
        }
        return implode(',', $items);
    }

    /**
     * 引入js,css 或加载文件
     * @param string $content
     * @return bool|null|string
     */
    protected function parseInclude(string $content) {
        if (!preg_match('/^(link|script|js|css|php|tpl)\s+(src|href|file)=[\'"]?([^"\'\s]+)[\'"]?/i', $content, $match)) {
            return false;
        }
        $match[1] = strtolower($match[1]);
        $files = explode(',', $match[3]);
        if ($match[1] == 'link' || $match[1] == 'css') {
            $this->addHeader(sprintf('$this%s;',
                $this->arrayToLink($files, '->registerCssFile(%s)')));
            return null;
        }
        if ($match[1] == 'js' || $match[1] == 'script') {
            $this->addHeader(sprintf('$this%s;',
                $this->arrayToLink($files, '->registerJsFile(%s)')));
            return null;
        }
        $line = '';
        foreach ($files as $file) {
            if ($match[1] == 'php') {
                $line .= sprintf('<?php include \'%s\';?>', $file);
                continue;
            }
            if ($match[1] == 'tpl') {
                $line .= sprintf('<?php $this->extend(\'%s\', %s);?>', $file, $this->parseParameters(substr($content, strlen($match[0]))));
                continue;
            }
            return false;
        }
        return $line;
    }

    /**
     * 但值进行转化，不包括通过 . 对数组读取
     * @param string $val
     * @return string
     */
    protected function getRealVal(string $val) {
        if ($val === '') {
            return '';
        }
        if (empty($val)) {
            return 'null';
        }
        if (is_numeric($val)) {
            return $val;
        }
        if ($val === 'true' || $val === 'false') {
            return $val;
        }
        $first = substr($val, 0, 1);
        if ($first === '"') {
            return $val;
        }
        if ($first === '\'') {
            $val = trim($val, '\'');
        }
        if (strpos($val, ',') > 0) {
            return $this->parseMultiVal(explode(',', $val));
        }
        if ($first === '$') {
            return $this->parseVal($val);
        }
        return sprintf('\'%s\'', $val);
    }

    protected function parseMultiVal(array $vals) {
        return implode(',', array_map([$this, 'getRealVal'], $vals));
    }

    protected function replaceVal(string $content) {
        return preg_replace_callback('/\$[A-z0-9_]+(?:\.\w+)+/i', function ($match) {
            return $this->parseVal($match[0]);
        }, $content);
    }

    /**
     * 转化值
     * @param $val
     * @return mixed|string
     */
    protected function parseVal($val) {
        if (strrpos($val, '|') !== false) {
            $filters = explode('|', $val);
            $val = array_shift($filters);
        }
        if (empty($val)) {
            return '';
        }
        if (str_starts_with($val, 'this.')) {
            $val = '$this->'.substr($val, 5);
        }
        if (str_contains($val, '.$')) {
            $all = explode('.$', $val);
            foreach ($all AS $key => $val) {
                $all[$key] = $key == 0 ? $this->makeVar($val)
                    : '[$'. $this->makeVar($val) . ']';
            }
            $p = implode('', $all);
        } else {
            $p = $this->makeVar($val);
        }
        if (empty($filters)) {
            return $p;
        }
        foreach ($filters as $filter) {
            list($tag, $values) = Str::explode($filter, ':', 2);
            if ($this->allowFilters !== true &&
                !in_array($tag, (array)$this->allowFilters)) {
                continue;
            }
            $p = sprintf('%s(%s%s)', $tag, $p, empty($values) ? '' : (','.$values));
        }
        return $p;
    }

    /**
     * 输出数组
     * @param string $val
     * @return mixed|string
     */
    protected function makeVar(string $val) {
        if (strrpos($val, '.') === false) {
            return $val;
        }
        if (preg_match('/(.+?)\[(.+)\](.*)/', $val, $match)) {
            return sprintf('%s[%s]%s', $this->makeVar($match[1]),
                $this->makeVar($match[2]),
                empty($match[2]) ? '' : $this->makeVar($match[3]));
        }
        $t = explode('.', $val);
        $p = array_shift($t);
        foreach ($t AS $val) {
            $p .= '[\'' . $val . '\']';
        }
        return $p;
    }

    /**
     * 是否包含指定顺序的字符
     * @param string $content
     * @param array|string $search
     * @return bool
     */
    protected function hasOrderTag(string $content, array|string $search) {
        $last = -1;
        foreach ((array)$search as $tag) {
            $tmpLast = $last < 0 ? 0 : $last;
            $index = $this->hasOneTag($content, $tag, $tmpLast);
            if ($index === false) {
                return false;
            }
            if ($index <= $last) {
                return false;
            }
            $last = $index;
        }
        return true;
    }

    /**
     * 是否有其中一个标签
     * @param string $content
     * @param array|string $tags
     * @param int $index
     * @return bool|int
     */
    protected function hasOneTag(string $content, array|string $tags, int $index = 0) {
        if (!is_array($tags)) {
            return strpos($content, $tags, $index);
        }
        foreach ($tags as $tag) {
            $curr = strpos($content, $tag, $index);
            if ($curr === false) {
                continue;
            }
            if ($curr <= $index) {
                continue;
            }
            return $curr;
        }
        return false;
    }

    /**
     * 注释
     * @param $content
     * @return bool|string
     */
    protected function parseNote(string $content) {
        if ((substr($content, 0, 1) == '*'
                && substr($content, -1) == '*') ||
            (substr($content, 0, 2) == '//'
                && substr($content, -2, 2) == '//')) {
            return '<?php /*'.$content.'*/ ?>';
        }
        return false;
    }

    /**
     * 转化赋值
     * @param string $content
     * @return bool|string
     */
    protected function parseAssign(string $content) {
        $eqI = strpos($content, '=');
        $dI = strpos($content, ',');
        if ($eqI === false || $dI === false || $dI >= $eqI) {
            return false;
        }
        $args = explode('=', $content, 2);
        return sprintf('<?php list(%s) = %s; ?>', $args[0], $this->parsePhp($args[1]));
    }

    protected function parseLambda(string $content) {
        if (preg_match('/(.+)=(.+)(\?|\|\|)((.*):)?(.+)/', $content, $match)) {
            return sprintf('<?php %s = %s ? %s : %s; ?>',
                $match[1], $match[2], $match[5] ?: $match[2] , $match[6]);
        }
        return false;
    }

    /**
     * 转化语句块 if for switch
     * @param $content
     * @return bool|string
     * @throws \Exception
     */
    protected function parseBlockTag($content) {
        list($tag, $content) = explode(':', $content, 2);
        if ($tag === 'request') {
            return $this->parseRequest($content);
        }
        if (str_starts_with($tag, 'request.')) {
            return $this->parseRequest($content, substr($tag, 8));
        }
        if ($tag === 'for') {
            return $this->parseFor($content);
        }
        if ($tag === 'switch') {
            return $this->parseSwitch($content);
        }
        if ($tag === 'case') {
            return sprintf('<?php case %s:?>', $content);
        }
        if ($tag === 'default') {
            return sprintf('<?php default:?>', $content);
        }
        if ($tag === 'extend') {
            return $this->parseExtend($content);
        }
        if ($tag === 'if') {
            return $this->parseIf($content);
        }
        if ($tag === 'page') {
            return $this->parsePage($content);
        }
        if ($tag === 'elseif' || $tag === 'else if') {
            return $this->parseElseIf($content);
        }
        if ($tag === 'url') {
            return $this->parseUrl($content);
        }
        if ($tag === 'layout') {
            $this->addHeader(sprintf('$this->layout = %s;', $this->getRealVal($content)));
            return null;
        }
        if ($tag === 'use') {
            $this->addHeader(sprintf('use \\%s;', trim($content, '\\')));
            return null;
        }
        if ($tag === 'break' || $tag == 'continue') {
            return sprintf('<?php %s %s; ?>', $tag, $content);
        }
        if (str_starts_with($tag, 'this.')) {
            // 解析this. => $this->
            return sprintf('<?=$this->%s(%s)?>', substr($tag, 5), $this->getRealVal($content));
        }
        if (str_starts_with($tag, '$') && substr_count($tag, '.') === 1) {
            return sprintf('<?=%s(%s)?>', str_replace('.', '->', $tag), $this->getRealVal($content));
        }
        return $this->invokeFunc($tag, $content);
    }

    protected function parseUrl(string $content) {
        $func = '<?= $this->url(%s) ?>';
        if ($this->hasFunc('url')) {
            $func = $this->funcList['url'];
        }
        return $this->invokeFuncParse($func, $this->parseUrlTag($content));
    }

    /**
     * 转化值
     * @param $key
     * @param string $tag
     * @return mixed
     * @throws \Exception
     */
    protected function parseRequest($key, $tag = 'get') {
        return call_user_func([app('request'), $tag], $this->getRealVal($key));
    }

    protected function parseElseIf($content) {
        return sprintf('<?php elseif(%s):?>', $this->replaceVal($content));
    }

    protected function parsePage($content) {
        if (!str_contains($content, ',')) {
            return sprintf('<?= %s->getLink() ?>', $content);
        }
        list($model, $options) = explode(',', $content, 2);
        return sprintf('<?= %s->getLink(%s) ?>', $model, $options);
    }

    protected function parseUrlTag($content) {
        if (empty($content)) {
            return '';
        }
        $first = substr($content, 0, 1);
        if ($first == '\'' || $first == '"') {
            return $content;
        }
        if ($first === '$') {
            return $this->makeVar($content);
        }
        $url = '';
        $i = -1;
        $args = explode(':$', $content);
        foreach ($args as $item) {
            $i ++;
            if ($i < 1) {
                $url = sprintf('\'%s\'', $item);
                continue;
            }
            if (strpos($item, ':') === false) {
                $url .= sprintf('.%s', $this->parseVal('$'.$item));
                continue;
            }
            list($key, $val) = explode(':', $item, 2);
            $url .= sprintf('.%s.\'%s\'', $this->parseVal('$'.$key), $val);
        }
        return $url;
    }

    /**
     * 加载
     * @param $content
     * @return null|string
     */
    protected function parseExtend($content) {
        if (empty($content)) {
            return null;
        }
        $files = [];
        $data = '';
        foreach (explode(',', $content) as $name) {
            if (str_contains($name, '[')) {
                $start = strpos($content, '[');
                $data = substr($content, $start,
                    strpos($content, ']', $start) - $start - 1);
                break;
            }
            if (!str_starts_with($name, '$')) {
                $name = sprintf('\'%s\'', trim($name, '\'"'));
            }
            $files[] = $name;
        }
        return sprintf('<?php $this->extend([%s], [%s]);?>',
            implode(',', $files), $data);
    }

    /**
     * 转化 if
     * @param $content
     * @return string
     */
    protected function parseIf($content) {
        $args = explode(',', $content);
        $length = count($args);
        if ($length > 1 && strpos($args[0], ':') > 0) {
            $args = [$content];
            $length = 1;
        }
        $args[0] = $this->parsePhp($args[0]);
        if ($length == 1) {
            return '<?php if('.$args[0].'):?>';
        }
        if ($length == 2) {
            return sprintf('<?php if (%s){ echo %s; }?>', $args[0], $args[1]);
        }
        return sprintf('<?php if (%s){ echo %s; } else { echo %s;}?>',
            $args[0], $args[1], $args[2]);
    }

    /**
     * 转化switch
     * @param $content
     * @return string
     */
    protected function parseSwitch($content) {
        $args = explode(',', $content);
        if (count($args) == 1) {
            return sprintf('<?php switch(%s):?>', $this->parsePhp($content));
        }
        return sprintf('<?php switch(%s): case %s:?>', $this->parsePhp($args[0]), $args[1]);
    }

    /**
     * 转化 for
     * @param $content
     * @return string
     */
    protected function parseFor(string $content) {
        $args = str_contains($content, ';') ?
            explode(';', $content) :
            $this->parseComma($content);
        $length = count($args);
        $args[0] = $this->replaceVal($args[0]);
        if ($length == 1) {
            $this->forTags[] = 'while';
            return '<?php while('.$args[0].'):?>';
        }
        if ($length == 2) {
            $this->forTags[] = 'foreach';
            return sprintf('<?php if (!empty(%s)): foreach(%s as %s):?>',
                $args[0],
                $args[0], $args[1] ?: '$item');
        }
        $tag = substr(trim($args[2]), 0, 1);
        if (in_array($tag, ['<', '>', '='])) {
            list($key, $item) = $this->getForItem($args[1]);
            $this->forTags[] = 'foreach';
            return sprintf('<?php if (!empty(%s)): foreach(%s as %s=>%s): if (!(%s %s)): break; endif;?>',
                $args[0],
                $args[0], $key, $item, $key,  $args[2]);
        }
        if ($this->isForTag($args)) {
            $this->forTags[] = 'for';
            return sprintf('<?php for(%s; %s; %s): ?>',
                $args[0],
                $args[1],
                $args[2]);
        }

        $this->forTags[] = 'foreach';
        return sprintf('<?php if (!empty(%s)):  $i = 0; foreach(%s as %s): $i ++; if ($i > %s): break; endif;?>',
            $args[0],
            $args[0],
            $args[1]  ?: '$item',
            $args[2]);
    }

    protected function parseComma($content) {
        if (!str_contains($content, ',')) {
            return $content;
        }
        if (!str_contains($content, '(')
            || !str_contains($content, ')')) {
            return explode(',', $content);
        }
        $args = explode(')', $content);
        $items = [$args[0]];
        for($i = 1; $i < count($args); $i ++) {
            $end = count($items) - 1;
            $lines = explode(',', $args[$i]);
            $items[$end] .= ')'.$lines[0];
            for ($j = 1; $j < count($lines); $j ++) {
                $items[] = $lines[$j];
            }
        }
        return $items;
    }

    protected function isForTag($args) {
        return $this->isJudge($args[1]) && $this->hasTag($args[2], ['+', '-', '*', '/', '%']);
    }

    /**
     * 是否是判断语句
     * @param $str
     * @return bool
     */
    protected function isJudge($str) {
        return $this->hasTag($str, ['<', '>', '==']);
    }

    /**
     * 是否包含字符
     * @param $str
     * @param $search
     * @return bool
     */
    protected function hasTag($str, $search) {
        foreach ((array)$search as $tag) {
            if (strpos($str, $tag) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function getForItem($content) {
        $key = '$key';
        $item = $content;
        if (str_contains($content, '=>')) {
            list($key, $item) = explode('=>', $content);
        } elseif (str_contains($content, ' ')) {
            list($key, $item) = explode(' ', $content);
        }
        if (empty($key)) {
            $key = '$key';
        }
        if (empty($item)) {
            $item = '$item';
        }
        return [$key, $item];
    }

    protected function parseTag($content) {
        if ($content == 'break' || $content == 'continue') {
            return sprintf('<?php %s; ?>', $content);
        }
        if ($content == 'else' || $content == '+') {
            return '<?php else: ?>';
        }
        if ($content == 'forelse' && end($this->forTags) == 'foreach') {
            $this->forTags[count($this->forTags) - 1] = 'if';
            return '<?php endforeach; else: ?>';
        }
        if ($content == '-') {
            return '<?php endif; ?>';
        }
        return false;
    }

    /**
     * 根据第一个字符转化
     * @param $content
     * @return bool|string
     */
    protected function parseFirstTag($content) {
        $first = substr($content, 0, 1);
        if ($first === '#') {
            // 返回原句
            return sprintf('%s%s%s', $this->beginTag, substr($content, 1), $this->endTag);
        }
        if ($first === '>') {
            return $this->parseBlock(substr($content, 1));
        }
        if ($first === '/') {
            return $this->parseEndTag(substr($content, 1));
        }
        if ($first === '|') {
            return $this->parseIf(substr($content, 1));
        }
        if ($first === '+') {
            return $this->parseElseIf(substr($content, 1));
        }
        if ($first === '~') {
            return $this->parseFor(substr($content, 1));
        }
        // 直接输出
        if ($first === '=') {
            return sprintf('<?=%s?>', $this->parseVal(substr($content, 1)));
        }
        if ($first === '@') {
            //
            return $this->parseScriptRegister($content);
        }
        return false;
    }

    protected function parseScriptRegister(string $content) {
        $splitIndex = strpos($content, ':');
        if ($splitIndex > 1) {
            $func = substr($content, 1, $splitIndex - 1);
            if ($this->hasFunc($func)) {
                $content = $this->invokeFunc($func, substr($content, $splitIndex + 1));
            }
        }
        if (str_ends_with($content, '.js')) {
            $this->addHeader(sprintf('$this->registerJsFile(\'%s\');', $content));
            return null;
        }
        if (str_ends_with($content, '.css')) {
            $this->addHeader(sprintf('$this->registerCssFile(\'%s\');', $content));
            return null;
        }
        return false;
    }

    protected function parseEndTag($content) {
        if ($content == '|' || $content == 'if') {
            return '<?php endif;?>';
        }
        if ($content == '~' || $content == 'for') {
            return $this->parseEndForTag();
        }
        if ($content == '*' || $content == 'switch') {
            return '<?php endswitch ?>';
        }
        if (!in_array($content, $this->blockTags)) {
            return false;
        }
        $func = $this->funcList[$content];
        if (is_string($func)) {
            return sprintf('<?php %s(-1);?>', $func);
        }
        if (is_callable($func)) {
            return call_user_func($func, -1);
        }
        return false;
    }

    protected function parseEndForTag() {
        if (count($this->forTags) == 0) {
            return false;
        }
        $tag = array_pop($this->forTags);
        if ($tag == 'foreach') {
            return '<?php endforeach;endif;?>';
        }
        return '<?php end'.$tag.';?>';
    }

    /**
     * @return string
     */
    protected function parseEndBlock() {
        list($tag, $this->blockTag) = [$this->blockTag, false];
        if ($tag == 'php' || $tag === '') {
            return '?>';
        }
        if ($tag == 'js' || $tag == 'css') {
            return sprintf(PHP_EOL.'%s;'.PHP_EOL.' $this->register%s($%s_%s);?>', strtoupper($tag), ucfirst($tag), $tag, $this->tplHash);
        }
        if ($tag == 'text') {
            return null;
        }
        return null;
    }

    /**
     * 转化语句块
     * @param $content
     * @return string
     */
    protected function parseBlock($content) {
        if ($content == '' || $content == 'php') {
            $this->blockTag = 'php';
            return '<?php ';
        }
        $args = '';
        if (strpos($content, ':')) {
            list($content, $args) = explode(':', $content, 2);
            $args = rtrim($args, ';').';'.PHP_EOL;
        }
        if ($content == 'js' || $content == 'script') {
            $this->blockTag = 'js';
            return sprintf('<?php %s$js_%s = <<<JS', $args, $this->tplHash);
        }
        if ($content == 'css' || $content == 'style') {
            $this->blockTag = 'css';
            return sprintf('<?php %s$css_%s = <<<CSS', $args, $this->tplHash);
        }
        if ($content == 'text') {
            $this->blockTag = 'text';
            return null;
        }
        return sprintf('<?php %s:%s; ?>', $content, $args);
    }

    public function parsePhp($content) {
        $content = preg_replace_callback('/(([a-z]+)\:(.*)|([a-z]+)\(([^\(\)]*)\))/i',function($match) {
            $tag = $match[2];
            $args = $match[3];
            if (empty($tag)) {
                $tag = $match[4];
                $args = $match[5];
            }
            if (!$this->hasFunc($tag) || in_array($tag, $this->blockTags)) {
                return $match[0];
            }
            return sprintf('%s(%s)',
                $this->funcList[$tag], $this->parseFuncParameters($args));
        }, $content);
        return $this->replaceVal($content);
    }

    /**
     * 输出代码
     * @param $line
     * @param mixed ...$args
     * @return string
     */
    public function echo($line, ...$args) {
        if (!empty($args)) {
            $line = sprintf($line, ...$args);
        }
        return sprintf('<?= %s ?>', $line);
    }

    /**
     * 代码块
     * @param $line
     * @param mixed ...$args
     * @return string
     */
    public function block($line, ...$args) {
        if (!empty($args)) {
            $line = sprintf($line, ...$args);
        }
        return sprintf('<?php %s ?>', $line);
    }
}