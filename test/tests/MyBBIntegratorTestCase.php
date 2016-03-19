<?php

class MyBBIntegratorTestCase extends \PHPUnit_Framework_TestCase {
	
	private static $factory;
	protected $mybb_integrator;

	public function setUp() {
		$this->mybb_integrator = self::$factory->getInstance();
	}

	public function tearDown() {
		unset($this->mybb_integrator);
	}

	public static function setFactory($mybb_integrator_factory) {
		self::$factory = $mybb_integrator_factory;
	}
}