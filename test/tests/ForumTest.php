<?php

class ForumTest extends MyBBIntegratorTestCase {
	
	const PASSWORDED_FORUM_ID = 3; 
	const NON_PASSWORDED_FORUM_ID = 2;

	const WRONG_PASSWORD = "wrong_password";
	const CORRECT_PASSWORD = "passworded";

	const NEW_CATEGORY_NAME = "TestCreateCategory-Name";
	const NEW_CATEGORY_DESCRIPTION = "TestCreateCategory-Description";
	const NEW_CATEGORY_ID = 5;
	
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

	/**
	 * Test creating a category.
	 * This test is located in this class because in MyBB, a category is just a special Forum (only type differs)
	*/
	public function testCreateCategory() {
		$category_data = array(
			'name' => self::NEW_CATEGORY_NAME
		);
		$return_data = $this->mybb_integrator->createCategory($category_data);
		$this->assertTrue(
			count($return_data) > count($category_data),
			"Returning array should have more entries than the original parameter due to populating missing keys"
		);

		$this->assertEquals(
			self::NEW_CATEGORY_ID,
			$return_data['fid']
		);

		$this->assertEquals(
			self::NEW_CATEGORY_ID,
			$return_data['parentlist']
		);
	}

	public function testCreateCategoryWithNegativePid() {
		$category_data = array(
			'name' => self::NEW_CATEGORY_NAME,
			'pid' => -4
		);
		$return_data = $this->mybb_integrator->createCategory($category_data);

		$this->assertEquals(
			0,
			$return_data['pid']
		);
	}
}