<?php
namespace Rbs\Timeline\Documents;

use Rbs\User\Events\AuthenticatedUser;
use Change\Documents\Events\Event;

	/**
 * @name \Rbs\Timeline\Documents\Message
 */
class Message extends \Compilation\Rbs\Timeline\Documents\Message
{

	protected function attachEvents($eventManager)
	{
		parent::attachEvents($eventManager);
		$eventManager->attach(array(Event::EVENT_CREATE, Event::EVENT_UPDATE), array($this, 'onDefaultSave'), 10);
	}

	public function onDefaultSave(Event $event)
	{
		/** @var $document Message */
		$document = $event->getDocument();
		if ($document->isPropertyModified('message'))
		{
			$event->getApplicationServices()->getRichTextManager()->render($document->getMessage(), 'Admin');
		}
		//first find targeted users by their identifiers
		$matches = [];
		preg_match_all('/\B(@\+?)([a-z0-9_\-]+)/i', $document->getMessage()->getRawText(), $matches, PREG_SET_ORDER);
		$userIdentifiers = [];
		$groupIdentifiers = [];
		foreach ($matches as $match)
		{
			if ($match[1] === '@')
			{
				$userIdentifiers[] = $match[2];
			}
			else if ($match[1] === '@+')
			{
				$groupIdentifiers[] = $match[2];
			}
		}

		$profileManager = $event->getApplicationServices()->getProfileManager();

		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$documentManager = $event->getApplicationServices()->getDocumentManager();

		//now get user from user identifiers and create notification or send a mail
		foreach ($userIdentifiers as $userIdentifier)
		{
			$dqb = $documentManager->getNewQuery('Rbs_User_User');
			$user = $dqb->andPredicates($dqb->eq('identifier', $userIdentifier))->getFirstDocument();
			if ($user)
			{
				$authenticatedUser = new \Rbs\User\Events\AuthenticatedUser($user);
				$contextDoc = $document->getContextIdInstance();
				if (!$contextDoc)
				{
					continue;
				}

				/* @var $user \Rbs\User\Documents\User */
				$params = [
					'documentLabel' => $contextDoc->getDocumentModel()->getPropertyValue($contextDoc, 'label', $contextDoc->__toString()),
					'authorName' => $document->getAuthorName(),
					'message' => $document->getMessage()->getRawText()
				];

				$userProfile = $profileManager->loadProfile($authenticatedUser, 'Change_User');
				$lcid = $userProfile->getPropertyValue('LCID')
				!= null ? $userProfile->getPropertyValue('LCID') : $i18nManager->getDefaultLCID();

				try
				{
					$documentManager->pushLCID($lcid);
					$notification = $documentManager->getNewDocumentInstanceByModelName('Rbs_Notification_Notification');
					/* @var $notification \Rbs\Notification\Documents\Notification */
					$notification->setUserId($user->getId());
					$notification->setCode('timeline_mention');
					$notification->getCurrentLocalization()->setMessage($i18nManager->transForLCID($lcid,
						'm.rbs.timeline.documents.message.notification-mention-message', ['ucf'], $params));
					$notification->setParams($params);
					$notification->save();

					//check user profile for mail notification time interval
					//if time interval is not set, create a job to send directly a mail
					$adminProfile = $profileManager->loadProfile($authenticatedUser, 'Rbs_Admin');
					if (!$adminProfile->getPropertyValue('notificationMailInterval'))
					{
						$jm = $event->getApplicationServices()->getJobManager();
						$arguments = [
							'params' => $params,
							'to' => [$user->getEmail()],
							'templateCode' => 'timeline_mention'
						];
						$jm->createNewJob('Rbs_Timeline_SendTemplateMail', $arguments);
					}

					$documentManager->popLCID();
				}
				catch (\Exception $e)
				{
					$event->getApplicationServices()->getLogging()->fatal($e);
					$documentManager->popLCID();
				}
			}
		}
		//TODO: do the same things for user group
	}

	public function onDefaultUpdateRestResult(\Change\Documents\Events\Event $event)
	{
		parent::onDefaultUpdateRestResult($event);
		$restResult = $event->getParam('restResult');
		if ($restResult instanceof \Change\Http\Rest\Result\DocumentResult)
		{
			$documentResult = $restResult;
			/* @var $message \Rbs\Timeline\Documents\Message */
			$message = $documentResult->getDocument();
			//For avatar
			$dm = $message->getDocumentManager();
			$user = $dm->getDocumentInstance($message->getAuthorId(), 'Rbs_User_User');

			//FIXME hardcoded value for default avatar url
			$avatar = 'Rbs/Admin/img/user-default.png';
			if ($user)
			{
				/* @var $user \Rbs\User\Documents\User */
				$pm = $event->getApplicationServices()->getProfileManager();
				$authenticatedUser = new AuthenticatedUser($user);
				$profile = $pm->loadProfile($authenticatedUser, 'Rbs_Admin');
				if ($profile && $profile->getPropertyValue('avatar'))
				{
					$avatar = $profile->getPropertyValue('avatar');
				}
			}
			$documentResult->setProperty('avatar', $avatar);
		}
		elseif ($restResult instanceof \Change\Http\Rest\Result\DocumentLink)
		{
			$documentLink = $restResult;
			/* @var $message \Rbs\Timeline\Documents\Message */
			//Add the message
			$message = $documentLink->getDocument();
			$documentLink->setProperty('message', $message->getMessage());
			//Add AuthorName and AuthorId
			$documentLink->setProperty('authorId', $message->getAuthorId());
			$documentLink->setProperty('authorName', $message->getAuthorName());
			//Add contextModel if document exist
			$contextDocument = $message->getContextIdInstance();
			if ($contextDocument)
			{
				/* @var $contextDocument \Change\Documents\AbstractDocument */
				$documentLink->setProperty('contextModel', $contextDocument->getDocumentModelName());
			}
			//For avatar & identifier
			$dm = $message->getDocumentManager();
			$user = $dm->getDocumentInstance($message->getAuthorId(), 'Rbs_User_User');

			//FIXME hardcoded value for default avatar url
			$avatar = 'Rbs/Admin/img/user-default.png';
			if ($user)
			{
				/* @var $user \Rbs\User\Documents\User */
				$pm = $event->getApplicationServices()->getProfileManager();
				$authenticatedUser = new AuthenticatedUser($user);
				$profile = $pm->loadProfile($authenticatedUser, 'Rbs_Admin');
				if ($profile && $profile->getPropertyValue('avatar'))
				{
					$avatar = $profile->getPropertyValue('avatar');
				}
				if ($user->getLogin())
				{
					$documentLink->setProperty('authorIdentifier', $user->getLogin());
				}
			}
			$documentLink->setProperty('avatar', $avatar);
		}
	}
}
