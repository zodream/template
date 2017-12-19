<?php
namespace Zodream\Domain\Template;

use Zodream\Disk\File;
use Zodream\Helpers\Str;
use Zodream\Infrastructure\Support\Html;

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

class Template {

    public function render($content) {
        if (empty($content)) {
            return $content;
        }
        $content = preg_replace($this->salePattern, '', $content);
        return $this->renderContent($this->parse($content));
    }

	public function renderFile(File $file) {
		if (!$file->exist()) {
			return false;
		}
		return $this->render($file->read());
	}

    protected function renderContent($content) {
        $obLevel = ob_get_level();
        ob_start();
        extract($this->get(), EXTR_SKIP);
        try {
            eval('?>'.$content);
        } catch (\Exception $e) {
            $this->handleViewException($e, $obLevel);
        } catch (\Throwable $e) {
            $this->handleViewException(new \Exception($e), $obLevel);
        }
        return ltrim(ob_get_clean());
    }
}