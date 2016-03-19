<?php

class ForumTest extends MyBBIntegratorTestCase {
	
	const PASSWORDED_FORUM_ID = 3; 
	const NON_PASSWORDED_FORUM_ID = 2;

	const WRONG_PASSWORD = "wrong_password";
	const CORRECT_PASSWORD = "passworded";
	
	/**
	 * Test for checking forum password
	*/
	public function testCheckForumPasswordForPasswordedForum() {
		$this->assertFalse(
			$this->mybb_integrator->checkForumPassword(self::PASSWORDED_FORUM_ID, self::WRONG_PASSWORD), 
			"passworded forum should be false for wrong password"
		);
		$this->assertTrue(
			$this->mybb_integrator->checkForumPassword(self::PASSWORDED_FORUM_ID, self::CORRECT_PASSWORD),
			"passworded forum should be true for correct password"
		);
	}

	/**
	 * Testing that check for password for non-passworded forum is TRUE
	*/
	public function testCheckForumPasswordForNonPasswordedForum() {
		$this->assertTrue(
			$this->mybb_integrator->checkForumPassword(self::NON_PASSWORDED_FORUM_ID, self::WRONG_PASSWORD),
			"non-passworded forum should be true for any password"
		);
		$this->assertTrue(
			$this->mybb_integrator->checkForumPassword(self::NON_PASSWORDED_FORUM_ID, self::CORRECT_PASSWORD),
			"non-passworded forum should be true for any password"
		);
	}
}