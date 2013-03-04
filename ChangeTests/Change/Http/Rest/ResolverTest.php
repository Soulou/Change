<?php
namespace ChangeTests\Change\Http\Rest;

use Change\Http\Rest\Resolver;

class ResolverTest extends \ChangeTests\Change\TestAssets\TestCase
{

	public function testInitialize()
	{
		if (!class_exists('Compilation\Change\Documents\AbstractDocumentServices'))
		{
			$compiler = new \Change\Documents\Generators\Compiler($this->getApplication(), $this->getApplicationServices());
			$compiler->generate();
		}
	}

	/**
	 * @param \Change\Http\Event $event
	 * @param string|null $path
	 * @param string $method
	 * @return void
	 */
	protected function resetEvent(\Change\Http\Event $event, $path = null, $method = \Zend\Http\Request::METHOD_GET)
	{
		$event->setAction(null);
		$event->setResult(null);
		$event->setParams(array());
		$event->getRequest()->setMethod($method);
		$event->getRequest()->setPath($path);
	}

	/**
	 * @depends testInitialize
	 */
	public function testConstruct()
	{
		$resolver = new Resolver();
		$this->assertArrayHasKey('getCorrection', $resolver->getResourceActionClasses());
		$this->assertArrayNotHasKey('test', $resolver->getResourceActionClasses());

		$resolver->registerActionClass('test', 'test');
		$this->assertArrayHasKey('test', $resolver->getResourceActionClasses());

		$application = $this->getApplication();
		$event = new \Change\Http\Event();
		$event->setRequest(new \ChangeTests\Change\Http\Rest\TestAssets\Request());
		$event->getRequest()->setMethod(\Zend\Http\Request::METHOD_GET);

		$event->setUrlManager(new \ChangeTests\Change\Http\Rest\TestAssets\UrlManager());
		$event->setTarget(new \Change\Http\Rest\Controller($application));
		$event->setApplicationServices($this->getApplicationServices());
		$event->setDocumentServices($this->getDocumentServices());

		return $event;
	}

	/**
	 * @depends testConstruct
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testRootResolver($event)
	{
		$resolver = new Resolver();

		$this->resetEvent($event, '');
		$resolver->resolve($event);
		$this->assertFalse(is_callable($event->getAction()));
		$this->assertEquals('', $event->getParam('namespace', 'fail'));
		$this->assertFalse($event->getParam('isDirectory'));

		$this->resetEvent($event, '/');
		$resolver->resolve($event);
		$this->assertEquals('', $event->getParam('namespace', 'fail'));
		$this->assertTrue($event->getParam('isDirectory'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/', 'POST');
		$resolver->resolve($event);
		$this->assertFalse(is_callable($event->getAction()));
		$this->assertInstanceOf('\Change\Http\Rest\Result\ErrorResult', $event->getResult());

		return $event;
	}

	/**
	 * @depends testRootResolver
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testDiscover($event)
	{
		$resolver = new Resolver();

		$this->resetEvent($event, '/test/');
		$resolver->resolve($event);
		$this->assertTrue(is_callable($event->getAction()));
		$this->assertEquals('test', $event->getParam('namespace', 'fail'));
		$this->assertTrue($event->getParam('isDirectory'));

		$this->resetEvent($event, '/test/', 'POST');
		$resolver->resolve($event);

		$this->assertFalse(is_callable($event->getAction()));
		$this->assertInstanceOf('\Change\Http\Rest\Result\ErrorResult', $event->getResult());
		return $event;
	}

	/**
	 * @depends testDiscover
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testCollection($event)
	{
		$resolver = new Resolver();
		$this->resetEvent($event, '/resources/Project/Tests/NotFound/');
		$resolver->resolve($event);
		$this->assertEquals('fail', $event->getParam('modelName', 'fail'));

		$this->resetEvent($event, '/resources/Project/Tests/Basic/');
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Basic/', \Zend\Http\Request::METHOD_PUT);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));
		$this->assertInstanceOf('\Change\Http\Rest\Result\ErrorResult', $event->getResult());

		$this->resetEvent($event, '/resources/Project/Tests/Basic/', \Zend\Http\Request::METHOD_DELETE);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));
		$this->assertInstanceOf('\Change\Http\Rest\Result\ErrorResult', $event->getResult());


		return $event;
	}

	/**
	 * @depends testCollection
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testDocument($event)
	{
		$resolver = new Resolver();

		$this->resetEvent($event, '/resources/Project/Tests/Basic/', \Zend\Http\Request::METHOD_POST);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Basic/-3', \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));

		/* @var $tbs \Project\Tests\Documents\BasicService */
		$tbs = $event->getDocumentServices()->getProjectTestsBasic();
		$document = $tbs->getInstanceRo5001();

