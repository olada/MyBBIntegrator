<?php

class PollTest extends MyBBIntegratorTestCase {

	const POLL_FORUM_ID = 4;
	const POLL_FORUM_NAME = "Poll Forum";
	const POLL_EXISTING_AT_START_ID = 1;
	const UPDATE_THREAD_CACHE = true;

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

	/**
	 * Make sure that the poll exists at the beginning
	*/
	public function testPollExistsAtStartExists() {
		$poll = $this->mybb_integrator->getPoll(self::POLL_EXISTING_AT_START_ID);

		$this->assertTrue(
			is_array($poll),
			"poll return value should be an array"
		);
	}

	/**
	 * @depends testPollExistsAtStartExists
	*/
	public function testRemovePollFromThread() {
		/*$this->mybb_integrator->deletePoll(self::POLL_EXISTING_AT_START_ID);
		$poll = $this->mybb_integrator->getPoll(self::POLL_EXISTING_AT_START_ID, self::UPDATE_THREAD_CACHE);
		$this->assertFalse(
			$poll,
			"poll return value should be false after deleting poll"
		);*/
	}
}