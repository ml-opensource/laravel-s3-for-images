<?php

namespace Fuzz\S3ForImages\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
	protected $artisan;

	public function setUp()
	{
		parent::setUp();

		$this->artisan = $this->app->make('Illuminate\Contracts\Console\Kernel');
		$this->artisan->call(
			'migrate', [
				'--database' => 'testbench',
				'--path'     => '../../../../tests/migrations',
			]
		);
	}

	protected function getEnvironmentSetUp($app)
	{
		parent::getEnvironmentSetUp($app);

		$app['config']->set('database.default', 'testbench');
		$app['config']->set(
			'database.connections.testbench', [
				'driver'   => 'sqlite',
				'database' => ':memory:',
				'prefix'   => ''
			]
		);
	}

	public function tearDown()
	{
		$this->artisan->call('migrate:rollback');
	}
}
