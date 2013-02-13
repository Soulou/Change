<?php
namespace Change\Documents;

/**
 * @name \Change\Documents\AbstractDocument
 * @api
 */
abstract class AbstractDocument implements \Serializable
{		
	/**
	 * @var integer
	 */
	private $persistentState = DocumentManager::STATE_NEW;

	/**
	 * @var integer
	 */
	private $id = 0;
	
	/**
	 * @var string
	 */
	private $documentModelName;
	
	/**
	 * @var string
	 */
	private $treeName;
	
	/**
	 * @var array
	 */
	private $modifiedProperties = array();

	/**
	 * @var array<String,String|String[]>
	 */
	private $metas;
	
	/**
	 * @var boolean
	 */
	private $modifiedMetas = false;
	
	/**
	 * @var array
	 */
	private $propertiesErrors;
	
	/**
	 * @var array
	 */
	protected $corrections;
	
	/**
	 * @var \Change\Documents\DocumentManager
	 */
	protected $documentManager;
	
	/**
	 * @var \Change\Documents\AbstractModel
	 */
	protected $documentModel;
	
	/**
	 * @var \Change\Documents\AbstractService
	 */
	protected $documentService;

	/**
	 * @param \Change\Documents\DocumentManager $manager
	 * @param \Change\Documents\AbstractModel $model
	 * @param \Change\Documents\AbstractService $service
	 */
	public function __construct(\Change\Documents\DocumentManager $manager, \Change\Documents\AbstractModel $model, \Change\Documents\AbstractService $service)
	{
		$this->setDocumentContext($manager, $model, $service);
	}
	
	/**
	 * @param \Change\Documents\DocumentManager $manager
	 * @param \Change\Documents\AbstractModel $model
	 * @param \Change\Documents\AbstractService $service
	 */
	public function setDocumentContext(\Change\Documents\DocumentManager $manager, \Change\Documents\AbstractModel $model, \Change\Documents\AbstractService $service)
	{
		$this->documentManager = $manager;
		$this->documentModel = $model;
		$this->documentModelName = $model->getName();
		$this->documentService = $service;
	}

	/**
	 * This class is not serializable
	 * @return null
	 */
	public function serialize()
	{
		return null;
	}

	/**
	 * @param string $serialized
	 * @return void
	 */
	public function unserialize($serialized)
	{
		return;
	}

	/**
	 * @api
	 * @return \Change\Documents\DocumentManager
	 */
	public function getDocumentManager()
	{
		return $this->documentManager;
	}
	
	/**
	 * @api
	 * @return \Change\Documents\AbstractModel
	 */
	public function getDocumentModel()
	{
		return $this->documentModel;
	}
	
	/**
	 * @api
	 * @return string
	 */
	public function getDocumentModelName()
	{
		return $this->documentModelName;
	}
	
	/**
	 * @api
	 * @return \Change\Documents\AbstractService
	 */
	public function getDocumentService()
	{
		return $this->documentService;
	}
	
	/**
	 * @param integer $id
	 * @param integer $persistentState
	 * @param string|null $treeName
	 */
	public function initialize($id, $persistentState = null, $treeName = null)
	{
		$this->id = intval($id);
		if ($persistentState!== null)
		{
			$this->setPersistentState($persistentState);
		}
		if ($treeName !== null)
		{
			$this->setTreeName($treeName);
		}
	}

	/**
	 * @param string|null $treeName
	 */
	public function setTreeName($treeName)
	{
		$this->treeName = ($treeName !== null) ? strval($treeName) : null;
	}
	
	/**
	 * @api
	 * @return string|null
	 */
	public function getTreeName()
	{
		return $this->treeName;
	}
	
	/**
	 * @api
	 */
	public function reset()
	{
		$this->modifiedProperties = array();
		$this->metas = null;
		$this->modifiedMetas = false;
		$this->propertiesErrors = null;
		$this->corrections = null;
		if ($this->persistentState === DocumentManager::STATE_LOADED)
		{
			$this->persistentState = DocumentManager::STATE_INITIALIZED;
		}
		elseif($this->persistentState === DocumentManager::STATE_NEW)
		{
			$this->setDefaultValues($this->documentModel);
		}
	}

	/**
	 * @param \Change\Documents\AbstractModel $documentModel
	 */
	public function setDefaultValues(\Change\Documents\AbstractModel $documentModel)
	{
		$this->persistentState = DocumentManager::STATE_NEW;
		foreach ($documentModel->getProperties() as $property)
		{
			/* @var $property \Change\Documents\Property */
			if (!$property->getLocalized() && $property->getDefaultValue() !== null)
			{
				$property->setValue($this, $property->getDefaultValue());
			}
		}
		$this->clearModifiedProperties();
	}
	
