<?php

class Creativestyle_Richsnippets_Model_Attributes extends Mage_Core_Model_Abstract {

	public function toOptionArray() {

		if (version_compare(Mage::getVersion(), "1.4.1", "<")) return false;

		$attributes = Mage::getResourceModel( 'catalog/product_attribute_collection' )
		                  ->addVisibleFilter()
		                  ->addFieldToFilter( 'frontend_input', array( 'text', 'select', 'textarea' ) );

		$attributeArray[] = array(
			'label' => '-',
			'value' => ''
		);

		foreach ( $attributes as $attribute ) {
			$attributeArray[] = array(
				'label' => $attribute->getData( 'frontend_label' ),
				'value' => $attribute->getData( 'attribute_code' )
			);
		}

		return $attributeArray;
	}
}