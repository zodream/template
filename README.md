# template
模板引擎

# 模板语法

默认语法 {}

### 代码块

    {>}            php 语句块
    {>js}             脚本语句块
    {>css}            样式语句块
    {>text}           文本语句块 内容原样输出

    {/>}              语句块结束

    {>...}            特殊语句 php语句单行

### 注释

    <!--  -->        支持html注释，里面的内容都会被删掉
    {*    *}          php注释
    {// }        php注释

### for 循环

    {for:$a}                             <?php while($a):?>
    {for:$a,}                            <?php if (!empty($a) && is_array($a)): foreach($a as $item):?>
    {for:$a,$item,>$b}                   <?php if (!empty($a) && is_array($a)): foreach($a as $item):if (!($key >$b)): break; endif;?>
    {for:$i = 1, $i >= 1, $i ++}         <?php for($i = 1, $i >= 1, $i ++): ?>
    {for:$a,$item,10}                    <?php if (!empty($a) && is_array($a)):  $i = 0; foreach($a as $item): $i ++; if ($i > 10): break; endif;?>

    结束符会自动判断

### if 语句

    {if:...}
    {elseif:...}
    {if:$a,$b}               <?php if ($a) { echo $b;}?>    
    {if:$a,$b,$c}               <?php if ($a) { echo $b;} else { echo $c;}?>    

### switch 语句

    {switch:...}
    {switch:$a,1}             <?php switch($a): case 1:?>
    {case:...}
    {default:}

### 加载文件

    {@file.js}        自动识别加载css或js 文件
    {css:file}      引入css文件
    {js:file}         引入js文件
    {tpl:file}       加载模板文件


### 特殊标记

    {+}                  else
    {-}                  endif
    {forelse}            endforeach; else:
    {break}              break
    {continue}           continue
    {break:1}            break 1
    {continue:1}         continue 1
    {use:...}            use ...
    {url:...}            <?=$this->url(...)?>

### 符号标记

    {| ...}              {if:...}
    {+ ...}              {elseif:...}
    {~ ...}              {for:...}

### 结束标记

    {/if}               结束if
    {/|}                结束if
    {/switch}           结束switch
    {/*}                结束switch
    {/for}              结束for
    {/~}                结束for

### 注册方法

所有的方法必须注释，否则替换为 null

    {header:}          <?=$this->header()?>
    {footer:}          <?=$this->footer()?>

    注册方法 'a', 输出模板'b'  模板使用 {a:b,c,d,e}            会输出   <?=b(c,d,e)?>
    注册方法 'a', 输出模板'<?=b(%s)?>'  模板使用 {a:b,c,d,e}            会输出   <?=b(c,d,e)?>
    注册方法 'a', 输出模板'<?php b(%s, %s);?>'  模板使用 {a:b,c,d,e}            会输出   <?php b(c,d);?>

### 给方法传值

    {url:./admin?query=:$a:&v=:$b,false}       <?= $this->url('./admin?query='.$a.'&v='.$b', false) ?>
    {query:1,a=>b,c=>d}              <?php query(1, ['a'=>'b', 'c' => 'd']); ?>
### 原样输出

    {#...}           输出 {...}

### 全局赋值

    {this.a=b}       $this->a = 'b'
    {.a=b}       $this->a = 'b'
    {this.a=true}    $this->a = true  或 false
    {this.a=$b}      $this->a = $b
    {this.a='b}      $this->a = 'b'
    {this.a="b"}     $this->a = "b"

### 批量赋值

    // {$a,$b=1,2}      $a = 1 $b = 2

### 赋值

    // {$a=$b?$c:$d}      <?php $a = $b ? $c : $d;?>
    // {$a=$b?$d}         <php $a = $b ? $b : $d;?>
    // {$a=$b||$d}        <?php $a = $b ? $b : $d;?>
    // {$a=$b}            <?php $a = $b;?>

### 输出值

    {$a??$b}            <?= $a ?? $b?>
    {$a}            <?= $a ?>
    {$a->a}            <?= $a->a ?>
    {$a.a}            <?= $a['a'] ?>
    {=...}          输出 ... 执行结果

### LIVE (待实现)

不允许缓存

1. 使用方法

a.html
```tpl
a
{.b}
{>live}
aaa
{.a:}
bbb
{/>}
```

2. 实现原理：

在模板解析阶段，分离live代码并合并生成一个关联文件

a.phtml
```php
a
<?= $this->b ?>
>>section_ajdjdnkjjd<<
```
live_a.phtml
```php
<?php ob_start(); ?>
aaa
<?= $this->a() ?>
bbb
<?php $this->section['section_ajdjdnkjjd'] = ob_get_clean(); ?>
```
使用缓存功能时 获取 `$this->section` 进行字符串替换即可