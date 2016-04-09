<?php

class UserTest extends MyBBIntegratorTestCase {
	
	const NORMAL_USER_NAME = "normal";
	const NORMAL_USER_PASSWORD = "normal123";

	/**
	 * Get list of members
	 * Count should be exactly one - which is the admin user.
	 * Username should be "admin"
	*/
	public function testGetAllMembers() {
		$members = $this->mybb_integrator->getMembers();
		$this->assertEquals(2, count($members));
	}

	/**
	 * Test if isLoggedIn state works after logging in as normal user
	*/
	public function testLoginAndLoggedInState() {
		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged out before testing login"
		);
		$status = $this->mybb_integrator->login(self::NORMAL_USER_NAME, self::NORMAL_USER_PASSWORD);
		$this->assertTrue(
			$status,
			"login as normal user should return true"
		);
		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in after logging in as normal user"
		);
	}

	/**
	 * Check if logout works after we tested login
	 * @depends testLoginAndLoggedInState
	*/
	public function testLogoutWithoutSessionIDOrLogoutKeyShouldFail() {
		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in before testing logout"
		);

		$status = $this->mybb_integrator->logout();

		$this->assertFalse(
			$status,
			"Logout status should be false when logging out without session id or logoutkey"
		);

		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in because logout fails"
		);
	}

	public function testLogoutWithSessionID() {
		$this->mybb_integrator->login(self::NORMAL_USER_NAME, self::NORMAL_USER_PASSWORD);

		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in before testing logout"
		);

		$this->mybb_integrator->getIntegratorVar('mybb')->input['sid'] = $this->mybb_integrator->getIntegratorVar('mybb')->session->sid;

		$status = $this->mybb_integrator->logout();

		$this->assertTrue(
			$status,
			"Logout status should be true after successfully logging out"
		);

		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged out after logging out"
		);
	}

	public function testLogoutWithLoginKey() {
		$this->mybb_integrator->login(self::NORMAL_USER_NAME, self::NORMAL_USER_PASSWORD);
		
		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in before testing logout"
		);

		$this->mybb_integrator->getIntegratorVar('mybb')->input['logoutkey'] = $this->mybb_integrator->getIntegratorVar('mybb')->user['logoutkey'];

		$status = $this->mybb_integrator->logout();

		$this->assertTrue(
			$status,
			"Logout status should be true after successfully logging out"
		);

		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged out after logging out"
		);
	}

	public function testLogoutWhenNotLoggedInShouldFail() {
		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should not be logged in before testing logout"
		);

		$status = $this->mybb_integrator->logout();
		
		$this->assertTrue(
			$status,
			"logout should fail because of not being logged in"
		);

		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should still be logged out - as before"
		);
	}
}