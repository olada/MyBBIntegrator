<?php

class MiscTest extends MyBBIntegratorTestCase {

	const USER_ID_FOR_POSTHASH_1 = 1;
	const USER_ID_FOR_POSTHASH_2 = 2;

	const MD5_LENGTH = 32;

	public function testGeneratePostHashWithoutUserID() {
		$posthash = $this->mybb_integrator->generatePosthash();
		$posthash2 = $this->mybb_integrator->generatePosthash();
		
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash));
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash2));
		$this->assertNotEquals($posthash, $posthash2);
	}

	public function testGeneratePostHashWithUserID() {
		$posthash = $this->mybb_integrator->generatePosthash(self::USER_ID_FOR_POSTHASH_1);
		$posthash2 = $this->mybb_integrator->generatePosthash(self::USER_ID_FOR_POSTHASH_2);
		
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash));
		$this->assertEquals(self::MD5_LENGTH, strlen($posthash2));
		$this->assertNotEquals($posthash, $posthash2);
	}
}