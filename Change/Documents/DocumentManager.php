<?php
namespace Change\Documents;

/**
 * @name \Change\Documents\DocumentManager
 */
class DocumentManager
{
	/**
	 * @var \Change\Application\ApplicationServices
	 */
	protected $applicationServices;
	
	/**
	 * @var \Change\Documents\DocumentServices
	 */
	protected $documentServices;
	
	
	/**
	 * Document instances by id
	 * @var array<integer, \Change\Documents\AbstractDocument>
	 */
	protected $documentInstances = array();
	
	/**
	 * @var array
	 */
	protected $tmpRelationIds = array();
	
	/**
	 * Temporay identifier for new persistent document
	 * @var integer
	 */
	protected $newInstancesCounter = 0;
	
	/**
	 * @param \Change\Application\ApplicationServices $applicationServices
	 * @param \Change\Documents\ModelManager $modelManager
	 */
	public function __construct(\Change\Application\ApplicationServices $applicationServices, \Change\Documents\DocumentServices $documentServices)
	{
		$this->applicationServices = $applicationServices;
		$this->documentServices = $documentServices;
	}
	
	/**
	 * @return \Change\Documents\DocumentServices
	 */
	protected function getDocumentServices()
	{
		return $this->documentServices;
	}
	
	
	/**
	 * @return \Change\Db\DbProvider
	 */
	protected function getDbProvider()
	{
		return $this->applicationServices->getDbProvider();
	}
	
	/**
	 * @return \Change\I18n\I18nManager
	 */
	protected function getI18nManager()
	{
		return $this->applicationServices->getI18nManager();
	}
	
	/**
	 * @return \Change\Documents\ModelManager
	 */
	protected function getModelManager()
	{
		return $this->getDocumentServices()->getModelManager();
	}
	
	/**
	 * @param \Change\Documents\AbstractDocument $document
	 */
	public function postUnserialze(\Change\Documents\AbstractDocument $document)
	{
		throw new \LogicException('not implemented');
		//$document->setDocumentContext($this, $model, $service)
	}

	/**
	 * @param  \Change\Documents\AbstractI18nDocument $i18nDocument
	 */
	public function postI18nUnserialze(\Change\Documents\AbstractI18nDocument $i18nDocument)
	{
		throw new \LogicException('not implemented');
		//$documentI18n->setDocumentContext($this);
	}

	/**
	 * @param \Change\Documents\AbstractDocument $document
	 */
	public function loadDocument(\Change\Documents\AbstractDocument $document)
	{
		$model = $document->getDocumentModel();
		$fieldMapping = $this->getFieldMapping($model, false);

		$propertyBag = $this->getDbProvider()->getDocumentProperties($document->getId(), $model->getRootName(), $fieldMapping);
		if ($propertyBag)
		{
			$document->setDocumentProperties($propertyBag);
			$document->setPersistentState(AbstractDocument::PERSISTENTSTATE_LOADED);
		}
		else
		{
			$document->setPersistentState(AbstractDocument::PERSISTENTSTATE_NEW);
		}
	}
	
	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @return array()
	 */
	public function loadMetas(\Change\Documents\AbstractDocument $document)
	{
		throw new \LogicException('not implemented');
		return array();
	}
	
	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @param array $metas
	 */
	public function saveMetas(\Change\Documents\AbstractDocument $document, $metas)
	{
		throw new \LogicException('not implemented');
	}
	
