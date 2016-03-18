<?php

class MyBBIntegratorFactory {

	private $mybb_integrator_original;

	public function __construct($mybb_integrator_instance) {
		$this->mybb_integrator_original = $mybb_integrator_instance;
	}

	public function getInstance() {
		$clone = clone $this->mybb_integrator_original;
		return $clone;
	}
	
}