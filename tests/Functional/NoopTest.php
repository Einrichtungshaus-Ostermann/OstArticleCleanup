<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class NoopTest extends TestCase
{
    /**
     * Needs to exist when no real Tests are existent
     */
    public function testNoop()
    {
        $this->assertTrue(true);
    }
}
