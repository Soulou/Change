<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Catalog\Documents;

use Change\Documents\AbstractModel;
use Change\Http\Rest\V1\ErrorResult;
use Rbs\Catalog\Product\AxisConfiguration;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Catalog\Documents\VariantGroup
 */
class VariantGroup extends \Compilation\Rbs\Catalog\Documents\VariantGroup
{
	/**
	 * @var array
	 */
	protected $variantConfiguration;

	/**
	 * @param AbstractModel $documentModel
	 */
	public function setDefaultValues(AbstractModel $documentModel)
	{
		parent::setDefaultValues($documentModel);
		$this->setAxesAttributes(array());
		$this->setAxesAttributes(array());
	}

	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATED, array($this, 'onDefaultCreated'));
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_CREATE, array($this, 'onDefaultCreate'), 10);
		$eventManager->attach(\Change\Documents\Events\Event::EVENT_UPDATE, array($this, 'onDefaultUpdate'), 10);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultCreate(\Change\Documents\Events\Event $event)
	{
		/* @var $variantGroup \Rbs\Catalog\Documents\VariantGroup */
		$variantGroup = $event->getDocument();
		if (!$variantGroup->getLabel() && $variantGroup->getRootProduct())
		{
			$variantGroup->setLabel($variantGroup->getRootProduct()->getLabel());
		}
		$variantGroup->normalizeAxesProperties();
		$variantGroup->checkOtherAttributes();
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultCreated(\Change\Documents\Events\Event $event)
	{
		/** @var $variantGroup VariantGroup */
		$variantGroup = $event->getDocument();
		$product = $variantGroup->getRootProduct();
		if ($product instanceof Product)
		{
			$product->setCategorizable(true);
			$product->setVariant(false);
			$product->setVariantGroup($variantGroup);
			$product->update();
		}

		if (is_array($this->variantConfiguration))
		{
			$arguments = $this->variantConfiguration;
			$arguments['variantGroupId'] = $this->getId();
			$job = $event->getApplicationServices()->getJobManager()
				->createNewJob('Rbs_Catalog_VariantConfiguration', $arguments);
			$this->setMeta('Job_VariantConfiguration', $job->getId());
			$this->saveMetas();
		}
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 */
	public function onDefaultUpdate(\Change\Documents\Events\Event $event)
	{
		if ($this->isPropertyModified('axesAttributes') || $this->isPropertyModified('axesConfiguration'))
		{
			$this->normalizeAxesProperties();
		}

		if ($this->isPropertyModified('othersAttributes'))
		{
			$this->checkOtherAttributes();
		}

		if ($this->isPropertyModified('axesAttributes') || $this->isPropertyModified('othersAttributes')
			|| $this->isPropertyModified('axesConfiguration')
		)
		{
			$arguments = ['variantGroupId' => $this->getId()];
			$job = $event->getApplicationServices()->getJobManager()->createNewJob('Rbs_Catalog_AxesConfiguration', $arguments);
			$this->setMeta('Job_AxesConfiguration', $job->getId());
		}

		if (is_array($this->variantConfiguration))
		{
			$arguments = $this->variantConfiguration;
			$arguments['variantGroupId'] = $this->getId();
			$job = $event->getApplicationServices()->getJobManager()
				->createNewJob('Rbs_Catalog_VariantConfiguration', $arguments);
			$this->setMeta('Job_VariantConfiguration', $job->getId());
		}
		$this->saveMetas();
	}

	protected function normalizeAxesProperties()
	{
		$attributes = [];
		$oldConfiguration = $this->getAxesConfiguration();
		if (!is_array($oldConfiguration))
		{
			$oldConfiguration = array();
		}

		foreach ($this->getAxesAttributes() as $attribute)
		{
			if ($attribute->getAxis() && $attribute->getValueType() != 'Group')
			{
				$hasConf = false;
				$attributes[$attribute->getId()] = $attribute;
				foreach ($oldConfiguration as $conf)
				{
					if ($conf['id'] == $attribute->getId())
					{
						$hasConf = true;
						break;
					}
				}

				if (!$hasConf)
				{
					$conf = new AxisConfiguration($attribute->getId());
					$oldConfiguration[] = $conf->toArray();
				}
			}
		}
		$this->getAxesAttributes()->fromArray(array_values($attributes));

		$nbAttributes = count($attributes);
		if ($nbAttributes)
		{
			$configuration = [];
			foreach ($oldConfiguration as $confArray)
			{
				$conf = (new AxisConfiguration())->fromArray($confArray);
				if (isset($attributes[$conf->getId()]))
				{
					$configuration[] = $conf;
					if (count($configuration) == $nbAttributes)
					{
						$conf->setUrl(true);
					}
				}
			}
			$this->setAxesConfiguration(array_map(function (AxisConfiguration $conf) { return $conf->toArray(); },
				$configuration));
		}
		else
		{
			$this->setAxesConfiguration(null);
		}
	}

	/**
	 * Check that the attributes set in other attributes cannot be used in axis
	 */
	protected function checkOtherAttributes()
	{
		$oldOtherAttributes = $this->getOthersAttributes();
		$newOtherAttributes = null;

		if ($oldOtherAttributes !== null)
		{
			$newOtherAttributes = array();
			foreach ($oldOtherAttributes as $oldOtherAttribute)
			{
				if (!$oldOtherAttribute->getAxis())
				{
					$newOtherAttributes[] = $oldOtherAttribute;
				}
			}
		}

		$this->setOthersAttributes($newOtherAttributes);
	}

	/**
	 * @param \Change\Documents\Events\Event $event
	 * @throws \RuntimeException
	 */
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');
		/** @var $document VariantGroup */
		$document = $event->getDocument();

		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$cs = $event->getServices('commerceServices');
			if ($cs instanceof \Rbs\Commerce\CommerceServices)
			{
				$restResult->setProperty('variantConfiguration',
					$cs->getAttributeManager()->buildVariantConfiguration($document));

				$jobs = array();
				$aJobId = $document->getMeta('Job_AxesConfiguration');
				if ($aJobId)
				{
					$jobs[] = ['id' => $aJobId, 'name' => 'Rbs_Catalog_AxesConfiguration'];
				}

				$vJobId = $document->getMeta('Job_VariantConfiguration');
				if ($vJobId)
				{
					$jobs[] = ['id' => $vJobId, 'name' => 'Rbs_Catalog_VariantConfiguration'];
				}
				$restResult->setProperty('jobs', $jobs);
			}
			else
			{
				throw new \RuntimeException('CommerceServices not set', 999999);
			}
		}
		else if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{
			$restResult->setProperty('rootProductId', $document->getRootProductId());
			$restResult->setProperty('axesAttributesId', $document->getAxesAttributeIds());
		}
	}

	/**
	 * Process the incoming REST data $name and set it to $value
	 * @param string $name
	 * @param mixed $value
	 * @param \Change\Http\Event $event
	 * @return boolean
	 */
	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		if ($name === 'variantConfiguration')
		{
			if (is_array($value))
			{
				$this->variantConfiguration = $value;
				return true;
			}

			$result = new ErrorResult('INVALID-VARIANT-CONFIGURATION', 'Invalid products variants configuration', HttpResponse::STATUS_CODE_409);
			$event->setResult($result);
			return false;
		}
		return parent::processRestData($name, $value, $event);
	}

	/**
	 * @param boolean $publishedProductOnly
	 * @return \Rbs\Catalog\Documents\Product[]
	 */
	public function getVariantProducts($publishedProductOnly = false)
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Catalog_Product');
		$query->andPredicates($query->eq('variantGroup', $this), $query->neq('id', $this->getRootProductId()));

		if ($publishedProductOnly)
		{
			$query->andPredicates($query->published());
		}

		return $query->getDocuments()->toArray();
	}

	/**
	 * Check if the variant group configuration define that product must be generated for intermediary configuration
	 * @return boolean
	 */
	public function mustGenerateOnlyLastVariant()
	{
		$axesConfig = $this->getAxesConfiguration();

		$count = 0;
		foreach ($axesConfig as $config)
		{
			if ($config['url'] === true)
			{
				$count++;
			}
		}

		if ($count > 1)
		{
			return false;
		}
		return true;
	}

	/**
	 * Get the list of attributes id that compose the axes attributes
	 * @return array
	 */
	public function getAxesAttributeIds()
	{
		$attributeIds = array();
		$attributes = $this->getAxesAttributes();
		foreach ($attributes as $attribute)
		{
			$attributeIds[] = $attribute->getId();
		}
		return $attributeIds;
	}
}