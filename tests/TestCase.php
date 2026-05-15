<?php
/**
 * Classic Editor Plugin Test Case
 *
 * @package Classic_Editor\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base test case with WordPress mocks.
 */
abstract class Classic_Editor_TestCase extends TestCase
{
	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void
	{
		parent::tearDown();
	}
}