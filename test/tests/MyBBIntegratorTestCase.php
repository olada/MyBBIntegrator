<?php

class MyBBIntegratorTestCase extends \PHPUnit_Framework_TestCase {
	
	protected $mybb_integrator;

	public function setUp() {
		global $MyBBI;

		$this->mybb_integrator =& $MyBBI;
	}

	public function tearDown() {

	}
}