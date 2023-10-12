<?php
declare(strict_types=1);
namespace Zodream\Template;

use Zodream\Helpers\BinaryReader;

class CharReader extends BinaryReader {

    protected int $lastMaker = -1;
    /**
     * 将指定位置的内容替换成新内容
     * @var array{int, int, string}
     */
    protected array $replaceItems = [];


    public function maker(): void {
        $this->lastMaker = $this->position;
    }

    /**
     * 替换标记于内容的内容
     * @param string $value
     * @param int $end 结束位置
     * @return void
     */
    public function replace(string $value = '', int $end = -1): void {
        if ($end > $this->lastMaker) {
            $this->seek($end);
        }
        $this->replaceItems[] = [$this->lastMaker, $this->position, $value];
        $this->maker();
    }

    /**
     * 移动指针到最近的一个字符串位置
     * @param string ...$items
     * @return int items_index
     */
    public function jumpTo(string ...$items): int {
        list($i, $item) = $this->minIndex(...$items);
        if ($i < 0) {
            return -1;
        }
        $this->position = $i;
        return $item;
    }

    public function isWhitespaceUntil(int $end): bool {
        if ($end > $this->length) {
            $end = $this->length;
        }
        $i = $this->position;
        while ($i < $end) {
            $i ++;
            if ($this->readChar($i) !== ' ') {
                return false;
            }
        }
        return true;
    }

    public function jumpWhitespace(): bool {
        if ($this->current() !== ' ') {
            return false;
        }
        $i = $this->position;
        while ($i < $this->length() - 1) {
            $i ++;
            if ($this->readChar($i) !== ' ') {
                $i --;
                break;
            }
        }
        $this->seek($i);
        return true;
    }



    public function __toString(): string {
        $items = [];
        $i = 0;
        foreach ($this->replaceItems as $item) {
            if ($item[0] > $i) {
                $items[] = substr($this->content, $i, $item[0] - $i);
            }
            $items[] = $item[2];
            $i = $item[1];
        }
        if ($i < $this->length() - 1) {
            $items[] = substr($this->content, $i);
        }
        return implode('', $items);
    }
}