<?php
namespace App\Converter;

use App\Service\CSVHandler;
use App\Service\SPointsOrObstacleRecordSet;
use App\Service\IRAPRecordSet;
use App\Service\SPointsOrObstacleRecord;
use App\Service\IRAPRecord;
use App\GeoUtils\GeoUtils;
use App\Service\SurveyRecordSet;
use App\Service\MinorSectionRecordSet;

/**
 *
 * @author petrics.lajos
 *        
 */
class EcsToIrapConverter
{
    const DEFA_SEGMENTLENGTH = 100.0;       // meter
    const NAME_SEGMENTLENGTH = "segmentlength";

    /**
     * @var CSVHandler
     */
    protected $csvhandler;

    /**
     * @var float
     */
    protected $segmentLength;


    /**
     * @var MinorSectionRecordSet
     */
    protected $minorSet;


    /**
     * @var IRAPRecordSet
     */
    protected $IRAPSet;

    /**
     * @var int
     */
    protected $IRAPSerial;

    /**
     * @var int
     */
    protected $calculatedDistance;


    /**
     */
    public function __construct(CSVHandler $csvhandler)
    {       
        $this->csvhandler    = $csvhandler;
        $this->segmentLength = EcsToIrapConverter::DEFA_SEGMENTLENGTH;
        $this->IRAPSerial    = 0;
    }


    
    public function processECSFile() : bool
    {
        $minorHeader    =  $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_MINORSECTION);        
        $this->minorSet = new MinorSectionRecordSet( $minorHeader );
        $this->minorSet->setCsvType(CSVHandler::CSVT_ECS_MINORSECTION);
        
        if (!$this->csvhandler->loadCSVDataToRecordset($this->minorSet)) {
            return false;
        }

        $irapHeader       =  $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_IRAP);
        $this->IRAPSet    = new IRAPRecordSet( $irapHeader );
        $this->IRAPSet->setCsvType(CSVHandler::CSVT_IRAP);        

        $this->IRAPSerial = 0;
        $this->calculatedDistance = 0;
        foreach ($this->minorSet as $minor) 
        {
           $aPoints = $this->getPointsFromLineString( $minor->getFieldvalue('geometry') );
           if ( count($aPoints) > 0 )
           {
                foreach ( $aPoints as $point )
                    $this->addIRAPRecordByPoint( $point, $minor, $irapHeader );

                //
                // calculate he length
                // ......
                // split the section
                // ......
                //
           }
        }
        return true;
    }

    /**
     * @param string
     */
    protected function getPointsFromLineString( string $linestring ):array
    {
        if ( empty($linestring) )
            return array();

        $aAllPoints = explode(",",trim(str_replace( array("LINESTRING Z","(",")"),"", $linestring )));
        $aPoints    = array();
        foreach ($aAllPoints as $point )
        {
            $aValues = explode(" ", trim($point) );
              
            $aCoords = array();
            if ( count($aValues) > 1 )
            {
                $aCoords["X"] = floatval( $aValues[0] );
                $aCoords["Y"] = floatval( $aValues[1] );
                $aCoords["Z"] = ( count($aValues) > 2 ? floatval( $aValues[2] ) : 0. );
                $aPoints[]    = $aCoords;                              
            }                
        }
        return $aPoints;
    }

    protected function addIRAPRecordByPoint( $point, $minor, $irapHeader )
    {
        ++$this->IRAPSerial;
        $IRAPRecord = new IRAPRecord( $irapHeader );        
        $IRAPRecord->setId( $this->IRAPSerial );
        $IRAPRecord->setFieldvalue("road_survey_date", $this->setIRAPDate( $minor->getFieldValue("date")));
        $IRAPRecord->setFieldvalue("distance",         $this->calculatedDistance );
        $this->calculatedDistance += floatval( $minor->getFieldValue("length") );
    // $IRAPRecord->setFieldvalue("lengh",  $point["X"] ); need to calculate
        $IRAPRecord->setFieldvalue("latitude",  $point["X"] );
        $IRAPRecord->setFieldvalue("longitude", $point["Y"] );
        $IRAPRecord->setFieldvalue("comments", "Converted from an ECS microsections file" );
        $IRAPRecord->setFieldvalue("speed_limit", $this->getSpeedLimit( intval( $minor->getFieldValue("i2_traffic_speed")) ) );
        $IRAPRecord->setFieldvalue("bicycle_facility", $this->setBicycleFacility( $minor->getFieldValue("i2_type") ) );
        $IRAPRecord->setFieldvalue("skid_resistance_grip", $this->setSkidResistanceGrip( $minor->getFieldValue("i2_type"), $minor->getFieldValue("i3_surface_type") ) );
        $IRAPRecord->setFieldvalue("vehicle_flow_aadt", $minor->getFieldValue("i2_traffic_volume") );

        $this->IRAPSet->offsetSet( $this->IRAPSerial-1, $IRAPRecord );
    }

    protected function setIRAPDate( $value )
    {
        $date = new \DateTime( $value );
        return $date->format('d/m/Y');
    }


    protected function getSpeedLimit( $value )
    {
        if ( $value >= 150 )  return 25;
        if ( $value >= 140 )  return 23;            
        if ( $value >= 130 )  return 21;
        if ( $value >= 120 )  return 19;
        if ( $value >= 110 )  return 17;
        if ( $value >= 100 )  return 15;
        if ( $value >=  90 )  return 13;
        if ( $value >=  80 )  return 11;
        if ( $value >=  70 )  return  9;        
        if ( $value >=  60 )  return  7;
        if ( $value >=  50 )  return  5;
        if ( $value >=  40 )  return  3;
        if ( $value >   30 )  return  3;
        if ( $value <   30 )  return  1;        
    }

    protected function setBicycleFacility( string $faciliy )
    {
        if ( strcmp( strtolower($faciliy), "painted cycle lane") == 0 )  return "2";
        if ( strcmp( strtolower($faciliy), "cycle and pedestrian path") == 0 )  return "7";
        return "";
    }

    protected function setSkidResistanceGrip( string $facility, string $grip )
    {
        if ( (strcmp( strtolower($facility), "public road") == 0) ||
             (strcmp( strtolower($facility), "painted cycle lane") == 0) ||
             (strcmp( strtolower($facility), "cycle street") == 0) ||
             (strcmp( strtolower($facility), "home zone") == 0) ||
             (strcmp( strtolower($facility), "agricultural road") == 0) ||
             (strpos( strtolower($facility), "forestry road") >= 0) ||
             (strpos( strtolower($facility), "water managemen road") >= 0) ) {

            if ( (strcmp( strtolower($grip), "gravel/dirt") == 0)  )  return "5";
            if ( (strcmp( strtolower($grip), "stabilised gravel") == 0)  )  return "4";
        }
        return "";
    }


    /**
     * @param number $minLength
     */
    public function setSegmentLength($length)
    {
        $this->segmentLength = $length;
    }

    /**
     * @return float
     */
    public function getSegmenLength()
    {
        return $this->segmenLength;
    }    


    /**
     * @return \App\Service\IRAPRecordSet
     */
    public function getIRAPSet()
    {
        return $this->IRAPSet;
    }    

}
