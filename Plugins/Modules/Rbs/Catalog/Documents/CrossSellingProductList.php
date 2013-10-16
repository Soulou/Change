<?php
namespace Rbs\Catalog\Documents;

use Change\Documents\Events\Event;
use Change\I18n\PreparedKey;

/**
 * @name \Rbs\Catalog\Documents\CrossSellingProductList
 */
class CrossSellingProductList extends \Compilation\Rbs\Catalog\Documents\CrossSellingProductList
{
	/**
	 * @param \Zend\EventManager\EventManagerInterface $eventManager
	 */
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$callback = function(Event $event)
		{
			$document = $event->getDocument();

			$documentServices = $document->getDocumentServices();
			$newType = $document->getCrossSellingType();

			$query = new \Change\Documents\Query\Query($documentServices, 'Rbs_Catalog_CrossSellingProductList');
			$pb = $query->getPredicateBuilder();
			$p1 = $pb->eq('product', $document->getProductId());
			$p2 = $pb->neq('id', $document->getId());
			$query->andPredicates($p1, $p2);
			$dbq = $query->dbQueryBuilder();
			$fb = $dbq->getFragmentBuilder();
			$dbq->addColumn($fb->alias($query->getColumn('crossSellingType'), 'type'));

			$sq = $dbq->query();

			$types = $sq->getResults($sq->getRowsConverter()->addStrCol('type'));

			if (in_array($newType, $types))
			{
				$errors = $event->getParam('propertiesErrors', array());
				$errors['crossSellingType'][] = new PreparedKey('m.rbs.catalog.documents.crosssellingproductlist.list-already-exists',
																array('ucf'),
																array('type' => $newType, 'product' => $document->getProduct()->getLabel()));
				$event->setParam('propertiesErrors', $errors);
			}
		};

		//Unicity check
		$eventManager->attach(array(Event::EVENT_CREATE, Event::EVENT_UPDATE), $callback, 3);
	}

	protected function onCreate()
	{
		//Default label = cross selling type
		if ($this->getCrossSellingType() && !$this->getLabel())
		{
			$this->setLabel($this->getLabelFromCrossSellingType());
		}
	}

	/**
	 * @return string|null
	 */
	protected function getLabelFromCrossSellingType()
	{
		$cm = new \Change\Collection\CollectionManager($this->getDocumentServices());
		$collectionCode = 'Rbs_Catalog_Collection_CrossSellingType';
		if (is_string($collectionCode))
		{
			$c = $cm->getCollection($collectionCode);
			if ($c)
			{
				$i = $c->getItemByValue($this->getCrossSellingType());
				if ($i)
				{
					return $i->getLabel();
				}
			}
		}
		return null;
	}
}