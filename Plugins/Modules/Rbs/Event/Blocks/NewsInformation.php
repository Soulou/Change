<?php
namespace Rbs\Event\Blocks;

/**
 * @name \Rbs\Event\Blocks\NewsInformation
 */
class NewsInformation extends \Rbs\Event\Blocks\Base\BaseEventInformation
{
	public function onInformation(\Change\Events\Event $event)
	{
		parent::onInformation($event);
		$i18nManager = $event->getApplicationServices()->getI18nManager();
		$ucf = array('ucf');
		$this->setLabel($i18nManager->trans('m.rbs.event.blocks.news-label', $ucf));
		$this->getParameterInformation('docId')->setAllowedModelsNames('Rbs_Event_News')
			->setLabel($i18nManager->trans('m.rbs.event.blocks.news-doc', $ucf));
		$this->getParameterInformation('templateName')->setDefaultValue('news.twig');
		$this->setFunctions(array('Rbs_Event_News' => $i18nManager->trans('m.rbs.event.blocks.news-function', $ucf)));
	}
}
