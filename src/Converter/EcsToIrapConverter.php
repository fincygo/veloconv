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
     */
    public function __construct(CSVHandler $csvhandler)
    {       
        $this->csvhandler    = $csvhandler;
        $this->segmentLength = EcsToIrapConverter::DEFA_SEGMENTLENGTH;
    }


    
    public function processECSFile() : bool
    {
        // TODO: configból fefl kell tölteni
        $minorHeader =  $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_MINORSECTION);

        $this->minorSet = new MinorSectionRecordSet( $minorHeader );
        $this->minorSet->setCsvType(CSVHandler::CSVT_ECS_MINORSECTION);
        
        if (!$this->csvhandler->loadCSVDataToRecordset($this->minorSet)) {
            return false;
        }

        // Generating Columns of the IRAP
        $IRAPheader    = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_IRAP);
        $this->IRAPSet = new IRAPRecordSet( $IRAPheader );
        $this->IRAPSet->setCsvType(CSVHandler::CSVT_IRAP);        

        return false;
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
