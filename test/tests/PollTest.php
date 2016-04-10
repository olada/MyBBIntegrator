<?php

class PollTest extends MyBBIntegratorTestCase {

	const POLL_FORUM_ID = 4;
	const POLL_FORUM_NAME = "Poll Forum";
	const THREAD_ID_WITH_POLL_TO_REMOVE = 1;
	const POLL_TO_REMOVE_ID = 1;
	const UPDATE_THREAD_CACHE = true;

	const NO_POLL_ID = 0;

	const USER_NAME_WITH_PERMISSION = "admin";
	const USER_PASSWORD_WITH_PERMISSION = "admin";

	public function testPollForumExists() {
		$forum_val = $this->mybb_integrator->getForum(self::POLL_FORUM_ID);

		$this->assertTrue(
			is_array($forum_val), 
			"get poll forum needs to be array"
		);
		$this->assertEquals(
			$forum_val['name'],
			self::POLL_FORUM_NAME,
			"name of poll forum should be " + self::POLL_FORUM_NAME
		);
	}

	public function testGetPollWithZeroID() {
		$poll = $this->mybb_integrator->getPoll(self::NO_POLL_ID);

		$this->assertFalse(
			is_array($poll),
			"poll should be false because poll_id is 0"
		);
	}

	/**
	 * Make sure that the poll exists at the beginning
	*/
	public function testPollExistsAtStart() {
		$poll = $this->mybb_integrator->getPoll(self::POLL_TO_REMOVE_ID);

		$this->assertTrue(
			is_array($poll),
			"poll return value should be an array"
		);
	}

	/**
	 * @depends testPollExistsAtStart
	*/
	public function testRemovePollFromThread() {
		$this->mybb_integrator->login(self::USER_NAME_WITH_PERMISSION, self::USER_PASSWORD_WITH_PERMISSION);
		$poll_thread = $this->mybb_integrator->getThread(self::THREAD_ID_WITH_POLL_TO_REMOVE, self::UPDATE_THREAD_CACHE);
		$this->assertEquals(
			self::POLL_TO_REMOVE_ID,
			$poll_thread['poll'],
			"thread should have reference to existing poll before removing it"
		);
		$this->testPollExistsAtStart();
		$status = $this->mybb_integrator->deletePoll(self::POLL_TO_REMOVE_ID);

		$this->assertTrue(
			$status,
			"Status of deleting a poll should be true"
		);
		
		$poll = $this->mybb_integrator->getPoll(self::POLL_TO_REMOVE_ID, self::UPDATE_THREAD_CACHE);
		$this->assertFalse(
			$poll,
			"poll return value should be false after deleting poll"
		);

		$poll_thread = $this->mybb_integrator->getThread(self::THREAD_ID_WITH_POLL_TO_REMOVE, self::UPDATE_THREAD_CACHE);
		$this->assertEquals(
			self::NO_POLL_ID,
			$poll_thread['poll'],
			"thread should have reference to 0 because poll was removed"
		);

		$this->logout();
	}

	public function testAddPollToThread() {
		
	}
}