		$this->resetEvent($event, '/resources/Project/Tests/Basic/' . $document->getId(), \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId(), \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Basic/' . $document->getId() . '/fr_FR', \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Basic/' . $document->getId(), \Zend\Http\Request::METHOD_PUT);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Basic/' . $document->getId(), \Zend\Http\Request::METHOD_DELETE);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Basic', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		return $event;
	}

	/**
	 * @depends testDocument
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testLocalizedDocument($event)
	{
		$resolver = new Resolver();

		$this->resetEvent($event, '/resources/Project/Tests/Localized/', \Zend\Http\Request::METHOD_POST);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Localized/-3', \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertNull($event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));

		/* @var $tbs \Project\Tests\Documents\LocalizedService */
		$tbs = $event->getDocumentServices()->getProjectTestsLocalized();
		$document = $tbs->getInstanceRo5002();

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId(), \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId() . '/fr_FR', \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId() . '/zz_ZZ', \Zend\Http\Request::METHOD_GET);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId(), \Zend\Http\Request::METHOD_POST);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId() . '/fr_FR', \Zend\Http\Request::METHOD_POST);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertFalse(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId(), \Zend\Http\Request::METHOD_PUT);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId() . '/fr_FR', \Zend\Http\Request::METHOD_PUT);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));


		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId(), \Zend\Http\Request::METHOD_DELETE);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));

		$this->resetEvent($event, '/resources/Project/Tests/Localized/' . $document->getId() . '/fr_FR', \Zend\Http\Request::METHOD_DELETE);
		$resolver->resolve($event);
		$this->assertEquals('Project_Tests_Localized', $event->getParam('modelName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId'));
		$this->assertTrue(is_callable($event->getAction()));
		return $event;
	}

	/**
	 * @depends testLocalizedDocument
	 * @param \Change\Http\Event $event
	 * @return \Change\Http\Event
	 */
	public function testResActions($event)
	{
		$resolver = new Resolver();

		$this->resetEvent($event, '/resourcesactions/startValidation/-3');
		$resolver->resolve($event);
		$this->assertNull($event->getAction());


		/* @var $tbs \Project\Tests\Documents\BasicService */
		$tbs = $event->getDocumentServices()->getProjectTestsBasic();
		$document = $tbs->getInstanceRo5001();

		$this->resetEvent($event, '/resourcesactions/startValidation/' . $document->getId());
		$resolver->resolve($event);

		$this->assertEquals('startValidation', $event->getParam('resourcesActionName', 'fail'));
		$this->assertEquals($document->getId(), $event->getParam('documentId', 'fail'));
		$this->assertTrue(is_callable($event->getAction()));


		/* @var $tbs \Project\Tests\Documents\LocalizedService */
		$tbs = $event->getDocumentServices()->getProjectTestsLocalized();
		$document = $tbs->getInstanceRo5002();

		$this->resetEvent($event, '/resourcesactions/startValidation/' . $document->getId());
		$resolver->resolve($event);
		$this->assertFalse(is_callable($event->getAction()));

		$this->resetEvent($event, '/resourcesactions/startValidation/' . $document->getId(). '/fr_FR');
		$resolver->resolve($event);
		$this->assertTrue(is_callable($event->getAction()));

		return $event;
	}

}