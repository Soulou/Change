<?php
namespace Rbs\Workflow\Documents;

use Change\Documents\Events\Event as DocumentEvent;

/**
 * @name \Rbs\Workflow\Documents\Task
 */
class Task extends \Compilation\Rbs\Workflow\Documents\Task
{
	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(DocumentEvent::EVENT_CREATED, array($this, 'addJobOnDeadLine'));
	}

	/**
	 * @param DocumentEvent $event
	 */
	public function addJobOnDeadLine(DocumentEvent $event)
	{
		$task = $event->getDocument();
		if ($task instanceof \Rbs\Workflow\Documents\Task && $task->getDeadLine())
		{
			$jobManager = new \Change\Job\JobManager();
			$jobManager->setDocumentServices($task->getDocumentServices());
			$startDate = clone($task->getDeadLine());

			$jobManager->createNewJob('Rbs_Workflow_ExecuteDeadLineTask',
				array('taskId' => $task->getId(), 'deadLine' => $startDate->format('c')),
				$startDate
			);
		}
	}

	/**
	 * @param array $context
	 * @param integer $userId
	 * @return \Change\Workflow\Interfaces\WorkflowInstance|null
	 * @throws \Exception
	 */
	public function execute(array $context = array(), $userId = 0)
	{
		$documentServices = $this->getDocumentServices();
		$documentManager = $documentServices->getDocumentManager();

		$LCID = $this->getDocumentLCID();

		if (count($context))
		{
			$this->setContext($context);
		}

		if ($this->getRole())
		{
			$this->setUserId($userId);
		}

		if ($this->hasModifiedProperties())
		{
			$transactionManager = $documentServices->getApplicationServices()->getTransactionManager();
			try
			{
				$transactionManager->begin();
				$this->update();
				$transactionManager->commit();
			}
			catch (\Exception $e)
			{
				throw $transactionManager->rollBack($e);
			}
		}

		$wm = new \Change\Workflow\WorkflowManager();
		$wm->setDocumentServices($documentServices);
		$workflowInstance = null;
		try
		{
			if ($LCID)
			{
				$documentManager->pushLCID($LCID);
			}
			$workflowInstance = $wm->processWorkflowInstance($this->getId(), $context);

			if ($LCID)
			{
				$documentManager->popLCID();
			}
		}
		catch (\Exception $e)
		{
			if ($LCID)
			{
				$documentManager->popLCID($e);
			}
		}
		return $workflowInstance;
	}
}