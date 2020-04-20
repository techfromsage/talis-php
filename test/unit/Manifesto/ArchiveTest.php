<?php

namespace test\unit\Manifesto;

use test\TestBase;

class ArchiveTest extends TestBase
{
    public function testLoadFromArray()
    {
        $response = ['status' => 'Completed', 'location' => 'http://example.com/1234', 'id' => '1234'];

        $archive = new \Talis\Manifesto\Archive();
        $archive->loadFromArray($response);

        $this->assertEquals('1234', $archive->getId());
        $this->assertEquals('Completed', $archive->getStatus());
        $this->assertEquals('http://example.com/1234', $archive->getLocation());
    }

    public function testLoadFromJSON()
    {
        $response = '{"status":"Completed", "location":"http:\/\/example.com\/1234", "id":"1234"}';

        $archive = new \Talis\Manifesto\Archive();
        $archive->loadFromJson($response);

        $this->assertEquals('1234', $archive->getId());
        $this->assertEquals('Completed', $archive->getStatus());
        $this->assertEquals('http://example.com/1234', $archive->getLocation());
    }
}