	/**
	 * @return integer \Change\Documents\DocumentManager::STATE_*
	 */
	public function getPersistentState()
	{
		return $this->persistentState;
	}

	/**
	 * @param integer $newValue \Change\Documents\DocumentManager::STATE_*
	 * @return integer oldState
	 */
	public function setPersistentState($newValue)
	{
		$oldState = $this->persistentState;
		switch ($newValue) 
		{
			case DocumentManager::STATE_LOADED:
				$this->clearModifiedProperties();
			case DocumentManager::STATE_NEW:
			case DocumentManager::STATE_INITIALIZED:
			case DocumentManager::STATE_LOADING:	
			case DocumentManager::STATE_DELETED:
			case DocumentManager::STATE_SAVING:
				$this->persistentState = $newValue;
				break;
		}
		return $oldState;
	}
	
	/**
	 * @api
	 * @return boolean
	 */
	public function isDeleted()
	{
		return $this->persistentState === DocumentManager::STATE_DELETED;
	}
	
	/**
	 * @api
	 * @return boolean
	 */
	public function isNew()
	{
		return $this->persistentState === DocumentManager::STATE_NEW;
	}
	
	/**
	 * @api
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}
	
	
	
	protected function checkLoaded()
	{
		if ($this->persistentState === DocumentManager::STATE_INITIALIZED)
		{
			$this->documentManager->loadDocument($this);
		}
	}

	/**
	 * @api
	 */
	public function save()
	{
		if ($this->isNew())
		{
			$this->create();
		}
		else
		{
			$this->update();
		}
	}
	
	/**
	 * @api
	 */
	public function create()
	{
		$this->getDocumentService()->create($this);
	}
	
	/**
	 * @api
	 */
	public function update()
	{
		$this->getDocumentService()->update($this);
	}
	
	/**
	 * @api
	 */
	public function delete()
	{
		$this->getDocumentService()->delete($this);
	}
	
	/**
	 * Overrided by compiled document class
	 */
	protected function validateProperties()
	{
		foreach ($this->documentModel->getProperties() as $property)
		{
			/* @var $property \Change\Documents\Property */
			if ($property->getLocalized())
			{
				$i18nPart = $this->getCurrentI18nPart();
				/* @var $i18nPart \Change\Documents\AbstractI18nDocument */
				if ($i18nPart->getPersistentState() == DocumentManager::STATE_NEW || $i18nPart->isPropertyModified($property->getName()))
				{
					$this->validatePropertyValue($property);
				}
			}
			else
			{
				if ($this->getPersistentState() == DocumentManager::STATE_NEW || $this->isPropertyModified($property->getName()))
				{
					$this->validatePropertyValue($property);
				}
			}
		}
	}
	
	/**
	 * @param \Change\Documents\Property $property
	 * @return boolean
	 */
	protected function validatePropertyValue($property)
	{
		$value = $property->getValue($this);
		if ($property->getType() === \Change\Documents\Property::TYPE_DOCUMENTARRAY)
		{
			$nbValue = count($value);
			if ($nbValue === 0)
			{
				if (!$property->isRequired())
				{
					return true;
				}
				$this->addPropertyError($property->getName(), new \Change\I18n\PreparedKey('c.constraints.isempty', array('ucf')));
				return false;
			}
			elseif ($property->getMaxOccurs() > 1 && $nbValue > $property->getMaxOccurs())
			{
				$args = array('maxOccurs' => $property->getMaxOccurs());
				$this->addPropertyError($property->getName(), new \Change\I18n\PreparedKey('c.constraints.maxoccurs', array('ucf'), array($args)));
				return false;
			}
			elseif ($property->getMinOccurs() > 1 && $nbValue < $property->getMinOccurs())
			{
				$args = array('minOccurs' => $property->getMinOccurs());
				$this->addPropertyError($property->getName(), new \Change\I18n\PreparedKey('c.constraints.minoccurs', array('ucf'), array($args)));
				return false;
			}
			
		} 
		elseif ($value === null || $value === '')
		{
			if (!$property->isRequired()) 
			{	
				return true;
			}
			$this->addPropertyError($property->getName(), new \Change\I18n\PreparedKey('c.constraints.isempty', array('ucf')));
			return false;
		}
		elseif ($property->hasConstraints()) 
		{
			$constraintManager = $this->documentService->getConstraintsManager();
			$defaultParams =  array('documentId' => $this->getId(),
									'modelName' => $this->getDocumentModelName(),
									'propertyName' => $property->getName(),
									'applicationServices' => $this->documentService->getApplicationServices(),
									'documentServices' => $this->documentService->getDocumentServices());
			foreach ($property->getConstraintArray() as $name => $params) 
			{
				$params += $defaultParams;
				$c = $constraintManager->getByName($name, $params);
				if (!$c->isValid($value)) 
				{
					$this->addPropertyErrors($property->getName(), $c->getMessages());
					return false;
				}
			}
		}
		return true;
	}
	
