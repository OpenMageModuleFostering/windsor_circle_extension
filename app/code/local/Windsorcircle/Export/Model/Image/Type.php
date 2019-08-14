<?php
/**
 * Image Type Source File
 *
 * @category	Lyons
 * @package		package_name
 * @copyright	Copyright (c) 2012 Lyons Consulting Group (www.lyonscg.com)
 * @author		Mark Hodge (mhodge@lyonscg.com)
 */

class Windsorcircle_Export_Model_Image_Type{
		
		/**
		 * Select field in admin area for Image Type
		 * @return array
		 */
		public function toOptionArray(){
			return array(
				array('value'=>1, 'label'=>Mage::helper('windsorcircle_export')->__('Standard')),
				array('value'=>2, 'label'=>Mage::helper('windsorcircle_export')->__('Small'))
			);
		}
	}
