<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Commerce\Documents;

use Change\Documents\Events;
use Change\Http\Rest\V1\Resources\DocumentLink;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Commerce\Documents\Process
 */
class Process extends \Compilation\Rbs\Commerce\Documents\Process
{
	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');

		/** @var $process Process */
		$process = $event->getDocument();
		if ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentResult)
		{
			$documentResult = $restResult;
			$um = $documentResult->getUrlManager();
			$modifiersOrder = [];

			foreach ($process->getModifiersOrder() as $modifier)
			{
				$lnk =  new DocumentLink($um, $modifier, DocumentLink::MODE_PROPERTY);
				$modifiersOrder[] = $lnk;
			}
			$documentResult->setProperty('modifiersOrder', $modifiersOrder);
		}
		elseif ($restResult instanceof \Change\Http\Rest\V1\Resources\DocumentLink)
		{

		}
	}

	/**
	 * @return \Change\Documents\AbstractDocument[]
	 */
	public function getAvailableModifiers()
	{
		return $this->getModifiersOrder(true);
	}

	/**
	 * @param boolean $activated
	 * @return \Change\Documents\AbstractDocument[]
	 */
	protected function getModifiersOrder($activated = false)
	{
		$q = $this->getDocumentManager()->getNewQuery('Rbs_Commerce_Fee');
		if ($activated)
		{
			$q->andPredicates($q->eq('orderProcess', $this), $q->activated());
		}
		else
		{
			$q->andPredicates($q->eq('orderProcess', $this));
		}

		/** @var $modifiers \Change\Documents\AbstractDocument[] */
		$modifiers = $q->getDocuments()->toArray();

		$q = $this->getDocumentManager()->getNewQuery('Rbs_Discount_Discount');
		if ($activated)
		{
			$q->andPredicates($q->eq('orderProcess', $this), $q->activated());
		}
		else
		{
			$q->andPredicates($q->eq('orderProcess', $this));
		}

		$modifiers = array_merge($modifiers, $q->getDocuments()->toArray());
		$modifiersOrder = $this->getModifiersOrderData();
		if (!is_array($modifiersOrder) || count($modifiersOrder) === 0)
		{
			return $modifiers;
		}

		$ordered = [];
		foreach ($modifiersOrder as $id)
		{
			foreach ($modifiers as $idx => $modifier)
			{
				if ($modifier->getId() == $id)
				{
					$ordered[] = $modifier;
					unset($modifiers[$idx]);
					break;
				}
			}
		}

		foreach ($modifiers as $modifier)
		{
			$ordered[] = $modifier;
		}
		return $ordered;
	}

	protected $ignoredPropertiesForRestEvents = array('model', 'modifiersOrderData');

	protected function processRestData($name, $value, \Change\Http\Event $event)
	{
		if ($name === 'modifiersOrder')
		{
			if (is_array($value))
			{
				$ids = [];
				foreach ($value as $jsonDoc)
				{
					if (isset($jsonDoc['id']))
					{
						/** @var $doc \Change\Documents\AbstractDocument */
						$doc = $this->getDocumentManager()->getDocumentInstance($jsonDoc['id']);
						if (($doc instanceof \Rbs\Commerce\Documents\Fee) || ($doc instanceof \Rbs\Discount\Documents\Discount)) {
							$ids[] = $doc->getId();
						}
					}
				}
				$this->setModifiersOrderData(count($ids) ? $ids : null);
			}
			return true;
		}
		return parent::processRestData($name, $value, $event); // TODO: Change the autogenerated stub
	}
}
