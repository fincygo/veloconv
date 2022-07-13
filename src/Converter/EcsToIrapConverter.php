<?php
namespace App\Converter;

use App\Service\CSVHandler;
use App\Service\SPointsOrObstacleRecordSet;
use App\Service\SPointsOrObstacleRecord;
use App\Service\IRAPRecord;
use App\GeoUtils\GeoUtils;
use App\Service\SurveyRecordSet;
use App\Service\MinorSectionRecordSet;
use App\Service\IRAPRecordSet;

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
     * @var array()
     */
    protected $minorSet;


    /**
     * @var array()
     */
    protected $IRAPSet;

    /**
     * @var array()
     */
    protected $TempSet;

    /**
     * @var int
     */
    protected $IRAPSerial;

    /**
     * @var int
     */
    protected $TempSerial;

    /**
     * @var int
     */
    protected $calculatedDistance;

    /**
     * @var GeoUtils
     */
    protected $GeoUtils;

    /**
     */
    public function __construct(CSVHandler $csvhandler)
    {       
        $this->csvhandler    = $csvhandler;
        $this->segmentLength = EcsToIrapConverter::DEFA_SEGMENTLENGTH;
        $this->IRAPSerial    = 0;
        $this->GeoUtils      = new GeoUtils();                            
    }


    
    public function processECSFile() : bool
    {
        $minorHeader    =  $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_MINORSECTION);        
        $this->minorSet = array(); 
        
        if (!$this->csvhandler->loadCSVDataToRecordset($this->minorSet)) {
            return false;
        }

        $irapHeader               =  $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_IRAP);
        $irapFieldList            = array_keys($irapHeader);
        $this->TempSet            = array();
        $this->IRAPSet            = array();
        $this->TempSerial         = 0;
        $this->IRAPSerial         = 0;
        $this->calculatedDistance = 0;

        foreach ($this->minorSet as $index => $minorRecord ) 
        {
           $aPoints = $this->getPointsFromLineString( $minorRecord->getFieldvalue('geometry') );
           if ( count($aPoints) > 0 )
           {
                if ( $index < count($this->minorSet)-1 ) // remove the last point if not the last record
                    array_pop( $aPoints );               // because the next record first point is equal the previouse record last point.
                foreach ( $aPoints as $pi => $point )
                    $this->addTempRecordByPoint( $pi, $point, $minorRecord, $irapHeader );
                    
           }
        }

        $prevRecord = false;    
        $length     = 0;
        foreach ( $this->TempSet as $TempRecord )
        {
            if ( false !== $prevRecord )
            {
                $prevRecord->setFieldValue( 'length', ($length = $this->calculateLength( $prevRecord, $TempRecord )) / 1000. );
                if ( $length > $this->segmentLength )
                {
                    // $splitLength = floor( $length / ceil( $length / $this->segmentLength )) / 2;
                    // echo "Need to interpolate {$length}  {$this->segmentLength}\n";
                    $newPoints = $this->GeoUtils->splitArc( floatval( $prevRecord->getFieldValue("latitude")), floatval( $prevRecord->getFieldValue("longitude")),
                                                            floatval( $TempRecord->getFieldValue("latitude")), floatval( $TempRecord->getFieldValue("longitude")),
                                                            $this->segmentLength );

                    foreach ($newPoints as $newPoint )
                    {
                        // echo "   > add newpoint ". $newPoint["lat"] . " " . $newPoint["lon"] . "\n";
                        ++$this->IRAPSerial;
                        $IRAPRecord = new IRAPRecord( $irapHeader );  
                        $IRAPRecord->setId( $this->IRAPSerial );

                        foreach ($irapFieldList as $field )
                            $IRAPRecord->setFieldvalue( $field, $prevRecord->getFieldvalue($field) );                                
                               
                        $IRAPRecord->setFieldValue("latitude",  $newPoint["lat"] );
                        $IRAPRecord->setFieldValue("longitude", $newPoint["lon"] );
                        $IRAPRecord->setFieldValue("length",   0 );
                        $IRAPRecord->setFieldValue("distance", 0 );
                        $IRAPRecord->setFieldvalue("comments", "Interpolated from an ECS microsections file" );
                        $this->IRAPSet[] = $IRAPRecord;
                    }
                    // PL.: a kezdő pontok lemaradnak
                    ++$this->IRAPSerial;
                    $TempRecord->setId( $this->IRAPSerial );
                    $this->IRAPSet[] = $TempRecord;
                }
                else
                {
                    ++$this->IRAPSerial;
                    $TempRecord->setId( $this->IRAPSerial );
                    $this->IRAPSet[] = $TempRecord;
                }
            }
            $prevRecord = $TempRecord;
        }
        //
        // recalculate the IRAP lengths / distances
        //
        $prevRecord               = false; 
        $this->calculatedDistance = 0;
        $length                   = 0;
        foreach ( $this->IRAPSet as &$IRAPRecord )                    
        {
            if ( false !== $prevRecord )
            {
                $prevRecord->setFieldValue( 'length', ($length = $this->calculateLength( $prevRecord, $IRAPRecord )) / 1000. );
                $this->calculatedDistance += ( $length );            
            }                     
            $IRAPRecord->setFieldValue( 'distance', $this->calculatedDistance / 1000. );        
            $prevRecord = $IRAPRecord;            
        }
        //
        //
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

    protected function addTempRecordByPoint( $index, $point, $minor, $irapHeader )
    {
        ++$this->TempSerial;
        $TempRecord = new IRAPRecord( $irapHeader );        
        $TempRecord->setId( $this->TempSerial );
        $TempRecord->setFieldvalue("road_survey_date", $this->setIRAPDate( $minor->getFieldValue("date")));
        $TempRecord->setFieldvalue("distance",         $this->calculatedDistance );
        $this->calculatedDistance += floatval( $minor->getFieldValue("length") );
        $TempRecord->setFieldvalue("latitude",  $point["Y"] );
        $TempRecord->setFieldvalue("longitude", $point["X"] );
        $TempRecord->setFieldvalue("comments", "Converted from an ECS microsections file" );
        $TempRecord->setFieldvalue("speed_limit", $this->getSpeedLimit( intval( $minor->getFieldValue("i2_traffic_speed")) ) );
        $TempRecord->setFieldvalue("bicycle_facility", $this->setBicycleFacility( $minor->getFieldValue("i2_type") ) );
        $TempRecord->setFieldvalue("skid_resistance_grip", $this->setSkidResistanceGrip( $minor->getFieldValue("i2_type"), $minor->getFieldValue("i3_surface_type") ) );
        $TempRecord->setFieldvalue("vehicle_flow_aadt", $minor->getFieldValue("i2_traffic_volume") );

        $this->TempSet[] = $TempRecord;
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
        if ( $value >=  30 )  return  3;
        if ( $value <   30 )  return  1;        
    }

    protected function setBicycleFacility( string $faciliy ):string
    {
        if ( strpos( strtolower($faciliy), "painted") >= 0 )  return "2";
        if ( strpos( strtolower($faciliy), "pedestrian") >= 0 )  return "7";
        return "";
    }

    protected function setSkidResistanceGrip( string $facility, string $grip ):string
    {
        if ( (strpos( strtolower($facility), "public") >= 0) ||
             (strpos( strtolower($facility), "painted") >= 0) ||
             (strpos( strtolower($facility), "cyclepath") >= 0) ||
             (strpos( strtolower($facility), "home") >= 0) ||
             (strpos( strtolower($facility), "agricultural") >= 0) ||
             (strpos( strtolower($facility), "forestry road") >= 0) ||
             (strpos( strtolower($facility), "water management road") >= 0) ) {

            if ( (strpos( strtolower($grip), "graveldirt") >= 0)  )  return "5";
            if ( (strpos( strtolower($grip), "stabilised") >= 0)  )  return "4";
        }
        return "";
    }

    protected function calculateLength( $prevRecord, $IRAPrecord ):float
    {        
        //$distance = $this->GeoUtils->dist( deg2rad( floatval($prevRecord->getFieldValue("latitude" )) ),  deg2rad( floatval($prevRecord->getFieldValue("longitude")) ),
        //                                   deg2rad( floatval($IRAPrecord->getFieldValue("latitude" )) ),  deg2rad( floatval($IRAPrecord->getFieldValue("longitude")) ));
        $distance = $this->GeoUtils->distH( floatval($prevRecord->getFieldValue("latitude" )),  floatval($prevRecord->getFieldValue("longitude")),
                                            floatval($IRAPrecord->getFieldValue("latitude" )),  floatval($IRAPrecord->getFieldValue("longitude")) );
        
        return $distance;                                       
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
