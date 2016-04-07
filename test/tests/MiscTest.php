<?php

class MiscTest extends MyBBIntegratorTestCase {

	public function testGeneratePostHash() {
		$posthash = $this->mybb_integrator->generatePosthash();
		$posthash2 = $this->mybb_integrator->generatePosthash();
		
		$this->assertEquals(32, strlen($posthash));
		$this->assertEquals(32, strlen($posthash2));
		$this->assertNotEquals($posthash, $posthash2);
	}
}