<?php

class Creativestyle_Richsnippets_Model_Dropdown {
	public function toOptionArray() {
		return array(
			array(
				'value' => '',
				'label' => '-',
			),
			array(
				'value' => 'http://schema.org/NewCondition',
				'label' => 'New',
			),
			array(
				'value' => 'http://schema.org/UsedCondition',
				'label' => 'Used',
			),
			array(
				'value' => 'http://schema.org/RefurbishedCondition',
				'label' => 'Refurbished',
			),
			array(
				'value' => 'http://schema.org/DamagedCondition',
				'label' => 'Damaged',
			),
		);
	}
}