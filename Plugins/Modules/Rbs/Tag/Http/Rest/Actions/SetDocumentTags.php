<?php
/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Rbs\Tag\Http\Rest\Actions;

use Change\Http\Rest\V1\CollectionResult;
use Zend\Http\Response as HttpResponse;

/**
 * @name \Rbs\Tag\Http\Rest\Actions\SetDocumentTags
 */
class SetDocumentTags
{
	const MAX_TAGS = 1000;

	/**
	 * @param \Change\Http\Rest\V1\CollectionResult $result
	 * @return array
	 */
	protected function buildQueryArray($result)
	{
		$array = array('limit' => $result->getLimit(), 'offset' => $result->getOffset());
		if ($result->getSort())
		{
			$array['sort'] = $result->getSort();
			$array['desc'] = ($result->getDesc()) ? 'true' : 'false';
		}
		return $array;
	}

	/**
	 * Use Event Params: tags[], docId
	 * @param \Change\Http\Event $event
	 * @throws \Exception
	 */
	public function execute($event)
	{
		$tags = $event->getParam('tags');
		$docId = $event->getParam('docId');

		$transactionManager = $event->getApplicationServices()->getTransactionManager();
		try
		{
			$transactionManager->begin();

			$docManager = $event->getApplicationServices()->getDocumentManager();
			$model = $event->getApplicationServices()->getModelManager()->getModelByName('Rbs_Tag_Tag');

			$stmt = $event->getApplicationServices()->getDbProvider()->getNewStatementBuilder();
			$fb = $stmt->getFragmentBuilder();

			// Create delete all document's tags statement
			$stmt->delete($fb->table('rbs_tag_document'));
			$stmt->where($fb->eq($fb->column('doc_id'), $fb->integerParameter('docId')));
			$dq = $stmt->deleteQuery();
			$dq->bindParameter('docId', $docId);
			$dq->execute();

			// Create insert statement
			$stmt->insert($fb->table('rbs_tag_document'), 'doc_id', 'tag_id');
			$stmt->addValues($fb->integerParameter('docId'), $fb->integerParameter('tagId'));
			$iq = $stmt->insertQuery();

			foreach ($tags as $tag)
			{
				$tagId = null;
				if (is_numeric($tag))
				{
					$tagId = $tag;
				}
				elseif (is_array($tag) && isset($tag['id']))
				{
					$tagId = intval($tag['id']);
				}

				if ($tagId)
				{
					$tagDoc = $docManager->getDocumentInstance($tagId, $model);
					if ($tagDoc !== null)
					{
						$iq->bindParameter('docId', $docId);
						$iq->bindParameter('tagId', $tagId);
						$iq->execute();
					}
				}
			}

			$transactionManager->commit();
		}
		catch (\Exception $e)
		{
			throw $transactionManager->rollBack($e);
		}

		$getDocumentTags = new GetDocumentTags();
		$getDocumentTags->execute($event);

		$result = $event->getResult();
		if ($result instanceof CollectionResult)
		{
			$result->setHttpStatusCode(HttpResponse::STATUS_CODE_201);
		}
	}
}
