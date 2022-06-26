<?php
namespace App\Service;

use App\Model\AbstractRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class IRAPRecord extends AbstractRecord
{
    /**
     * @var integer
     */
    private $id;
    
    /**
     * @var integer
     */
    private $newId;
    
    /**
     * @var integer
     */
    private $rank;
    
    /**
     * @var boolean
     */
    private $vertex;
    
    /**
     * @var string
     */
    private $geometry;
    
    /**
     * @var array
     */
    private $record;
    
    /**
     * @var array
     */
    private $latlong;
    
    /**
     */
    public function __construct(array $fields)
    {
        parent::__construct($fields);
        $this->newId = 0;
        $this->vertex = false;
        $this->geometry = "";
        $this->record = array();
        $this->latlong = array();
    }
    
    /**
     * @return number
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return number
     */
    public function getNewId()
    {
        return $this->newId;
    }

    /**
     * @return boolean
     */
    public function isVertex()
    {
        return $this->vertex;
    }

    /**
     * @return string
     */
    public function getGeometry()
    {
        return $this->geometry;
    }

    /**
     * @param number $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param number $newId
     */
    public function setNewId($newId)
    {
        $this->newId = $newId;
    }

    /**
     * @param boolean $vertex
     */
    public function setVertex($vertex)
    {
        $this->vertex = $vertex;
    }

    /**
     * @param string $geometry
     */
    public function setGeometry($geometry)
    {
        $this->geometry = $geometry;
    }

    /**
     * @return array
     */
    public function getLatlong()
    {
        return $this->latlong;
    }

    /**
     * @param array $latlong
     */
    public function addLatlongPoint($longitude, $latitude, $z)
    {
        $point = [$longitude, $latitude, $z];
        $this->latlong[] = $point;
    }
    
    /**
     * 
     * @param array $wkt
     * @param boolean $before
     */
    public function mergeWKTPoints(array $wkt, $after=true)
    {
        if ($after) {
            foreach ($wkt as $value) {
                $this->latlong[] = $value;
            };
        } else {
            $mentes = $this->latlong;
            $this->latlong = $wkt;
            foreach ($mentes as $value) {
                $this->latlong[] = $value;
                ;
            }
        }
    }
    
    /**
     * @return number
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @param number $rank
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
    }


}

