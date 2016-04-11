<?php

class UserTest extends MyBBIntegratorTestCase {
	
	const NORMAL_USER_NAME = "normal";
	const NORMAL_USER_PASSWORD = "normal123";
	const WRONG_SESSION_ID = "aabb";

	const WRONG_USER_NAME = "wrong_user";
	const WRONG_USER_PASSWORD = "wrong_password";

	const ADMIN_USER_ID = 1;
	const NORMAL_USER_ID = 2;

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

	public function testLogoutWithWrongAndCorrectSessionID() {
		$this->mybb_integrator->login(self::NORMAL_USER_NAME, self::NORMAL_USER_PASSWORD);

		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged in before testing logout"
		);

		$this->mybb_integrator->getIntegratorVar('mybb')->input['sid'] = self::WRONG_SESSION_ID;

		$status = $this->mybb_integrator->logout();

		$this->assertFalse(
			$status,
			"Logout status should be false after logging out with wrong session id"
		);

		$this->assertTrue(
			$this->mybb_integrator->isLoggedIn(),
			"should still be logged in because previous logout attempt must have failed"
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

	/**
	 * @depends testLogoutWithLoginKey
	*/
	public function testLoginWithWrongCredentials() {
		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should be logged out before testing login"
		);

		$status = $this->mybb_integrator->login(self::WRONG_USER_NAME, self::WRONG_USER_PASSWORD);

		$this->assertFalse(
			$status,
			"Login Routine should have failed due to wrong validation"
		);

		$this->assertFalse(
			$this->mybb_integrator->isLoggedIn(),
			"should still be logged out after wrong login"
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

	public function testIsSuperAdmin() {
		$this->assertTrue(
			$this->mybb_integrator->isSuperAdmin(self::ADMIN_USER_ID),
			"admin user should be super admin"
		);

		$this->assertFalse(
			$this->mybb_integrator->isSuperAdmin(self::NORMAL_USER_ID),
			"normal user should not be super admin"
		);
	}

	public function testRegisterCorrect() {
		$user_data = array(
		    'username' => 'Testuser',
		    'password' => 'difficultpassword',
		    'password2' => 'difficultpassword',
		    'email' => 'some_address@example.com',
		    'email2' => 'some_address@example.com',
		    'hideemail' => 1,
		    'invisible' => 0,
		    'receivepms' => 1
		);

		$register_status = $this->mybb_integrator->register($user_data);

		$this->assertFalse(
			is_array($register_status),
			"registration should work, therefore no array containing error messages should be returned"
		);

		$this->assertEquals(
			"Thank you for registering on MyBB Forum Name, Testuser.<br />You will now be taken back to the main page.",
			$register_status,
			"default message for correct registration should be in return status"
		);
	}

	public function testRegisterFails() {
		$user_data = array(
		    'username' => 'Testuser',
		    'password' => 'password',
		    'password2' => 'password_does_not_match',
		    'email' => 'some_address@example.com',
		    'email2' => 'some_address@example.com',
		    'hideemail' => 1,
		    'invisible' => 0,
		    'receivepms' => 1
		);

		$register_status = $this->mybb_integrator->register($user_data);

		$this->assertTrue(
			is_array($register_status),
			"when registration fails, an array containing error messages should be returned"
		);

		__($register_status,0,0);
	}
}