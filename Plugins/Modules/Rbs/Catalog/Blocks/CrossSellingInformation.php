<?php
namespace Rbs\Catalog\Blocks;

use Change\Documents\Property;
use Change\Presentation\Blocks\Information;
use Change\Presentation\Blocks\ParameterInformation;

/**
 * @name \Rbs\Catalog\Blocks\CrossSellingInformation
 */
class CrossSellingInformation extends Information
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->setLabel($i18nManager->trans('m.rbs.catalog.blocks.cross-selling-label', $ucf));
		$this->addInformationMeta('title', Property::TYPE_STRING, false, null)
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.cross-selling-title', $ucf));
		$this->addInformationMeta('crossSellingType', ParameterInformation::TYPE_COLLECTION, true, 'ACCESSORIES')
			->setCollectionCode('Rbs_Catalog_Collection_CrossSellingType')
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.cross-selling-type', $ucf));
		$this->addInformationMeta('itemsPerSlide', Property::TYPE_INTEGER, true, 3)
			->setLabel($i18nManager->trans('m.rbs.catalog.blocks.cross-selling-items-per-slide', $ucf));
	}
}
