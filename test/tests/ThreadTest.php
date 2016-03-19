<?php

class ThreadTest extends MyBBIntegratorTestCase {

	const THREAD_TO_OPEN_AND_CLOSE_ID = 2;

	const USER_ID_WITH_PERMISSION = 1;
	const USER_ID_WITHOUT_PERMISSION = 2;

	const UPDATE_CACHE = true;

	public function testCloseAndOpenThreadWithPermission() {
		// Check if thread exists and is open
		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID);
		$this->assertTrue(
			is_array($thread),
			"thread variable should be an array when it exists"
		);
		$this->assertEquals(
			"0",
			$thread['closed'],
			"thread should not be closed at the beginning"
		);

		// Close thread and check if it works
		$close_thread_action = $this->mybb_integrator->closeThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, $thread['fid'], self::USER_ID_WITH_PERMISSION);
		$this->assertTrue(
			$close_thread_action,
			"closing thread should have succeeded"
		);

		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, self::UPDATE_CACHE);
		$this->assertEquals(
			"1",
			$thread['closed'],
			"thread should be closed after closing"
		);

		// Open thread and check if it works
		$this->mybb_integrator->openThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, $thread['fid'], self::USER_ID_WITH_PERMISSION);
		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, self::UPDATE_CACHE);
		$this->assertEquals(
			"0",
			$thread['closed'],
			"thread should be open again to reinforce same state as before"
		);
	}

	public function testCloseThreadWithoutPermission() {
		// Check if thread exists and is open
		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID);
		$this->assertTrue(
			is_array($thread),
			"thread variable should be an array when it exists"
		);
		$this->assertEquals(
			"0",
			$thread['closed'],
			"thread should not be closed at the beginning"
		);

		// Close thread and check if it works
		$close_thread_action = $this->mybb_integrator->closeThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, $thread['fid'], self::USER_ID_WITHOUT_PERMISSION);
		$this->assertFalse(
			$close_thread_action,
			"closing thread should fail due to lack of permission"
		);

		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, self::UPDATE_CACHE);
		$this->assertEquals(
			"0",
			$thread['closed'],
			"thread should not be closed after closing attempt without permission"
		);

		// Open thread and check if it works
		$this->mybb_integrator->openThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, $thread['fid'], self::USER_ID_WITHOUT_PERMISSION);
		$thread = $this->mybb_integrator->getThread(self::THREAD_TO_OPEN_AND_CLOSE_ID, self::UPDATE_CACHE);
		$this->assertEquals(
			"0",
			$thread['closed'],
			"thread should be open (as before)"
		);
	}

	private function closeAndOpenThread($user_id) {
		
	}
}