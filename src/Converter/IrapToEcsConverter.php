<?php
namespace App\Converter;

use App\Service\CSVHandler;
use App\Service\SPointsOrObstacleRecordSet;
use App\Service\IRAPRecordSet;
use App\Service\SPointsOrObstacleRecord;
use App\Service\IRAPRecord;
use App\GeoUtils\GeoUtils;

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
    protected $irapSet;
    
    /**
     * @var CSVHandler
     */
    protected $spoSet;
    
    /**
     * @var CSVHandler
     */
    protected $surveySet;
    
    /**
     * @var CSVHandler
     */
    protected $minorSet;
    
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

        $this->irapSet = new IRAPRecordSet($irapheader);
        
        if (!$this->csvhandler->loadFile($this->irapSet)) {
            return false;
        }
        
        // Generating Columns of the survey_points_crossing_or_obstacle
        // TODO: configból fefl kell tölteni
        $spoheader = array();
        $this->spoSet = new SPointsOrObstacleRecordSet($spoheader);
        /** @var \App\Service\IRAPRecord $irap */
        $lastRow = array();
        foreach ($this->irapSet as $irap) {
            // Ranking
            $this->calculateRankForRows($irap, $lastRow);
            
            if ($irap->getFieldvalue('pedestrian_crossing_inspected_road') != 7 && $irap->getFieldvalue('intersection_type') != 12) {
                $spo = new SPointsOrObstacleRecord($spoheader);
                
                $spo->setFieldvalue('id', $irap->getId());
                $spo->setFieldvalue('survey_id', 0);
                $spo->setFieldvalue('road_survey_date', $irap->getFieldvalue('road_survey_date'));
                $spo->setFieldvalue('kilometre_section', $irap->getFieldvalue('distance'));
                $spo->setFieldvalue('date', $irap->getFieldvalue('road_survey_date'));
                $spo->setFieldvalue('log_position_lat', $irap->getFieldvalue('latitude'));
                $spo->setFieldvalue('log_position_lon', $irap->getFieldvalue('longitude'));
                $spo->setFieldvalue('comment', $irap->getFieldvalue('comments'));
                
                $this->spoSet[] = $spo;
            }
        }
        
        // ​4.3.1.5.​ Identify Vertices in the iRAP
        $this->identifyVertices();
        
        // ​4.3.1.9.​ Merge Zero Ranked Rows
        
        
    }
    
    protected function calculateRankForRows(IRAPRecord $irap, array &$lastRow)
    {
        if (empty($lastRow)) {
            $lastRow['speed_limit'] = $irap->getFieldvalue('speed_limit');
            $lastRow['bicycle_facility'] = $irap->getFieldvalue('bicycle_facility');
            $lastRow['number_of_lanes'] = $irap->getFieldvalue('number_of_lanes');
            $lastRow['lane_width'] = $irap->getFieldvalue('lane_width');
            $lastRow['road_condition'] = $irap->getFieldvalue('road_condition');
            $lastRow['skid_resistance_grip'] = $irap->getFieldvalue('skid_resistance_grip');
            
            $irap->setRank(count($lastRow));
        } else {
            $rank = 0;
            if ($lastRow['speed_limit'] != $irap->getFieldvalue('speed_limit')) {
                ++$rank;
            }
            if ($lastRow['bicycle_facility'] != $irap->getFieldvalue('bicycle_facility')) {
                ++$rank;
            }
            if ($lastRow['number_of_lanes'] != $irap->getFieldvalue('number_of_lanes')) {
                ++$rank;
            }
            if ($lastRow['lane_width'] != $irap->getFieldvalue('lane_width')) {
                ++$rank;
            }
            if ($lastRow['road_condition'] != $irap->getFieldvalue('road_condition')) {
                ++$rank;
            }
            if ($lastRow['skid_resistance_grip'] != $irap->getFieldvalue('skid_resistance_grip')) {
                ++$rank;
            }

            $irap->setRank($rank);
        }
    }
    
    protected function identifyVertices()
    {
        $geo = new GeoUtils();
        $div = $this->maxDivergence / 2;
        $count = count($this->irapSet);
        // First and last is a vertex
        $this->irapSet[0]->setVertex(true);
        $this->irapSet[$count-1]->setVertex(true);
        
        $n = 0;
        while ($n < $count-1) {
            $m = $n+1;
            while ($m < $count-1) {
                $dist = $geo->crossArc(
                    $this->irapSet[$n]->$irap->getFieldvalue('latitude'), $this->irapSet[$n]->$irap->getFieldvalue('longitude'),
                    $this->irapSet[$m+1]->$irap->getFieldvalue('latitude'), $this->irapSet[$m+1]->$irap->getFieldvalue('longitude'),
                    $this->irapSet[$m]->$irap->getFieldvalue('latitude'), $this->irapSet[$m]->$irap->getFieldvalue('longitude')
                );
                if ($dist > $div) {
                    $this->irapSet[$m]->setVertex(true);
                    break;
                }
            }
            $n = $m;
        }
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

