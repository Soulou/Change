<?php
namespace Rbs\Plugins\Commands;

use Change\Commands\Events\Event;
use Rbs\Plugins\Std\Signtool;

/**
 * @name \Rbs\Plugins\Commands\Sign
 */
class Sign
{
	/**
	 * @param Event $event
	 */
	public function execute(Event $event)
	{
		$application = $event->getApplication();
		$applicationServices = $event->getApplicationServices();

		$response = $event->getCommandResponse();

		$type = $event->getParam('type');
		$vendor = $event->getParam('vendor');
		$name = $event->getParam('name');
		$key = $event->getParam('key');
		$cert = $event->getParam('cert');
		$passPhrase = $event->getParam('passphrase');

		try
		{
			if ($type === 'theme')
			{
				$plugin = $applicationServices->getPluginManager()->getTheme($vendor, $name);
			}
			else
			{
				$plugin = $applicationServices->getPluginManager()->getModule($vendor, $name);
			}

			if ($plugin === null)
			{
				$response->addErrorMessage('Plugin not found.');
				return;
			}

			$signTool = new Signtool($application);
			$result = $signTool->sign($plugin, $key, $cert, $passPhrase);
			if (!$result)
			{
				$response->addErrorMessage('Could not sign plugin.');
				return;
			}
			$response->addInfoMessage('Plugin signed.');
		}
		catch (\Exception $e)
		{
			$applicationServices->getLogging()->exception($e);
			$response->addErrorMessage($e->getMessage());
		}
	}
}