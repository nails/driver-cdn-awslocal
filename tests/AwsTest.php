<?php

namespace Nails\Cdn\Driver;

use Nails\Factory;

class AwsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Whether the setup has been run once already
     * @var bool
     */
    private $bIsInitiated;

    /**
     * The CDN Service
     * @var \Nails\Cdn\Service\Cdn;
     */
    private $oCdn;

    // --------------------------------------------------------------------------

    public function setup()
    {
        if (empty($this->bIsInitiated)) {
            Factory::setup();
            $this->oCdn = Factory::services('Cdn', 'nails/module-cdn');
        }
    }

    // --------------------------------------------------------------------------

    public function testCanCreateBucket()
    {
        //  @todo
        $this->assertCount(0, []);
    }

    // --------------------------------------------------------------------------

    public function testCanDeleteBucket()
    {
        //  @todo
        $this->assertCount(0, []);
    }

    // --------------------------------------------------------------------------

    public function testCanCreateObject()
    {
        //  @todo
        $this->assertCount(0, []);
    }

    // --------------------------------------------------------------------------

    public function testCanDetectObject()
    {
        //  @todo
        $this->assertCount(0, []);
    }

    // --------------------------------------------------------------------------

    public function testDestroyObject()
    {
        //  @todo
        $this->assertCount(0, []);
    }

    // --------------------------------------------------------------------------

    public function testGetObjectLocalPath()
    {
        //  @todo
        $this->assertCount(0, []);
    }
}