	/**
	 * @api
	 * validate document and return boolean result
	 * @return boolean
	 */
	public function isValid()
	{
		$this->propertiesErrors = null;
		$this->validateProperties();
		return !$this->hasPropertiesErrors();
	}
	
	/**
	 * @api
	 * @return array<propertyName => string[]>
	 */
	public function getPropertiesErrors()
	{
		if ($this->hasPropertiesErrors())
		{
			return $this->propertiesErrors;
		}
		return array();
	}
	
	/**
	 * @return boolean
	 */
	protected function hasPropertiesErrors()
	{
		return is_array($this->propertiesErrors) && count($this->propertiesErrors);
	}
	
	/**
	 * @param string $propertyName
	 * @param string $error
	 */
	protected function addPropertyError($propertyName, $error)
	{
		if ($error !== null)
		{
			$this->propertiesErrors[$propertyName][] = $error;
		}
	}
	
	/**
	 * @param string $propertyName
	 * @param string[] $errors
	 */
	protected function addPropertyErrors($propertyName, $errors)
	{		
		if (is_array($errors) && count($errors))
		{
			foreach ($errors as $error)
			{
				/* @var $error string */
				$this->addPropertyError($propertyName, $error);
			}
		}
	}	
	
	/**
	 * @param string $propertyName
	 */
	protected function clearPropertyErrors($propertyName = null)
	{
		if ($propertyName === null)
		{
			$this->propertiesErrors = null;
		}
		elseif (is_array($this->propertiesErrors) && isset($this->propertiesErrors[$propertyName]))
		{
			unset($this->propertiesErrors[$propertyName]);
		}
	}

	/**
	 * @api
	 * @return boolean
	 */
	public function hasNonLocalizedModifiedProperties()
	{
		return count($this->modifiedProperties) > 0;
	}

	/**
	 * @api
	 * @return boolean
	 */
	public function hasModifiedProperties()
	{
		return $this->hasNonLocalizedModifiedProperties();
	}

	/**
	 * @api
	 * @return string[]
	 */
	public function getNonLocalizedModifiedPropertyNames()
	{
		return array_keys($this->modifiedProperties);
	}

	/**
	 * @api
	 * @return string[]
	 */
	public function getModifiedPropertyNames()
	{
		return $this->getNonLocalizedModifiedPropertyNames();
	}
	
	/**
	 * @api
	 * @param string $propertyName
	 * @return boolean
	 */
	public function isPropertyModified($propertyName)
	{
		return array_key_exists($propertyName, $this->modifiedProperties);
	}

	/**
	 * @param string $propertyName
	 * @return mixed
	 */
	protected function getOldPropertyValue($propertyName)
	{
		if (array_key_exists($propertyName, $this->modifiedProperties))
		{
			return $this->modifiedProperties[$propertyName];
		}
		return null;
	}

	/**
	 * @api
	 */
	protected function clearModifiedProperties()
	{
		$this->modifiedProperties = array();
	}
		
	/**
	 * @param string $propertyName
	 * @param mixed $value
	 */
	protected function setOldPropertyValue($propertyName, $value)
	{
		if (!array_key_exists($propertyName, $this->modifiedProperties))
		{
			$this->modifiedProperties[$propertyName] = $value;
		}
	}
	
	/**
	 * @api
	 * @param string $propertyName
	 */
	public function removeOldPropertyValue($propertyName)
	{
		if (array_key_exists($propertyName, $this->modifiedProperties))
		{
			unset($this->modifiedProperties[$propertyName]);
		}
	}

	/**
	 * Called every time a property has changed.
	 * @param string $propertyName Name of the property that has changed.
	 */
	protected function propertyChanged($propertyName)
	{
		$this->documentService->propertyChanged($this, $propertyName);
	}

	/**
	 * @api
	 * @param \Change\Documents\AbstractDocument $b
	 * @return boolean
	 */
	public function equals($b)
	{
		return $this === $b || (($b instanceof AbstractDocument) && $b->getId() === $this->getId());
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getDocumentModelName().' '.$this->getId();
	}
		
	// Metadata management

