<?php
namespace ChangeTests\Change\Db\Mysql;

use Change\Db\Mysql\DbProvider;
use Change\Db\ScalarType;

class DbProviderTest extends \ChangeTests\Change\TestAssets\TestCase
{
	protected function setUp()
	{
		if (!in_array('mysql', \PDO::getAvailableDrivers()))
		{
			$this->markTestSkipped('PDO Mysql is not installed.');
		}

		$provider = $this->getApplicationServices()->getDbProvider();
		if (!($provider instanceof DbProvider))
		{
			$this->markTestSkipped('The Mysql DbProvider is not configured.');
		}
		$connectionInfos = $provider->getConnectionInfos();
		if (!isset($connectionInfos['database']))
		{
			$this->markTestSkipped('The Mysql database not defined!');
		}
	}


    public function testGetConnectionWithURL()
    {
        $provider = $this->getApplicationServices()->getDbProvider();
        $originalConnectionInfos = $provider->getConnectionInfos();

        $provider->setConnectionInfos(
            array( "url" => "mysql://" )
        );
        try
        {
            $pdo = $provider->getDriver();
            $this->fail('Invalid RuntimeException');
        }
        catch (\RuntimeException $e)
        {
            $this->assertStringStartsWith('Database URL is not valid', $e->getMessage());
        }

        $provider->setConnectionInfos(
            array( "url" => "ENV:MYSQL_TEST_URL" )
        );
        try
        {
            $pdo = $provider->getDriver();
            $this->fail('Invalid RuntimeException');
        }
        catch (\RuntimeException $e)
        {
            $this->assertStringEndsWith('is not set.', $e->getMessage());
        }

        $_ENV['MYSQL_TEST_URL'] = "mysql://" .
            (isset($infos['user']) ? $originalConnectionInfos['user'] : '') . ":" .
            (isset($infos['password']) ? $originalConnectionInfos['password'] : '') . "@" .
            (isset($infos['host']) ? $originalConnectionInfos['host'] : 'localhost') . ":" .
            (isset($infos['port']) ? $originalConnectionInfos['port'] : '3306') . "/" .
            $originalConnectionInfos['database'];

        $pdo = $provider->getDriver();
        $this->assertNotNull($pdo);
    }

	public function testGetInstance()
	{
		$provider = $this->getApplicationServices()->getDbProvider();

		/* @var $provider \Change\Db\Mysql\DbProvider */
		$this->assertInstanceOf('\Change\Db\Mysql\DbProvider', $provider);
		
		$this->assertEquals('mysql', $provider->getType());
		$this->assertNull($provider->getId());
		
		$pdo = $provider->getDriver();
		try 
		{
			$pdo->exec('INVALID SQL');
			$this->fail('Invalid PDO Exception');
			
		}
		catch (\PDOException $e)
		{
			$this->assertStringStartsWith('SQLSTATE', $e->getMessage());
			$this->assertEquals('42000', $e->getCode());
		}
		$provider->closeConnection();
	}
	

	public function testTransaction()
	{
		/** @var $provider DbProvider */
		$provider = $this->getApplicationServices()->getDbProvider();

		$event = new \Change\Events\Event('tm', $this, array('primary' => true));

		$this->assertFalse($provider->inTransaction());
		
		$provider->beginTransaction();
		
		$this->assertTrue($provider->inTransaction());
	
		$provider->commit($event);
		
		$this->assertFalse($provider->inTransaction());
		
		$provider->beginTransaction($event);
		
		$this->assertTrue($provider->inTransaction());
		
		$provider->rollBack($event);
		
		$this->assertFalse($provider->inTransaction());

		$event->setParam('primary', false);

		$provider->beginTransaction($event);
		$this->assertFalse($provider->inTransaction());

		$provider->closeConnection();
	}
	

	public function testValues()
	{
		/** @var $provider DbProvider */
		$provider = $this->getApplicationServices()->getDbProvider();

		$this->assertSame(1, $provider->phpToDB(true, ScalarType::BOOLEAN));
		$this->assertSame(true, $provider->dbToPhp(1, ScalarType::BOOLEAN));
		$this->assertSame(0, $provider->phpToDB(false, ScalarType::BOOLEAN));
		$this->assertSame(false, $provider->dbToPhp(0, ScalarType::BOOLEAN));
		
		$dt = new \DateTime('now', new \DateTimeZone('UTC'));
		$dbVal =  $dt->format('Y-m-d H:i:s');
		
		$this->assertSame($dbVal, $provider->phpToDB($dt, ScalarType::DATETIME));
		$this->assertEquals($dt, $provider->dbToPhp($dbVal, ScalarType::DATETIME));

		$provider->closeConnection();
	}

	public function testGetLastInsertId()
	{
		/** @var $provider DbProvider */
		$provider = $this->getApplicationServices()->getDbProvider();

		$pdo = $provider->getDriver();
		$pdo->exec('DROP TABLE IF EXISTS `test_auto_number`');
		$pdo->exec('CREATE TABLE `test_auto_number` (`auto` int(11) NOT NULL AUTO_INCREMENT, `test` int(11) NOT NULL, PRIMARY KEY (`auto`)) ENGINE=InnoDB AUTO_INCREMENT=5000');

		$pdo->beginTransaction();
		$pdo->exec('INSERT INTO `test_auto_number` (`auto`, `test`) VALUES (NULL, \'2\')');
		$auto = $provider->getLastInsertId('test_auto_number');
		$this->assertEquals(5000, $auto);
		$pdo->commit();

		$provider->closeConnection();
	}
}
