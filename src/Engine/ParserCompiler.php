<?php
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

    protected $beginTag = '{';

    protected $endTag = '}';

    protected $salePattern = '/\<\?(.|\r\n|\s)*\?\>/U';

    protected $blockTag = false; // 代码块开始符

    protected $forTags = [];

    protected $allowFilters = true;

    /**
     * 临时替代变量
     * @var string
     */
    protected $tplHash = 'c7a9cdc11f7d259de872d3e6ff9739be';

    protected $funcList = [
        'header' => '$this->header',
        'footer' => '$this->header',
    ];

    /**
     * 设置提取标签
     * @param $begin
     * @param $end
     * @return $this
     */
    public function setTag($begin, $end) {
        $this->beginTag = $begin;
        $this->endTag = $end;
        return $this;
    }

    /**
     * 注册方法
     * @param $tag
     * @param $func
     * @return $this
     */
    public function registerFunc($tag, $func = null) {
        $this->funcList[$tag] = empty($func) ? $tag : $func;
        return $this;
    }

    /**
     * 判断是否有方法
     * @param $tag
     * @return bool
     */
    public function hasFunc($tag) {
        return array_key_exists($tag, $this->funcList);
    }

    /**
     * 执行方法
     * @param $tag
     * @param $args
     * @return bool|string
     */
    public function invokeFunc($tag, $args) {
        if (!$this->hasFunc($tag)) {
            return false;
        }
        $func = $this->funcList[$tag];
        if (is_string($func)) {
            return $this->setValueToFunc($func, $args);
        }
        if (is_callable($func)) {
            return call_user_func($func, $args);
        }
        return false;
    }

    protected function setValueToFunc($func, $args) {
        if (strpos($func, '%') === false) {
            return sprintf('<?=%s(%s)?>', $func, $args);
        }
        if (substr_count($func, '%') == 1) {
            return sprintf($func, $args);
        }
        return sprintf($func, ...explode(',', $args));
    }

    public function parseFile(File $file) {
        return $this->parse($file->read());
    }

    public function parse($content) {
        $this->initHeaders();
        $content = preg_replace($this->salePattern, '', $content);
        $pattern = sprintf('/%s\s*(.+?)\s*%s/s', $this->beginTag, $this->endTag);
        return preg_replace_callback($pattern, [$this, 'replaceCallback'], $content);
    }

    public function compileString($arg) {
        return $this->parse($arg);
    }

    protected function replaceCallback($match) {
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
            return '<?php '.$content.';?>';
        }
        // 转化输出默认值
        if ($this->hasOrderTag($content, ['$', ',', ['$', '\'', '"']]) > 0) {
            $args = explode(',', $content, 2);
            return sprintf('<?=isset(%s) ? %s : %s?>', $args[0], $args[1]);
        }
        // 转化输出值
        if (preg_match('/^(\$|this.)[_\w\.-\>\[\]\|\$]+$/i', $content)) {
            return sprintf('<?=%s?>', $this->parseVal($content));
        }
        return $match[0];
    }

    protected function parseThis($content) {
        if (strpos($content, 'this.') !== 0) {
            return false;
        }
        if (strpos($content, '=') === false) {
            return false;
        }
        list($tag, $val) = explode('=', substr($content, 5));
        return sprintf('<?php $this->%s = %s;?>', $tag, $this->getRealVal($val));
    }

    protected function arrayToLink(array $args, $format) {
        return implode('', array_map(function($item) use ($format) {
            return sprintf($format, $item);
        }, $args));
    }

    /**
     * 引入js,css 或加载文件
     * @param $content
     * @return bool|null|string
     */
    protected function parseInclude($content) {
        if (!preg_match('/^(link|script|js|css|php|tpl)\s+(src|href|file)=[\'"]?([^"\']+)[\'"]?/i', $content, $match)) {
            return false;
        }
        $match[1] = strtolower($match[1]);
        $files = explode(',', $match[3]);
        if ($match[1] == 'link' || $match[1] == 'css') {
            $this->addHeader(sprintf('$this%s;',
                $this->arrayToLink($files, '->registerCssFile(\'%s\')')));
            return null;
        }
        if ($match[1] == 'js' || $match[1] == 'script') {
            $this->addHeader(sprintf('$this%s;',
                $this->arrayToLink($files, '->registerJsFile(\'%s\')')));
            return null;
        }
        $content = '';
        foreach ($files as $file) {
            if ($match[1] == 'php') {
                $content .= sprintf('<?php include \'%s\';?>', $file);
                continue;
            }
            if ($match[1] == 'tpl') {
                $content .= sprintf('<?php $this->extend(\'%s\');?>', $file);
                continue;
            }
            return false;
        }
        return $content;
    }

    protected function getRealVal($val) {
        if (empty($val)) {
            return 'null';
        }
        if (is_numeric($val)) {
            return $val;
        }
        if ($val == 'true' || $val == 'false') {
            return $val;
        }
        $first = substr($val, 0, 1);
        if ($first == '$' || $first == '"') {
            return $val;
        }
        if ($first == '\'') {
            $val = trim('\'');
        }
        return sprintf('\'%s\'', $val);
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
        if (strpos($val, 'this.') === 0) {
            $val = '$this->'.substr($val, 5);
        }
        if (strpos($val, '.$') !== false) {
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
            list($tag, $vals) = Str::explode($filter, ':', 2);
            if ($this->allowFilters !== true &&
                !in_array($tag, (array)$this->allowFilters)) {
                continue;
            }
            $p = sprintf('%s(%s%s)', $tag, $p, empty($vals) ? '' : (','.$vals));
        }
        return $p;
    }

    /**
     * 输出数组
     * @param $val
     * @return mixed|string
     */
    protected function makeVar($val) {
        if (strrpos($val, '.') === false) {
            return $val;
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
     * @param $content
     * @param $search
     * @return bool
     */
    protected function hasOrderTag($content, $search) {
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
     * @param $content
     * @param array $tags
     * @param int $index
     * @return bool|int
     */
    protected function hasOneTag($content, $tags, $index = 0) {
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
    protected function parseNote($content) {
        if (($content{0} == '*'
                && substr($content, -1) == '*') ||
            (substr($content, 0, 2) == '//'
                && substr($content, -2, 2) == '//')) {
            return '<?php /*'.$content.'*/ ?>';
        }
        return false;
    }

    /**
     * 转化赋值
     * @param $content
     * @return bool|string
     */
    protected function parseAssign($content) {
        $eqI = strpos($content, '=');
        $dI = strpos($content, ',');
        if ($eqI === false || $dI === false || $dI >= $eqI) {
            return false;
        }
        $args = explode('=', $content, 2);
        return sprintf('<?php list(%s) = %s; ?>', $args[0], $args[1]);
    }

    protected function parseLambda($content) {
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
     */
    protected function parseBlockTag($content) {
        list($tag, $content) = explode(':', $content, 2);
        if ($tag == 'for') {
            return $this->parseFor($content);
        }
        if ($tag == 'switch') {
            return $this->parseSwitch($content);
        }
        if ($tag == 'case') {
            sprintf('<?php case %s:?>', $content);
        }
        if ($tag == 'default') {
            sprintf('<?php default:?>', $content);
        }
        if ($tag == 'extend') {
            return $this->parseExtend($content);
        }
        if ($tag == 'if') {
            return $this->parseIf($content);
        }
        if ($tag == 'elseif' || $tag == 'else if') {
            return sprintf('<?php elseif(%s):?>', $content);
        }
        if ($tag == 'url') {
            return sprintf('<?php echo $this->url(%s) ?>', $content);
        }
        if ($tag == 'use') {
            $this->addHeader(sprintf('use \\%s;', trim($content, '\\')));
            return null;
        }
        if ($tag == 'break' || $tag == 'continue') {
            return sprintf('<?php %s %s; ?>', $tag, $content);
        }
        return $this->invokeFunc($tag, $content);
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
            if (strpos($name, '[') !== false) {
                $start = strpos($content, '[');
                $data = substr($content, $start,
                    strpos($content, ']', $start) - $start - 1);
                break;
            }
            if (strpos($name, '$') !== 0) {
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
        if ($length == 1) {
            return '<?php if('.$content.'):?>';
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
            return sprintf('<?php switch(%s):?>', $content);
        }
        return sprintf('<?php switch(%s): case %s:?>', $args[0], $args[1]);
    }

    /**
     * 转化 for
     * @param $content
     * @return string
     */
    protected function parseFor($content) {
        $args = strpos($content, ';') !== false ?
            explode(';', $content) :
            explode(',', $content);
        $length = count($args);
        if ($length == 1) {
            $this->forTags[] = 'while';
            return '<?php while('.$content.'):?>';
        }
        if ($length == 2) {
            $this->forTags[] = 'foreach';
            return sprintf('<?php if (!empty(%s) && is_array(%s)): foreach(%s as %s):?>',
                $args[0],
                $args[0],
                $args[0], $args[1] ?: '$item');
        }
        $tag = substr(trim($args[2]), 0, 1);
        if (in_array($tag, ['<', '>', '='])) {
            list($key, $item) = $this->getForItem($args[1]);
            $this->forTags[] = 'foreach';
            return sprintf('<?php if (!empty(%s) && is_array(%s)): foreach(%s as %s=>%s): if (!(%s %s)): break; endif;?>',
                $args[0],
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
        return sprintf('<?php if (!empty(%s) && is_array(%s)):  $i = 0; foreach(%s as %s): $i ++; if ($i > %s): break; endif;?>',
            $args[0],
            $args[0],
            $args[0],
            $args[1]  ?: '$item',
            $args[2]);
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
        if (strpos($content, '=>') !== false) {
            list($key, $item) = explode('=>', $content);
        } elseif (strpos($content, ' ') !== false) {
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
        if ($first == '#') {
            // 返回原句
            return sprintf('%s%s%s', $this->beginTag, substr($content, 1), $this->endTag);
        }
        if ($first == '>') {
            return $this->parseBlock(substr($content, 1));
        }
        if ($first == '/') {
            return $this->parseEndTag(substr($content, 1));
        }
        if ($first == '|') {
            return '<?php if ('.substr($content, 1).'):?>';
        }
        if ($first == '+') {
            return '<?php elseif ('.substr($content, 1).'):?>';
        }
        if ($first == '~') {
            return '<?php for('.substr($content, 1).'):?>';
        }
        // 直接输出
        if ($first == '=') {
            return '<?='.substr($content, 1).'?>';
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
        return false;
    }

    protected function parseEndForTag() {
        if (count($this->forTags) == 0) {
            return false;
        }
        $tag = array_pop($this->forTags);
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
            return sprintf('<<<%s; $this->register%s($%s_%s);?>', strtoupper($tag), ucfirst($tag), $tag, $this->tplHash);
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
        if ($content == 'js' || $content == 'script') {
            $this->blockTag = 'js';
            return sprintf('<?php $js_%s = <<<JS', $this->tplHash);
        }
        if ($content == 'css' || $content == 'style') {
            $this->blockTag = 'css';
            return sprintf('<?php $css_%s = <<<CSS', $this->tplHash);
        }
        if ($content == 'text') {
            $this->blockTag = 'text';
            return null;
        }
        return sprintf('<?php %s; ?>', $content);
    }
}