	/**
	 * @api
	 */
	public function saveMetas()
	{
		if ($this->modifiedMetas)
		{
			$this->documentService->saveMetas($this, $this->metas);
			$this->modifiedMetas = false;
		}
	}
	
	/**
	 *
	 */
	protected function checkMetasLoaded()
	{
		if ($this->metas === null)
		{
			$this->metas = $this->documentManager->loadMetas($this);
			$this->modifiedMetas = false;
		}
	}
	
	/**
	 * @api
	 * @return boolean
	 */
	public function hasModifiedMetas()
	{
		return $this->modifiedMetas;
	}
		
	/**
	 * @api
	 * @return array
	 */
	public function getMetas()
	{
		$this->checkMetasLoaded();
		return $this->metas;
	}
	
	/**
	 * @api
	 * @param array $metas
	 */
	public function setMetas($metas)
	{
		$this->checkMetasLoaded();
		if (count($this->metas))
		{
			$this->metas = array();
			$this->modifiedMetas = true;
		}
		if (is_array($metas))
		{
			foreach ($metas as $name => $value)
			{
				$this->metas[$name] = $value;
			}
			$this->modifiedMetas = true;
		}
	}
	
	/**
	 * @api
	 * @param string $name
	 * @return boolean
	 */
	public function hasMeta($name)
	{
		$this->checkMetasLoaded();
		return isset($this->metas[$name]);
	}

	/**
	 * @api
	 * @param string $name
	 * @return mixed
	 */
	public function getMeta($name)
	{
		$this->checkMetasLoaded();
		return isset($this->metas[$name]) ? $this->metas[$name] : null;
	}

	/**
	 * @api
	 * @param string $name
	 * @param mixed|null $value
	 */
	public function setMeta($name, $value)
	{
		$this->checkMetasLoaded();
		if ($value === null)
		{
			if (isset($this->metas[$name]))
			{
				unset($this->metas[$name]);
				$this->modifiedMetas = true;
			}
		}
		elseif (!isset($this->metas[$name]) || $this->metas[$name] != $value)
		{
			$this->metas[$name] = $value;
			$this->modifiedMetas = true;
		}
	}
	
	// Correction Method
	
	/**
	 * @return \Change\Documents\Correction[]
	 */
	protected function getCorrections()
	{
		if ($this->corrections === null)
		{
			$this->setCorrections($this->documentManager->loadCorrections($this));
		}
		return $this->corrections;
	}

	/**
	 * @param \Change\Documents\Correction[]|null $corrections
	 */
	protected function setCorrections($corrections = null)
	{
		if (is_array($corrections))
		{
			$this->corrections = array();
			foreach ($corrections as $correction)
			{
				if ($correction instanceof Correction)
				{
					$key = $correction->getLcid();
					if ($key === null) {$key = Correction::NULL_LCID_KEY;}
					$this->corrections[$key] = $correction;
				}
			}
		}
		else
		{
			$this->corrections = null;
		}
	}
	
	/**
	 * @param \Change\Documents\Correction $correction
	 */
	protected function addCorrection(Correction $correction)
	{
		$key = $correction->getLcid();
		if ($key === null) {$key = Correction::NULL_LCID_KEY;}
		$this->corrections[$key] = $correction;
	}
	
	/**
	 * @param \Change\Documents\Correction $correction
	 */
	public function removeCorrection(Correction $correction)
	{
		if (is_array($this->corrections))
		{
			$key = $correction->getLcid();
			if ($key === null) {$key = Correction::NULL_LCID_KEY;}
			unset($this->corrections[$key]);
		}
	}
	
	/**
	 * @return boolean
	 */
	public function hasCorrection()
	{
		$corrections = $this->getCorrections();
		return isset($corrections[Correction::NULL_LCID_KEY]);
	}	
	
	/**
	 * @return \Change\Documents\Correction
	 */
	public function getCorrection()
	{
		$corrections = $this->getCorrections();
		if (!isset($corrections[Correction::NULL_LCID_KEY]))
		{
			$correction = $this->documentManager->getNewCorrectionInstance($this, null);
			$this->addCorrection($correction);
			return $correction;
		}
		return $corrections[Correction::NULL_LCID_KEY];
	}
	
	// Generic Method
	
	/**
	 * @return \DateTime
	 */
	abstract public function getCreationDate();
	
	/**
	 * @param \DateTime $creationDate
	 */
	abstract public function setCreationDate($creationDate);
	
	/**
	 * @return \DateTime
	 */
	abstract public function getModificationDate();
	
	/**
	 * @param \DateTime $modificationDate
	 */
	abstract public function setModificationDate($modificationDate);
}