	/**
	 * 
	 * @param \Change\Documents\AbstractDocument $document
	 * @param string $propertyName
	 * @return integer[]
	 */
	public function getPropertyDocumentIds(\Change\Documents\AbstractDocument $document, $propertyName)
	{
		throw new \LogicException('not implemented');
		return array();
	}
	
	
	/**
	 * @param string $modelName
	 * @return \Change\Documents\AbstractDocument
	 */
	public function getNewDocumentInstanceByModelName($modelName)
	{
		$model = $this->getModelManager()->getModelByName($modelName);
		if ($model === null)
		{
			throw new \InvalidArgumentException('Invalid model name (' . $modelName . ')');
		}
		return $this->getNewDocumentInstanceByModel($model);
	}
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @return \Change\Documents\AbstractDocument
	 */
	public function getNewDocumentInstanceByModel(\Change\Documents\AbstractModel $model)
	{
		$newDocument = $this->createNewDocumentInstance($model);
		$this->newInstancesCounter--;
		$newDocument->initialize($this->newInstancesCounter, AbstractDocument::PERSISTENTSTATE_NEW);
		return $newDocument;
	}
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @return \Change\Documents\AbstractDocument
	 */
	protected function createNewDocumentInstance(\Change\Documents\AbstractModel $model)
	{
		$service = $this->getDocumentServices()->get($model->getName());
		$className = $this->getDocumentClassFromModel($model);	
		/* @var $newDocument \Change\Documents\AbstractDocument */
		return new $className($this, $model, $service);
	}
	
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @return \Change\Documents\AbstractI18nDocument
	 */
	protected function createNewI18nDocumentInstance(\Change\Documents\AbstractModel $model)
	{
		$className = $this->getI18nDocumentClassFromModel($model);
		/* @var $newDocument \Change\Documents\AbstractDocument */
		return new $className($this);
	}
	
	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @param string $LCID
	 * @return \Change\Documents\AbstractI18nDocument
	 */
	public function getI18nDocumentInstanceByDocument(\Change\Documents\AbstractDocument $document, $LCID)
	{
		$i18nPart = $this->createNewI18nDocumentInstance($document->getDocumentModel());
		$i18nPart->initialize($document->getId(), $LCID, AbstractDocument::PERSISTENTSTATE_NEW);
		if (!$document->persistentStateIsNew())
		{
			$fieldMapping = $this->getFieldMapping($document->getDocumentModel(), true);
			$propertyBag = $this->getDbProvider()->getI18nDocumentProperties($document->getId(), $LCID, $document->getDocumentModel()->getRootName(), $fieldMapping);
			if ($propertyBag)
			{
				
				$i18nPart->setDocumentProperties($propertyBag);
				if ($i18nPart->getDeletedDate())
				{
					$i18nPart->setPersistentState(AbstractDocument::PERSISTENTSTATE_DELETED);
				}
				else
				{
					$i18nPart->setPersistentState(AbstractDocument::PERSISTENTSTATE_LOADED);
				}
			}
		}
		return $i18nPart;
	}
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @param boolean $localised
	 */
	protected function getFieldMapping(\Change\Documents\AbstractModel $model, $localised)
	{
		$sm = $this->getDbProvider()->getSchemaManager();
		$mapping = array();
		foreach ($model->getProperties() as $property)
		{
			/* @var $property \Change\Documents\Property */
			if ($property->getLocalized() == $localised)
			{
				$mapping[$property->getName()] = $sm->getDocumentFieldName($property->getName());
			}
		}
		return $mapping;
	}
	
	/**
	 * @param integer $documentId
	 * @param \Change\Documents\AbstractModel $model
	 * @return \Change\Documents\AbstractDocument|null
	 */
	public function getDocumentInstance($documentId, \Change\Documents\AbstractModel $model = null)
	{
		$id = intval($documentId);
		if ($id > 0)
		{
			if ($this->isInCache($id))
			{
				return $this->getFromCache($id);
			}
			
			$rootModelname = ($model) ? $model->getRootName() : null;
			
			$constructorInfos = $this->getDbProvider()->getDocumentInitializeInfos($id, $rootModelname);
			if ($constructorInfos !== null)
			{
				list ($modelName, $treeId) = $constructorInfos;
				$documentModel = $this->getModelManager()->getModelByName($modelName);
				if ($documentModel !== null)
				{
					$document = $this->createNewDocumentInstance($documentModel);
					$document->initialize($id, AbstractDocument::PERSISTENTSTATE_INITIALIZED, $treeId);
					$this->putInCache($id, $document);
					return $document;
				}
				else
				{
					$this->applicationServices->getLogging()->error(__METHOD__ . ' Invalid model name: ' . $modelName);
				}
			}
		}
		return null;
	}
		
	/**
	 * @param \Change\Documents\AbstractDocument $document
	 * @return integer
	 */
	public function initializeRelationDocumentId($document)
	{
		$id = $document->getId();
		if ($id < 0)
		{
			$this->putInCache($id, $document);
			$this->tmpRelationIds[$id] = $id;
		}
		return $id;
	}
	
