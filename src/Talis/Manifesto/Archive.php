<?php

namespace Talis\Manifesto;

// phpcs:disable PSR1.Files.SideEffects
require_once 'common.inc.php';

class Archive
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $location;

    /**
     * Loads a from json.
     *
     * @param string $jsonDocument Archive as a JSON document
     */
    public function loadFromJson($jsonDocument)
    {
        $this->loadFromArray(json_decode($jsonDocument, true));
    }

    /**
     * Loads a from array.
     *
     * @param array $array Archive as an array
     */
    public function loadFromArray(array $array)
    {
        if (isset($array['id'])) {
            $this->id = $array['id'];
        }

        if (isset($array['status'])) {
            $this->status = $array['status'];
        }

        if (isset($array['location'])) {
            $this->location = $array['location'];
        }
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}
