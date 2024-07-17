<?php

namespace test\unit\Manifesto;

use InvalidArgumentException;
use test\TestBase;

class ManifestTest extends TestBase
{
    public function testGetSetSafeMode()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->assertFalse($m->getSafeMode());
        $m->setSafeMode(true);
        $this->assertTrue($m->getSafeMode());

        $m2 = new \Talis\Manifesto\Manifest(true);
        $this->assertTrue($m2->getSafeMode());
    }

    public function testGetSetFileCount()
    {
        $m = new \Talis\Manifesto\Manifest();
        $m->setFileCount(12);
        $this->assertEquals(12, $m->getFileCount());
    }

    public function testGetSetFormat()
    {
        $m = new \Talis\Manifesto\Manifest();
        $m->setFormat(FORMAT_ZIP);
        $this->assertEquals(FORMAT_ZIP, $m->getFormat());
    }

    public function testValidateSetFormat()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, "'wibble' is not supported");
        $m->setFormat('wibble');
    }

    public function testGetSetCallbackLocation()
    {
        $m = new \Talis\Manifesto\Manifest();
        $m->setCallbackLocation('https://example.com/callback.cgi');
        $this->assertEquals('https://example.com/callback.cgi', $m->getCallbackLocation());
    }

    public function testValidateSetCallbackLocation()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, 'Callback location must be an http or https url');
        $m->setCallbackLocation('telnet://wibble');
    }

    public function testGetSetCallbackMethod()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->assertEquals('GET', $m->getCallbackMethod());
        $m->setCallbackMethod('POST');
        $this->assertEquals('POST', $m->getCallbackMethod());
        $m->setCallbackMethod('get');
        $this->assertEquals('GET', $m->getCallbackMethod());
    }

    public function testValidateSetCallbackMethod()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, 'Callback method must be GET or POST');
        $m->setCallbackMethod('PUT');
    }

    public function testAddGetFiles()
    {
        $m = new \Talis\Manifesto\Manifest();
        $files = [];
        $file1 = ['file' => '/path/to/file1.txt'];
        $files[] = $file1;
        $m->addFile($file1);

        $file2 = ['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => '/path/to/file2.txt', 'destinationPath' => 'foobar.txt'];
        $files[] = $file2;
        $m->addFile($file2);

        $file3 = ['type' => FILE_TYPE_CF, 'file' => '/path/to/file3.txt', 'destinationPath' => '/another/path/foobar.txt'];
        $files[] = $file3;
        $m->addFile($file3);

        $this->assertEquals($files, $m->getFiles());
    }

    public function testValidateAddFileWithNoFileKey()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, 'Files must contain a file key and value');
        $m->addFile(['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'destinationPath' => 'foobar.txt']);
    }

    public function testValidateAddFileWithNoFileValue()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, 'Files must contain a file key and value');
        $m->addFile(['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => null, 'destinationPath' => 'foobar.txt']);
    }

    public function testValidateAddFileWithUnsupportedFileType()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(InvalidArgumentException::class, "Unsupported file 'type'");
        $m->addFile(['type' => 'MY_FOO_CLOUD', 'container' => 'myBucket', 'file' => '/path/to/file.txt', 'destinationPath' => 'foobar.txt']);
    }

    public function testGenerateManifestNoFiles()
    {
        $m = new \Talis\Manifesto\Manifest();
        $this->setExpectedException(\Talis\Manifesto\Exceptions\ManifestValidationException::class, 'No files have been added to manifest');
        $m->generateManifest();
    }

    public function testGenerateManifestNoFormat()
    {
        $m = new \Talis\Manifesto\Manifest();
        $m->addFile(['file' => '/path/to/file1.txt']);
        $this->setExpectedException(\Talis\Manifesto\Exceptions\ManifestValidationException::class, 'Output format has not been set');
        $m->generateManifest();
    }

    public function testGenerateManifestNoFileCountInSafeMode()
    {
        $m = new \Talis\Manifesto\Manifest(true);
        $m->addFile(['file' => '/path/to/file1.txt']);
        $m->setFormat(FORMAT_TARBZ);
        $this->setExpectedException(\Talis\Manifesto\Exceptions\ManifestValidationException::class, 'File count must be set in safe mode');
        $m->generateManifest();
    }

    public function testGenerateManifestWrongFileCountInSafeMode()
    {
        $m = new \Talis\Manifesto\Manifest(true);
        $m->addFile(['file' => '/path/to/file1.txt']);
        $m->setFormat(FORMAT_TARBZ);
        $m->setFileCount(3);
        $this->setExpectedException(\Talis\Manifesto\Exceptions\ManifestValidationException::class, 'Number of files does not equal fileCount');
        $m->generateManifest();
    }

    public function testBasicManifest()
    {
        $m = new \Talis\Manifesto\Manifest();
        $m->setFormat(FORMAT_ZIP);
        $files = [];
        $file1 = ['file' => '/path/to/file1.txt'];
        $files[] = $file1;
        $m->addFile($file1);

        $file2 = ['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => '/path/to/file2.txt', 'destinationPath' => 'foobar.txt'];
        $files[] = $file2;
        $m->addFile($file2);

        $file3 = ['type' => FILE_TYPE_CF, 'file' => '/path/to/file3.txt', 'destinationPath' => '/another/path/foobar.txt'];
        $files[] = $file3;
        $m->addFile($file3);

        $expectedManifest = [
            'format' => FORMAT_ZIP,
            'fileCount' => 3,
            'files' => $files
        ];

        $this->assertEquals($expectedManifest, $m->generateManifest());
    }

    public function testAdvancedManifest()
    {
        $m = new \Talis\Manifesto\Manifest(true);
        $m->setFormat(FORMAT_TARBZ);
        $files = [];
        $file1 = ['file' => '/path/to/file1.txt'];
        $files[] = $file1;
        $m->addFile($file1);

        $file2 = ['type' => FILE_TYPE_S3, 'container' => 'myBucket', 'file' => '/path/to/file2.txt', 'destinationPath' => 'foobar.txt'];
        $files[] = $file2;
        $m->addFile($file2);

        $file3 = ['type' => FILE_TYPE_CF, 'file' => '/path/to/file3.txt', 'destinationPath' => '/another/path/foobar.txt'];
        $files[] = $file3;
        $m->addFile($file3);

        $m->setFileCount(3);

        $m->setCallbackLocation('https://example.com/callback.cgi');
        $m->setCallbackMethod('post');

        $expectedManifest = [
            'callback' => ['url' => 'https://example.com/callback.cgi', 'method' => 'POST'],
            'format' => FORMAT_TARBZ,
            'fileCount' => 3,
            'files' => $files
        ];

        $this->assertEquals($expectedManifest, $m->generateManifest());
    }
}