	/**
	 * @param integer $documentId
	 * @return integer
	 * @throws Exception
	 */
	public function resolveRelationDocumentId($documentId)
	{
		if ($documentId < 0)
		{
			$documentId = isset($this->tmpRelationIds[$documentId]) ? $this->tmpRelationIds[$documentId] : $documentId;
			if (!$this->isInCache($documentId))
			{
				throw new \Exception('document ' . $documentId . ' not found');
			}
		}
		return intval($documentId);
	}
	
	/**
	 * @param integer $documentId
	 * @return \Change\Documents\AbstractDocument
	 * @throws Exception
	 */
	public function getRelationDocument($documentId)
	{
		if ($documentId < 0)
		{
			$id = intval(isset($this->tmpRelationIds[$documentId]) ? $this->tmpRelationIds[$documentId] : $documentId);
			if ($this->isInCache($id))
			{
				return $this->getFromCache($id);
			}
			throw new \Exception('document ' . $documentId . '/'. $id . ' not found');
		}
		return $this->getDocumentInstance($documentId);
	}
	
	/**
	 * @param integer $tmpId
	 * @param integer $documentId
	 */
	protected function updateTemporaryRelationId($tmpId, $documentId)
	{
		if (isset($this->tmpRelationIds[$tmpId]))
		{
			$this->tmpRelationIds[$tmpId] = $documentId;
		}
	}	
	
	/**
	 * @param integer $documentId
	 * @return boolean
	 */
	protected function isInCache($documentId)
	{
		return isset($this->documentInstances[intval($documentId)]);
	}
	
	/**
	 * @param integer $documentId
	 * @return \Change\Documents\AbstractDocument
	 */
	protected function getFromCache($documentId)
	{
		return $this->documentInstances[intval($documentId)];
	}
		
	/**
	 * @param integer $documentId
	 * @param \Change\Documents\AbstractDocument $document
	 * @return void
	 */
	protected function putInCache($documentId, $document)
	{
		$this->documentInstances[$documentId] = $document;
	}
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @return string
	 */
	protected function getDocumentClassFromModel($model)
	{
		return '\\' . implode('\\', array($model->getVendorName(), $model->getShortModuleName(), 'Documents', $model->getShortName()));
	}
	
	/**
	 * @param \Change\Documents\AbstractModel $model
	 * @return string
	 */
	protected function getI18nDocumentClassFromModel($model)
	{
		return '\\' . implode('\\', array('Compilation', $model->getVendorName(), $model->getShortModuleName(), 'Documents', $model->getShortName().'I18n'));
	}
	
	// Working lang.
	
	/**
	 * @var string[] ex: "en_GB" or "fr_FR"
	 */
	protected $LCIDStack = array();
	
	/**
	 * Get the current lcid.
	 * @api
	 * @return string ex: "en_GB" or "fr_FR"
	 */
	public function getLCID()
	{

		if (count($this->LCIDStack) > 0)
		{
			return end($this->LCIDStack);
		}
		else
		{
			return $this->getI18nManager()->getLCID();
		}
	}
	
	/**
	 * Push a new working language code.
	 * @api
	 * @throws \InvalidArgumentException
	 * @param string $LCID ex: "fr_FR"
	 */
	public function pushLCID($LCID)
	{
		if (!in_array($LCID, $this->getI18nManager()->getSupportedLCIDs()))
		{
			throw new \InvalidArgumentException('Not supported LCID: ' . $LCID);
		}
		array_push($this->LCIDStack, $LCID);
	}
	
	/**
	 * Pop the last working language code.
	 * @api
	 * @throws \LogicException if there is no lang to pop
	 * @throws \Exception if provided
	 * @param \Exception $exception
	 */
	public function popLCID($exception = null)
	{
		// FIXME: what if the exception was raized by pushLCID (and so no lang was pushed)?
		if ($this->getLCIDStackSize() === 0)
		{
			throw new \LogicException('No language to pop.');
		}
		array_pop($this->LCIDStack);
		if ($exception !== null)
		{
			throw $exception;
		}
	}
	
	/**
	 * Get the lang stack size.
	 * @api
	 * @return integer
	 */
	public function getLCIDStackSize()
	{
		return count($this->LCIDStack);
	}
}