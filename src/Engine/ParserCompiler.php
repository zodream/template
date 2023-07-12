<?php
declare(strict_types=1);
namespace Zodream\Template\Engine;

use Zodream\Disk\File;
use Zodream\Helpers\Str;
use Zodream\Template\CharReader;

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
        'url' => '$this->url',
        'js' => '$this->registerJsFile',
        'css' => '$this->registerCssFile',
        'tpl' => '$this->extend',
        'request' => 'request',
        'isset' => 'isset',
        'empty' => 'empty',
        '__' => '__',
    ];

    protected array $blockTags = [];

    protected string $currentToken = '';

    protected bool $moveNextStop = false;

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
     * @param mixed $func
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
    public function hasFunc(string $tag): bool {
        return array_key_exists($tag, $this->funcList);
    }

    /**
     * 执行方法
     * @param string $tag
     * @param string $args
     * @return bool|string
     */
    public function invokeFunc(string $tag, string $args): string|false {
        if (str_starts_with($tag, '$this->')) {
            if (strpos($tag, '$', 7) !== false) {
                return 'null';
            }
            return sprintf('%s(%s)', $tag, $args);
        } else if ($tag === '$' || !$this->hasFunc($tag)) {
            return 'null';
        }
        if (!is_string($this->funcList[$tag]) && is_callable($this->funcList[$tag])) {
            return (string)call_user_func($this->funcList[$tag], $args);
        }
        return sprintf('%s(%s)',
            $this->funcList[$tag], $args);
    }


    public function parseFile(File $file) {
        return $this->parse($file->read());
    }



    public function compile(string $value): string {
        return $this->parse($value);
    }

    public function parse(string|CharReader $content): string {
        $this->initHeaders();
        $reader = $content instanceof CharReader ? $content : new CharReader($content);
        while ($reader->canNext()) {
            $res = $reader->jumpTo('<!--', '<?', $this->beginTag);
            if ($res < 0) {
                break;
            }
            switch ($res) {
                case 0:
                    $this->parseHtmlComment($reader);
                    break;
                case 2:
                    $this->parseTemplate($reader);
                    break;
                case 1:
                    $this->parsePhpScript($reader);
                    break;
                default:
                    break;
            }
        }
        return $reader->__toString();
    }

    public function parseFunc(string $content): string {
        $reader = new CharReader($content);
        return $this->parseCode($reader, $reader->length())[0];
    }

    public function parseValue(string $content): string {
        $reader = new CharReader($content);
        return $this->parseInlineCode($reader, $reader->length());
    }

    /**
     * 删除html的注释
     * @param CharReader $reader
     * @return void
     */
    protected function parseHtmlComment(CharReader $reader): void {
        $i = $reader->indexOf('-->');
        if ($i < 0) {
            $reader->seekOffset(3);
            return;
        }
        $reader->maker();
        $reader->seek($i + 3);
        $reader->replace();
    }

    /**
     * 不允许直接写php脚本
     * @param CharReader $reader
     * @return void
     */
    protected function parsePhpScript(CharReader $reader): void {
        $i = $reader->indexOf('?>');
        if ($i < 0) {
            $i = $reader->position();
        }
        $reader->maker();
        $reader->seek($i + 2);
        $reader->replace();
    }

    protected function parseTemplate(CharReader $reader): void {
        if ($reader->nextIs('/>') >= 0) {
            $reader->maker();
            $reader->replace($this->parseEndCodeBlock(), $reader->indexOf($this->endTag) + 1);
            return;
        }
        if ($this->blockTag !== false) {
            $reader->seekOffset(strlen($this->beginTag));
            return;
        }
        list($i, $tag) = $reader->minIndex("\r", "\n", $this->endTag);
        if ($i < 0) {
            $reader->seekOffset(strlen($this->beginTag));
            return;
        }
        if ($tag < 2) {
            $reader->seek($i);
            return;
        }
        list($res, $isEcho) = $this->parseCode($reader, $i);
        if ($reader->position() > $i + strlen($this->beginTag) + strlen($this->endTag)) {
            $i = $reader->position();
        }
        $reader->replace($isEcho ? $this->formatEcho($res) :
            $this->formatBlock($res), $i + 1);
    }

    protected function parseComment(CharReader $reader, int $max): string|false {
        $i = $reader->nextIs('/', '*');
        if ($i < 0) {
            return false;
        }
        switch ($i) {
            case 1:
                $j = $reader->indexOf('*/');
                if ($j < 0 || $j > $max) {
                    $reader->seek($max);
                } else {
                    $reader->seek($j + 2);
                }
                return '';
            default:
                $i = $reader->indexOf("\r", 0, $max);
                if ($i < 0) {
                    $i = $reader->indexOf("\n", 0, $max);
                }
                if ($i < 0) {
                    $reader->seek($max);
                } else {
                    $reader->seek($i);
                }
                return '';
        }
    }

    /**
     *
     * @param CharReader $reader
     * @param int $max
     * @return array{string, bool}
     */
    public function parseCode(CharReader $reader, int $max): array {
        $this->moveNextStop = false; // 一定要清除
        $reader->maker();
        $code = $reader->next();
        switch ($code) {
            case '=':
                $reader->seekOffset(1);
                $reader->back();
                return [$this->parseInlineCode($reader, $max), true];
            case '>':
                $tags = ['text', 'css', 'js'];
                $j = $reader->nextIs(...$tags);
                if ($j >= 0) {
                    $reader->seekOffset(strlen($tags[$j]) + 1);
                    return [call_user_func([$this,
                        sprintf('parse%sBlockCall', Str::studly($tags[$j]))], $reader, $max), false];
                } else if ($reader->isWhitespaceUntil($max - 1)) {
                    $reader->seekOffset(1);
                    return [$this->parseBlockCode($reader, $max), false];
                } else {
                    $reader->seekOffset(1);
                    return [$this->parseInlineCode($reader, $max), false];
                }
            case '|':
                $reader->seekOffset(1);
                return [$this->parseIfCall($reader, $max), false];
            case '+':
                if ($reader->isWhitespaceUntil($max - 1)) {
                    return ['else:', false];
                } else {
                    $reader->seekOffset(1);
                    return [$this->parseElseifCall($reader, $max), false];
                }
            case '-':
                return ['endif;', false];
            case '@':
                // $reader->seekOffset(1);
                return [$this->parseFileCall($reader, $max), false];
            case '~':
                $reader->seekOffset(1);
                return [$this->parseForCall($reader, $max), false];
            case '/':
                if ($reader->nextIs('>') >= 0) {
                    return [$this->parseEndCodeBlock(), false];
                } elseif ($reader->nextIs('~') >= 0) {
                    return [$this->parseEndFor(), false];
                } else {
                    $reader->seekOffset(1);
                    return [$this->parseEndBlock($reader, $max), false];
                }
        }
        $reader->jumpWhitespace();
        foreach ([
            'js',
            'css',
            'tpl',
            'for',
            'if',
             'elseif',
             'else',
            'default',
            'page' => true,
            'layout',
            'break',
            'continue',
            'elseif',
            'switch',
            'case',
            'url' => true,
            'request' => true
                 ] as $func => $isEcho) {
            if (!is_bool($isEcho)) {
                list($func, $isEcho) = [$isEcho, false];
            }
            if ($reader->is($func)) {
                $reader->seekOffset(strlen($func));
                return [call_user_func([$this, sprintf('parse%sCall', Str::studly($func))],
                    $reader, $max), $isEcho];
            }
        }
        $reader->back();
        $isEcho = true;
        $data = [];
        while ($reader->canNextUntil($max)) {
            $line = $this->parseInlineCode($reader, $max);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '=')) {
                $isEcho = false;
            }
            $data[] = $line;
        }
        return [implode('', $data), $isEcho];
    }

    protected function parseBlockCode(CharReader $reader, int $max): string {
        $reader->seek($max);
        $endTag = sprintf('%s/>%s', $this->beginTag, $this->endTag);
        $max = $reader->indexOf($endTag);
        if ($max < 0) {
            return '';
        }
        $data = [];
        while ($reader->canNextUntil($max)) {
            $code = $this->parseInlineCode($reader, $max);
            if (empty($code)) {
                continue;
            }
            $data[] = $code. ';';
        }
        $reader->seek($max + strlen($endTag));
        return implode(PHP_EOL, $data);
    }

    public function parseInlineCode(CharReader $reader, int $max): string
    {
        $data = [];
        $block = [];
        while ($reader->canNextUntil($max)) {
            $token = $this->nextToken($reader, $max);
            if ($token === '') {
                continue;
            }
            if ($token === PHP_EOL || $token === ';') {
                break;
            }
            if ($token === '?') {
                if (!empty($block)) {
                    $data[] = $this->parseWordToValue(implode('', $block));
                    $block = [];
                }
                $data[] = $token;
                $data[] = $this->parseLambda($reader, $max);
                continue;
            }
            if ($token === '[') {
                if (!empty($block)) {
                    $data[] = $this->parseWordToValue(implode('', $block));
                }
                $data[] = $this->parseArray($reader, $token, $max);
                $block = [];
                continue;
            }
            if ($token === '.') {
                if (empty($block)) {
                    $block = [$this->parseThis($reader, $max)];
                    continue;
                }
                if (count($block) === 1) {
                    if ($block[0] === 'this' || $block[0] === '$this') {
                        $block = [$this->parseThis($reader, $max)];
                    } elseif ($this->isArrayOrCall($reader, $max)) {
                        $block = [$this->parseCallQuery($reader, $max, $block[0])];
                    } else {
                        $block = [$this->parseArrayQuery($reader, $token, $max, $block[0])];
                    }
                    continue;
                }
            }
            if ($this->isFuncToken($token)) {
                // TODO 方法
                $func = implode('', $block);
                $block = [];
                $data[] = $this->parseInvokeFunc($reader, $max, $func, $token);
                break;
            }
            if ($token === ' ') {
                if (!empty($block)) {
                    $data[] = $this->parseWordToValue(implode('', $block));
                    $block = [];
                }
                continue;
            }
            if ($this->isSymbol($token[0])) {
                if (!empty($block)) {
                    $data[] = $this->parseWordToValue(implode('', $block));
                    $block = [];
                }
                $data[] = $token;
                continue;
            }
            $block[] = $token;
        }
        if (!empty($block)) {
            $data[] = $this->parseWordToValue(implode('', $block));
        }
        return implode(' ', $data);
    }

    /**
     * 结束代码块
     * @return string
     */
    protected function parseEndCodeBlock(): string {
        list($tag, $this->blockTag) = [$this->blockTag, false];
        if ($tag == 'php' || $tag === '') {
            return '?>';
        }
        if ($tag == 'js' || $tag == 'css') {
            return sprintf(PHP_EOL.'%s;'.PHP_EOL.' $this->register%s($%s_%s);?>',
                strtoupper($tag), ucfirst($tag), $tag,
                $this->tplHash);
        }
//        if ($tag == 'text') {
//            return '';
//        }
        return '';
    }

    protected function parseInvokeFunc(CharReader $reader, int $max, string $func, string $tag = ':'): string {
        $method = sprintf('parse%sCall', Str::studly($func));
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $reader, $max);
        }
        $parameters = $this->parseCallCode($reader, $tag, $max);
        return $this->invokeFunc($func, $parameters);
    }

    protected function isFuncToken(string $code): bool {
        return match ($code) {
            ':', '(' => true,
            default => false,
        };
    }

    /**
     * 转化方法调用的
     * @param CharReader $reader
     * @param string $tag 可以是: 搭配,或空格分隔 或 ( 只能是,分隔
     * @param int $max
     * @param string $link 连接符
     * @return string
     */
    protected function parseCallCode(CharReader $reader, string $tag, int $max, string $link = ',',
                                     bool $firstMaybeString = true): string {
        $data = [];
        while ($reader->canNextUntil($max)) {
            // 只有第一个会被解析成字符串
            $token = $this->nextScope($reader, $max, ')', !$firstMaybeString || !empty($data));
            if ($tag === '(' && $token === ')') {
                break;
            }
            if ($token === ';') {
                $reader->back();
                break;
            }
            if ($token === ',') {
                continue;
            }
            if ($token === '->') {
                $last = array_pop($data);
                $data[] = sprintf('%s->%s',
                    $last,
                    $this->nextToken($reader, $max));
                continue;
            }
            if ($token === '=>' || $token === '=') {
                $last = array_pop($data);
                $data[] = $this->parseArray($reader, '[', $max, sprintf('%s => %s',
                    $last,
                    $this->nextScope($reader, $max, ']')));
                continue;
            }
            $data[] = $token;
        }
        return implode($link, array_filter($data, function ($item) {
            return $item !== '' && $item !== ' ';
        }));
    }

    protected function parseToken(string $token): string {
        return '';
    }

    public function parseLambda(CharReader $reader, int $max): string {
        $i = $reader->indexOf(':', 0, $max);
        if ($i < 0) {
            return $this->parseInlineCode($reader, $max);
        }
        $data = [$this->parseCallCode($reader, ':',  $i, ''), ':'];
        $reader->seek($i);
        $data[] = $this->parseCallCode($reader, ':',  $max, '');
        return implode(' ', $data);
    }

    /**
     * 获取下一个分词
     * @param CharReader $reader
     * @param int $max
     * @return string
     */
    public function nextToken(CharReader $reader, int $max): string {
        if ($this->moveNextStop) {
            $this->moveNextStop = false;
            return $this->currentToken;
        }
        $reader->jumpWhitespace();
        $i = $reader->position() + 1;
        while ($reader->canNextUntil($max)) {
            $code = $reader->next();
            if ($code === '') {
                break;
            }
            $isSymbol = $this->isSymbol($code);
            if (!$this->isWhitespace($code) && !$isSymbol && !$this->isBracket($code)) {
                continue;
            }
            if ($reader->position() - $i > 0) {
                $reader->back();
                break;
            }
            if ($this->isOperatorSymbol($code)) {
                while ($reader->canNextUntil($max)) {
                    $code = $reader->next();
                    if (!$this->isOperatorSymbol($code)) {
                        $reader->back();
                        break;
                    }
                }
                break;
            }
            if ($code === "\r") {
                if ($reader->nextIs("\n") >= 0) {
                    $reader->next();
                }
                return $this->currentToken = PHP_EOL;
            }
            if ($code === "\n") {
                return $this->currentToken = PHP_EOL;
            }
            if ($code === '/') {
                $res = $this->parseComment($reader, $max);
                return $this->currentToken = $res === false ? $code : $res;
            }
//            if ($code === ':' || $code === '(') {
//                // TODO 方法
//                $func = $reader->substr($i, $reader->position());
//                $parameters = $this->parseCallCode($reader, $code, $max);
//                return $this->invokeFunc($func, $parameters);
//            }
//            if ($code === '.' || $code === '[') {
//                // TODO 数组
//            }
            if ($code === '\'' || $code === '"') {
                // TODO 字符串
                $reader->seekOffset(1);
                return $this->currentToken = $this->parseString($reader, $code, $max);
            }
            return $this->currentToken = $code;
        }
        return $this->currentToken = $reader->substr($i, $reader->position() + 1);
    }

    /**
     * 把一些分词进行合并，例如 数组， 取数组的
     * @param CharReader $reader
     * @param int $max
     * @param string $endTag
     * @param bool $allowOperator 是否允许运算符，否则合并为字符串
     * @return string
     */
    public function nextScope(CharReader $reader, int $max,
                              string $endTag = '',
                              bool $allowOperator = true): string {
        if (!$allowOperator) {
            $next = $reader->readChar($reader->position() + 1);
            if ($next === '') {
                return $next;
            }
            if ($next !== '$' && $next !== ' ' && ($next === '.' || !$this->isSeparatorSymbol($next))) {
                if (!empty($endTag)) {
                    // 出现了结束符，则，自动缩小范围
                    $i = $reader->indexOf($endTag, 0, $max);
                    if ($i >= 0) {
                        $max = $i;
                    }
                }
                // 首字符不能为 $ ' "
                return $this->nextStringScope($reader, $max);
            }
        }
        $token = $this->nextToken($reader, $max);
        if ($token === '' || $this->isWhitespace($token) ||
            $this->isSymbol($token[0])) {
            return $token;
        }
        if ($token === '[') {
            return $this->parseArray($reader, $token, $max);
        }
        if ($this->isBracket($token)) {
            return $token;
        }
        $first = $token[0];
        if ($first === '\'' || $first === '"') {
            return $token;
        }
        if ($first === '$') {
            $next = $this->nextToken($reader, $max);
            if ($next !== '.' && $next !== '[') {
                $this->moveNextStop = true;
                return $token;
            }
            if ($next === '.' && $this->isArrayOrCall($reader, $max)) {
                return $this->parseCallQuery($reader, $max, $token);
            }
            return $this->parseArrayQuery($reader, $next, $max, $token, true);
        }
        $i = $reader->nextIs(':', '(');
        if ($i >= 0) {
            $reader->seekOffset(1);
            return $this->parseInvokeFunc($reader, $max, $token, $i > 0 ? '(' : ':');
        }
        return $this->parseWordToValue($token);
    }

    /**
     * 转化没有 引号的字符串
     * @param CharReader $reader
     * @param int $max
     * @return string
     */
    public function nextStringScope(CharReader $reader, int $max): string {
        $begin = $reader->position() + 1;
        $data = [];
        $comma = $reader->indexOf(',', 1, $max);
        $maxIndex = $comma > 0 ? $comma : $max;
        $eq = -1;
        if ($reader->indexOf(':$', 0, $maxIndex) < 0) {
            // 只有出现 :$ 才是字符串解析，以 , 作为结束符，否则要考虑 = 为数组
            $eq = $reader->indexOf('=', 0, $max);
            if ($eq > 0 && ($eq < $comma || $comma < 0)) {
                $maxIndex = $eq;
            }
        }
        while ($reader->canNextUntil($maxIndex)) {
            $i = $reader->indexOf(':$', 0, $maxIndex);
            if ($i < 0) {
                $data[] = $this->parseWordToValue($reader->substr($begin, $maxIndex));
                $reader->seek($maxIndex);
                break;
            }
            $data[] = sprintf('\'%s\'', $reader->substr($begin, $i));
            $reader->seek($i);
            $begin = $i;
            $j = $reader->indexOf(':', 1, $maxIndex);
            if ($j < 0) {
                $data[] = $this->parseInlineCode($reader,  $maxIndex);
                break;
            }
            $data[] = $this->parseInlineCode($reader, $j);
            $begin = $j + 1;
        }
        $reader->seek($maxIndex - ($maxIndex === $eq ? 1 : 0));
        return implode('.', $data);
    }

    /**
     * 判断 . 是数组查询还是方法调用
     * @param CharReader $reader
     * @param int $max
     * @return bool true 为方法调用
     */
    protected function isArrayOrCall(CharReader $reader, int $max): bool {
        list($i, $j) = $reader->minIndex(':', ' ', ',');
        return $j === 0 && $i < $max;
    }

    protected function parseCallQuery(CharReader $reader, int $max, string $begin): string {
        $data = [$begin];
        while ($reader->canNextUntil($max)) {
            $token = $this->nextToken($reader, $max);
            if ($token === ':') {
                break;
            }
            if ($token === '.') {
                continue;
            }
            $data[] = $token;
        }
        return sprintf('%s(%s)', implode('->', $data), $this->parseCallCode($reader, ':', $max));
    }

    /**
     * 是否是符号
     * @param string $code
     * @return bool
     */
    protected function isSymbol(string $code): bool
    {
        if ($this->isOperatorSymbol($code)) {
            return true;
        }
        return $this->isSeparatorSymbol($code);
    }

    /**
     * 判断是否是拆分符号
     * @param string $code
     * @return bool
     */
    protected function isSeparatorSymbol(string $code): bool {
        return match ($code) {
            ':', '.', ';', ',', '\'', '"' => true,
            default => false,
        };
    }

    /**
     * 是否是运算符
     * @param string $code
     * @return bool
     */
    protected function isOperatorSymbol(string $code): bool {
        return match ($code) {
            '!', '&', '=', '%', '*', '+', '/', '-', '|', '<', '>', '?', '^', '~' => true,
            default => false,
        };
    }

    protected function isBracket(string $code): bool {
        return match ($code) {
            '[', ']', '{', '}', '(', ')' => true,
            default => false,
        };
    }

    protected function isWhitespace(string $code): bool
    {
        return match ($code) {
            ' ', "\n", "\r" => true,
            default => false,
        };
    }

    protected function parseThis(CharReader $reader, int $max): string {
        if ($reader->is('$')) {
            return '';
        }
        $reader->jumpWhitespace();
        return '$this->';
    }

    protected function parseArray(CharReader $reader, string $tag, int $max, string $first = ''): string {
        $data = [];
        if ($first !== '') {
            $data[] = $first;
        }
        $endTag = $tag === '(' ? ')' : ']';
        while ($reader->canNextUntil($max)) {
            $token = $this->nextScope($reader, $max, $endTag, !empty($data));
            if ($token === $endTag) {
                break;
            }
            if ($token === '' || $token === ' ') {
                continue;
            }
            if ($token === ';') {
                $reader->back();
                break;
            }
            if ($token === ',') {
                continue;
            }
            if ($token === '=>' || $token === '=') {
                $last = count($data)-1;
                $data[$last] = sprintf('%s => %s', $data[$last],
                    $this->nextScope($reader, $max, $endTag));
                continue;
            }
            $data[] = $token;
        }
        return sprintf('[%s]', implode(',', $data));
    }

    protected function parseArrayQuery(CharReader $reader, string $tag,
                                       int $max, string $token, bool $isSymbolStop = true): string {
        $data = [$token];
        $block = [];
        while ($reader->canNextUntil($max)) {
            $token = $this->nextToken($reader, $max);
            if ($token === ' ') {
                continue;
            }
            if ($token !== '' && $isSymbolStop &&
                ($this->isOperatorSymbol($token[0]) || $this->isBracket($token[0]))
            ) {
                $this->moveNextStop = true;
                break;
            }
            if ($tag === '[') {
                if ($token === ']') {
                    $data[] = empty($block) ? '[]' : sprintf('[%s]',
                        $this->parseWordToValue(implode('', $block))
                    );
                    $block = [];
                }
                $block[] = $token;
                continue;
            }
            if ($tag === '.' && $token === ',') {
                $this->moveNextStop = true;
                break;
            }
            if ($token === $tag) {
                $data[] = empty($block) ? '[]' : sprintf('[%s]',
                    $this->parseWordToValue(implode('', $block))
                );
                $block = [];
                continue;
            }
            $block[] = $token;
        }
        if (!empty($block)) {
            $data[] = sprintf('[%s]',
                $this->parseWordToValue(implode('', $block))
            );
        }
        return implode('', $data);
    }

    protected function parseString(CharReader $reader, string $tag, int $max): string {
        $i = $reader->indexOf($tag, 1, $max);
        if ($i < 0) {
            $i = $max;
        }
        $res = $reader->substr($i);
        $reader->seek($i);
        return sprintf('%s%s%s', $tag, $res, $tag);
    }

    protected function parseWordToValue(string $val): string {
        if ($val === '') {
            return '';
        }
        if ($val === ' ') {
            return 'null';
        }
        if (is_numeric($val)) {
            return $val;
        }
        if ($val === 'true' || $val === 'false') {
            return $val;
        }
        if ($val[0] === '$') {
            return $val;
        }
        return sprintf('\'%s\'', $val);
    }

    protected function parseIfCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        $first = $reader->indexOf(',', 0, $max);
        if ($first < 0) {
            return sprintf('if (%s):', $this->parseCallCode($reader, ':', $max, ' ', false));
        }
        $second = $reader->indexOf(',', $first - $reader->position() + 1, $max);
        $func = $this->parseCallCode($reader, ':', $first, ' ', false);
        // $reader->seek($first + 1);
        if ($second < 0) {
            $case = $this->parseCallCode($reader, ':', $max, ' ', false);
            return sprintf('if (%s) { echo %s; }', $func, $case);
        }
        $case = $this->parseCallCode($reader, ':', $second, ' ', false);
        // $reader->seek($second + 1);
        return sprintf('if (%s) { echo %s; } else { echo %s;}', $func, $case,
            $this->parseCallCode($reader, ':', $max, ' ', false));
    }

    protected function parseElseifCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        return sprintf('elseif (%s):', $this->parseCallCode($reader, ':', $max, ' ', false));
    }

    protected function parseForCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        $first = $reader->indexOf(',', 0, $max);
        if ($first < 0) {
            $this->forTags[] = 'while';
            return sprintf('while (%s):', $this->parseCallCode($reader, ':', $max, ' ', false));
        }
        $second = $reader->indexOf(',', $first - $reader->position() + 1, $max);
        $func = $this->parseCallCode($reader, ':', $first, ' ', false);
        $reader->seek($first);
        if ($second < 0) {
            $this->forTags[] = 'foreach';
            $case = $this->parseInlineCode($reader, $max);

            return sprintf('if (!empty(%s)): foreach (%s as %s):', $func, $func, $case ?: '$item');
        }
        $case = $this->parseInlineCode($reader, $max);
        $reader->seek($second + 1);
        $i = $reader->nextIs('<', '>', '=');
        if ($i >= 0) {
            list($key, $item) = $this->formatForItem($reader->substr($first, $second));
            $this->forTags[] = 'foreach';
            return sprintf('if (!empty(%s)): foreach(%s as %s=>%s): if (!(%s %s)): break; endif;',
                $func,
                $func, $key, $item, $key,  $this->parseInlineCode($reader, $max));
        }
        $third = $this->parseInlineCode($reader, $max);
        if ($this->isJudge($case) && $this->hasTag($third, ['+', '-', '*', '/', '%'])) {
            $this->forTags[] = 'for';
            return sprintf('for(%s; %s; %s):',
                $func, $case, $third);
        }
        $this->forTags[] = 'foreach';
        return sprintf('if (!empty(%s)):  $i = 0; foreach(%s as %s): $i ++; if ($i > %s): break; endif;',
        $func, $func, $case ?: '$item', $third);
    }


    /**
     * 是否是判断语句
     * @param $str
     * @return bool
     */
    protected function isJudge(string $str): bool {
        return $this->hasTag($str, ['<', '>', '==']);
    }

    protected function hasTag(string $str, array|string $search): bool {
        foreach ((array)$search as $tag) {
            if (str_contains($str, $tag)) {
                return true;
            }
        }
        return false;
    }

    protected function formatForItem(string $content): array {
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

    protected function parseSwitchCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        $i = $reader->indexOf(',', 0, $max);
        if ($i < 0) {
            return sprintf('switch(%s):', $this->parseCallCode($reader, ':', $max, ' ', false));
        }
        $func = $this->parseCallCode($reader, ':', $i, ' ', false);
        $reader->seek($i + 1);
        $case = $this->parseInlineCode($reader, $max);
        return sprintf('switch(%s): %s case %s:', PHP_EOL, $func, $case);
    }

    protected function parseBreakCall(CharReader $reader, int $max): string {
        return 'break;';
    }

    protected function parseElseCall(CharReader $reader, int $max): string {
        return 'else:';
    }

    protected function parseDefaultCall(CharReader $reader, int $max): string {
        return 'default:';
    }

    protected function parseCaseCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        return sprintf('case %s:', $this->parseCallCode($reader, ':', $max, ' ', false));
    }

    protected function parseContinueCall(CharReader $reader, int $max): string {
        return 'continue;';
    }

    protected function parseEndBlock(CharReader $reader, int $max): string {
        $tag = $reader->substr($max);
        if ($tag == '|' || $tag == 'if') {
            return 'endif;';
        }
        if ($tag == '~' || $tag == 'for') {
            return $this->parseEndFor();
        }
        if ($tag == '*' || $tag == 'switch') {
            return 'endswitch;';
        }
        if (!in_array($tag, $this->blockTags)) {
            return '';
        }
        $func = $this->funcList[$tag];
        if (is_string($func)) {
            return sprintf('%s(-1);', $func);
        }
        if (is_callable($func)) {
            return call_user_func($func, -1);
        }
        return '';
    }
    protected function parseEndFor(): string {
        if (count($this->forTags) == 0) {
            return '';
        }
        $tag = array_pop($this->forTags);
        if ($tag == 'foreach') {
            return 'endforeach;endif;';
        }
        return sprintf('end%s;', $tag);
    }

    protected function parseTextBlockCall(CharReader $reader, int $max): string {
        $this->blockTag = 'text';
        return '';
    }

    protected function parseCssBlockCall(CharReader $reader, int $max): string {
        return $this->parsePlainBlock($reader, $max, 'CSS');
    }

    protected function parseJsBlockCall(CharReader $reader, int $max): string {
        return $this->parsePlainBlock($reader, $max, 'JS');
    }

    protected function parsePlainBlock(CharReader $reader, int $max, string $tag): string {
        $this->blockTag = false;
        $reader->seek($max);
        $endTag = sprintf('%s/>%s', $this->beginTag, $this->endTag);
        $end = $reader->indexOf($endTag);
        if ($max < 0) {
            return '';
        }
        $text = $reader->substr($max + 1, $end - 1);
        $reader->seek($end + strlen($endTag));
        return sprintf('$plain_%s = <<<%s%s%s%s%s;%s $this->register%s($plain_%s);',
            $this->tplHash, $tag, PHP_EOL,
            $text, PHP_EOL, $tag, PHP_EOL, ucfirst(strtolower($tag)), $this->tplHash);
    }

    protected function parseCssCall(CharReader $reader, int $max): string {
        if ($reader->current() === ':') {
            $reader->next();
        }
        return $this->parseLoadFile('css', $reader, $max);
    }

    protected function parseFileCall(CharReader $reader, int $max): string {
        $content = $reader->substr($max);
        $splitIndex = strpos($content, ':');
        $oldContent = $content;
        if ($splitIndex > 1) {
            $func = substr($content, 1, $splitIndex - 1);
            if ($this->hasFunc($func)) {
                $oldContent = substr($content, $splitIndex + 1);
                $content = $this->invokeFunc($func, $oldContent);
            }
            if (empty($content)) {
                return '';
            }
        }
        if (str_ends_with($oldContent, '.js')) {
            return $this->parseLoadFile('js', $content);
        }
        if (str_ends_with($oldContent, '.css')) {
            return $this->parseLoadFile('css', $content);
        }
        return '';
    }

    protected function parseJsCall(CharReader $reader, int $max): string {
        if ($reader->current() === ':') {
            $reader->next();
        }
        return $this->parseLoadFile('js', $reader, $max);
    }

    protected function parseLoadFile(string $func, string|CharReader $fileName, int $max = 0): string {
        if ($fileName instanceof CharReader) {
            $fileName = $fileName->substr($max);
        }
        $this->addHeader($this->invokeFunc($func, $this->parseWordToValue($fileName)).';');
        return '';
    }

    protected function parseTplCall(CharReader $reader, int $max): string {
//        if ($reader->current() === ':') {
//            $reader->next();
//        }
        return $this->invokeFunc('tpl', $this->parseCallCode($reader, ':', $max));
    }

    protected function parseRequestCall(CharReader $reader, int $max): string {
        $code = $reader->current();
        if ($code === '.') {
            $next = $this->nextToken($reader, $max);
            if ($next[0] === '$') {
                return '';
            }
            return sprintf('request()->%s(%s)', $next, $this->parseInlineCode($reader, $max));
        }
//        if ($code !== ':') {
//            $reader->back();
//        }
        return $this->invokeFunc('request', $this->parseCallCode($reader, ':', $max));
    }

    protected function parseUrlCall(CharReader $reader, int $max): string {
//        if ($reader->current() === ':') {
//            $reader->next();
//        }
        return $this->invokeFunc('url', $this->parseCallCode($reader, ':', $max));
    }

    protected function parseLayoutCall(CharReader $reader, int $max): string {
        if ($reader->current() === ':') {
            $reader->next();
        }
        return sprintf('$this->layout = %s;', $this->parseWordToValue($reader->substr($max)));
    }

    protected function parsePageCall(CharReader $reader, int $max): string {
        if ($reader->current() !== ':') {
            $reader->back();
        }
        $reader->jumpWhitespace();
        $i = $reader->indexOf(',', 0, $max);
        if ($i < 0) {
            return sprintf('%s->getLink()', $this->parseInlineCode($reader, $max));
        }
        $func = $this->parseInlineCode($reader, $i);
        $reader->seek($i + 1);
        return sprintf('%s->getLink(%s)', $func,
            $this->parseInlineCode($reader, $max));
    }

    /**
     * 输出代码
     * @param string $line
     * @param mixed ...$args
     * @return string
     */
    protected function formatEcho(string $line, mixed ...$args): string {
        if (!empty($args)) {
            $line = sprintf($line, ...$args);
        }
        if ($line === '') {
            return '';
        }
        return sprintf('<?= %s ?>', $line);
    }

    /**
     * 代码块
     * @param string $line
     * @param mixed ...$args
     * @return string
     */
    protected function formatBlock(string $line, mixed ...$args): string {
        if (!empty($args)) {
            $line = sprintf($line, ...$args);
        }
        if ($line === '') {
            return '';
        }
        return sprintf('<?php %s%s ?>', $line, Str::endWith($line, [';', ':']) ? '' : ';');
    }
}