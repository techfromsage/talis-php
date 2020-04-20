<?php

namespace Talis\Manifesto;

use Talis\Manifesto\Exceptions\ManifestValidationException;

// phpcs:disable PSR1.Files.SideEffects
require_once 'common.inc.php';

class Manifest
{
    protected $callbackLocation;
    protected $callbackMethod = 'GET';
    protected $fileCount;
    protected $files = [];
    protected $format;
    protected $safeMode = false;

    protected $validFormats = [FORMAT_ZIP, FORMAT_TARGZ, FORMAT_TARBZ];

    /**
     * Constructs a new instance.
     *
     * @param boolean $safeMode The safe mode
     */
    public function __construct($safeMode = false)
    {
        $this->safeMode = $safeMode;
    }

    /**
     * @return boolean
     */
    public function getSafeMode()
    {
        return $this->safeMode;
    }

    /**
     * @param boolean $safeMode Whether to enable safe mode
     */
    public function setSafeMode($safeMode)
    {
        $this->safeMode = $safeMode;
    }

    /**
     * @return integer
     */
    public function getFileCount()
    {
        return $this->fileCount;
    }

    /**
     * @param integer $fileCount Number of files expected
     */
    public function setFileCount($fileCount)
    {
        $this->fileCount = $fileCount;
    }

    /**
     * @return array
     * @throws ManifestValidationException Manifest is not configured properly
     */
    public function generateManifest()
    {
        $this->validateManifest();

        $manifest = [];
        if (isset($this->callbackLocation)) {
            $manifest['callback'] = ['url' => $this->callbackLocation, 'method' => $this->callbackMethod];
        }

        $manifest['format'] = $this->format;

        $manifest['fileCount'] = $this->fileCount;

        $manifest['files'] = $this->files;

        return $manifest;
    }

    /**
     * Validate current configuration.
     * @throws ManifestValidationException Manifest is not configured properly
     */
    private function validateManifest()
    {
        if (empty($this->files)) {
            throw new ManifestValidationException('No files have been added to manifest');
        }

        if (!in_array($this->format, $this->validFormats)) {
            throw new ManifestValidationException('Output format has not been set');
        }

        if ($this->safeMode && (!isset($this->fileCount) || empty($this->fileCount))) {
            throw new ManifestValidationException('File count must be set in safe mode');
        } elseif (!($this->safeMode && isset($this->fileCount))) {
            $this->fileCount = count($this->files);
        }

        if ($this->fileCount != count($this->files)) {
            throw new ManifestValidationException('Number of files does not equal fileCount');
        }
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param array $file File descriptor
     * @throws \InvalidArgumentException File has an incorrect type
     */
    public function addFile(array $file)
    {
        if (!array_key_exists('file', $file) || empty($file['file'])) {
            throw new \InvalidArgumentException('Files must contain a file key and value');
        }

        if (array_key_exists('type', $file) && !in_array($file['type'], [FILE_TYPE_S3, FILE_TYPE_CF])) {
            throw new \InvalidArgumentException("Unsupported file 'type'");
        }
        $this->files[] = $file;
    }

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param string $format On of the FORMAT_ constants
     * @throws \InvalidArgumentException Invalid format given
     */
    public function setFormat($format)
    {
        if (!in_array($format, $this->validFormats)) {
            throw new \InvalidArgumentException("'{$format}' is not supported");
        }
        $this->format = $format;
    }

    /**
     * @return mixed
     */
    public function getCallbackLocation()
    {
        return $this->callbackLocation;
    }

    /**
     * @param mixed $callbackLocation Callback URL
     * @throws \InvalidArgumentException Given location is not a http(s) URL
     */
    public function setCallbackLocation($callbackLocation)
    {
        if (!(filter_var($callbackLocation, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $callbackLocation))) {
            throw new \InvalidArgumentException('Callback location must be an http or https url');
        }
        $this->callbackLocation = $callbackLocation;
    }

    /**
     * @return string
     */
    public function getCallbackMethod()
    {
        return $this->callbackMethod;
    }

    /**
     * @param string $callbackMethod Callback HTTP request method
     * @throws \InvalidArgumentException Given method is not supported
     */
    public function setCallbackMethod($callbackMethod)
    {
        if (!in_array(strtoupper($callbackMethod), ['GET', 'POST'])) {
            throw new \InvalidArgumentException('Callback method must be GET or POST');
        }
        $this->callbackMethod = strtoupper($callbackMethod);
    }
}
