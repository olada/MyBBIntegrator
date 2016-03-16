<?php

class UserTest extends MyBBIntegratorTestCase {
	
	/**
	 * Get list of members
	 * Count should be exactly one - which is the admin user.
	 * Username should be "admin"
	*/
	public function testGetAllMembers() {
		$members = $this->mybb_integrator->getMembers();
		$this->assertEquals(1, count($members));
	}
}