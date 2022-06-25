<?php
namespace App\Converter;

use App\Service\CSVHandler;
use App\Service\SPointsOrObstacleRecordSet;
use App\Service\IRAPRecordSet;
use App\Service\SPointsOrObstacleRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class IrapToEcsConverter
{

    /**
     * For the LINESTRING z parameter
     * 
     * @var string
     */
    protected $averageHeight;
    
    /**
     * The maximum divergence from the original polygone
     *
     * @var float
     */
    protected $maxDivergence;
    
    /**
     * minimum length of ECS segments in metres, default value 200
     *
     * @var float
     */
    protected $minLength;
    
    /**
     * maximum length of ECS segments in metres,  default value 5000
     *
     * @var float
     */
    protected $maxLength;
    
    /**
     * @var array
     */
    protected $mergedRows;
    
    /**
     * @var CSVHandler
     */
    protected $csvhandler;
    
    /**
     * @var CSVHandler
     */
    protected $irapRecSet;
    
    /**
     * @var CSVHandler
     */
    protected $spoRecSet;
    
    /**
     * @var CSVHandler
     */
    protected $surveyRecSet;
    
    /**
     * @var CSVHandler
     */
    protected $minorRecSet;
    
    /**
     */
    public function __construct(CSVHandler $csvhandler, $avgHeight = 0, $maxDiv = 1.0, $minLen = 200, $maxLen = 5000)
    {
        $this->averageHeight = 0;
        $this->maxDivergence = 1.0;
        $this->minLength = 200.0;
        $this->maxLength = 5000.0;
        
        if ($avgHeight) {
            $this->averageHeight = $avgHeight;
        }
        if ($maxDiv) {
            $this->maxDivergence = $maxDiv;
        }
        if ($minLen) {
            $this->minLength = $minLen;
        }
        if ($maxLen) {
            $this->maxLength = $maxLen;
        }
        
        $this->csvhandler = $csvhandler;
    }
    
    public function processIrapFile() : bool
    {
        // TODO: configból fefl kell tölteni
        $irapheader = array();

        $this->irapRecSet = new IRAPRecordSet($irapheader);
        
        if (!$this->csvhandler->loadFile($this->irapRecSet)) {
            return false;
        }
        
        // Generating Columns of the survey_points_crossing_or_obstacle
        // TODO: configból fefl kell tölteni
        $spoheader = array();
        $this->spoRecSet = new SPointsOrObstacleRecordSet($spoheader);
        /** @var \App\Service\IRAPRecord $irap */
        foreach ($this->irapRecSet as $irap) {
            $spo = new SPointsOrObstacleRecord($spoheader);
            
            $spo->setFieldvalue('id', $irap->getId());
            $spo->setFieldvalue('survey_id', 0);
            $spo->setFieldvalue('road_survey_date', $irap->getFieldvalue('road_survey_date'));
            $spo->setFieldvalue('kilometre_section', $irap->getFieldvalue('distance'));
            $spo->setFieldvalue('date', $irap->getFieldvalue('road_survey_date'));
            $spo->setFieldvalue('log_position_lat', $irap->getFieldvalue('latitude'));
            $spo->setFieldvalue('log_position_lon', $irap->getFieldvalue('longitude'));
            $spo->setFieldvalue('comment', $irap->getFieldvalue('comments'));
        }
        
    }
    
    protected function identifyVertices()
    {
        ;
    }
    
    protected function calculateRankForRows()
    {
        ;
    }
    
    protected function mergeZeroRankedRows() {
        ;
    }
    
    protected function mergeShortLengthRows() {
        ;
    }

    protected function generatingValuesOfSurveys() {
        ;
    }
    
    protected function generatingValuesOfMinorSection() {
        ;
    }
    
    protected function finaliseSpoRecords() {
        ;
    }
    
}

