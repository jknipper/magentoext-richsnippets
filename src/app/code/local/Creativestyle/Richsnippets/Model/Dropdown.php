<?php

class Creativestyle_Richsnippets_Model_Dropdown extends Mage_Core_Model_Abstract {
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