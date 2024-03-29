<?php
namespace Zodream\Template\Concerns;
/**
 * 自定义视图函数
 * @author zx648
 *
 */
trait ConditionTrait {

	private mixed $_switchCondition = null;
	private mixed $_switchValue;

	/**
	 * 拓展switch
	 * @param string $condition 条件或要输出的值
	 * @param string $value 要输出的值
	 */
	public function swi(mixed $condition, mixed $value = null) {
		if (null == $value) {
			$this->_switchCondition = null;
			$this->_switchValue = $condition;
		} else  {
			$this->_switchCondition = $condition;
			$this->_switchValue = $value;
		}
	}

	/**
	 * 拓展case
	 * @param string|boolean $condition 条件
	 * @param string $value 可以更改输出的值，不必先用 $this->swi
	 * @return null|string
	 */
	public function cas(mixed $condition, mixed $value = null) {
		if (!is_null($value)) {
			$this->_switchValue = $value;
		}
		if ((is_null($this->_switchCondition) && true === $condition) ||
			(!is_null($this->_switchCondition) && $this->_switchCondition == $condition)) {
			return $this->_switchValue;
		}
		return null;
	}

	/**
	 * 替换标志
	 * @param string $name
	 * @param string|integer|array $key
	 * @return mixed|null
	 */
	public function tag(string $name, mixed $key) {
		$args = array();
		if (!is_array($key)) {
			for ($i = 1; $i < func_num_args(); $i+=2) {
				$args[func_get_arg($i)] = func_get_arg($i + 1);
			};
		} else {
			$args = $key;
		}
		if (isset($args[$name])) {
			return $args[$name];
		}
		return null;
	}
}