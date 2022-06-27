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
     * @var IRAPRecordSet
     */
    protected $irapSet;
    
    /**
     * @var SPointsOrObstacleRecordSet
     */
    protected $spoSet;
    
    /**
     * @var SurveyRecordSet
     */
    protected $surveySet;
    
    /**
     * @var MinorSectionRecordSet
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
        $this->irapSet->setCsvType(CSVHandler::CSVT_IRAP);
        
        if (!$this->csvhandler->loadCSVDataToRecordset($this->irapSet)) {
            return false;
        }
        
        // Generating Columns of the survey_points_crossing_or_obstacle
        $spoheader = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_POINTS);
        $this->spoSet = new SPointsOrObstacleRecordSet($spoheader);
        $this->spoSet->setCsvType(CSVHandler::CSVT_ECS_POINTS);
        /** @var \App\Service\IRAPRecord $irap */
        $lastRow = array();
        $serial = 1;
        foreach ($this->irapSet as $irap) {
            // Ranking
            $this->calculateRankForRows($irap, $lastRow);
            
            if ($irap->getFieldvalue('pedestrian_crossing_inspected_road') != 7 && $irap->getFieldvalue('intersection_type') != 12) {
                $spo = new SPointsOrObstacleRecord($spoheader);
                
                $spo->setFieldvalue('id', $serial);
                $spo->setFieldvalue('survey_id', 0);
                $spo->setFieldvalue('minor_section_id', $irap->getId());
                $spo->setFieldvalue('kilometre_section', $irap->getFieldvalue('distance'));
                $spo->setFieldvalue('date', $irap->getFieldvalue('road_survey_date'));
                $spo->setFieldvalue('log_position_lat', $irap->getFieldvalue('latitude'));
                $spo->setFieldvalue('log_position_lon', $irap->getFieldvalue('longitude'));
                $spo->setFieldvalue('comment', $irap->getFieldvalue('comments'));
                
                $this->spoSet[] = $spo;
                ++$serial;
            }
        }
        
        // ​4.3.1.5.​ Identify Vertices in the iRAP
        $this->identifyVertices();
        
        // ​4.3.1.9.​ Merge Zero Ranked Rows
        $this->mergeZeroRankedRows();
        
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
                    $this->irapSet[$n]->getFieldvalue('latitude'), $this->irapSet[$n]->$irap->getFieldvalue('longitude'),
                    $this->irapSet[$m+1]->getFieldvalue('latitude'), $this->irapSet[$m+1]->$irap->getFieldvalue('longitude'),
                    $this->irapSet[$m]->getFieldvalue('latitude'), $this->irapSet[$m]->$irap->getFieldvalue('longitude')
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
        $count = count($this->irapSet);
        $n = 0;
        while ($n < $count-1) {
            /** @var IRAPRecord $cur */
            $cur = $this->irapSet[$n];
            $cur->addLatlongPoint($cur->getFieldvalue('longitude'), $cur->getFieldvalue('latitude'), $this->averageHeight);
            /** @var IRAPRecord $next */
            $m = $n+1;
            $next = $this->irapSet[$m];
            // Calculate lenghts in meter unit
            while ($m < $count-1 && $next->getRank() == 0 && (($cur->getFieldvalue('length')+$next->getFieldvalue('length')) * 1000) < ($this->maxLength-$this->minLength)) {
                $cur->setFieldvalue('length', $cur->getFieldvalue('length')+$next->getFieldvalue('length'));
                if ($next->isVertex()) {
                    $cur->addLatlongPoint($next->getFieldvalue('longitude'), $next->getFieldvalue('latitude'), $this->averageHeight);
                }
                $next->setDeleted(true);
                ++$m;
                $next = $this->irapSet[$m];
            }
            $cur->addLatlongPoint($next->getFieldvalue('longitude'), $next->getFieldvalue('latitude'), $this->averageHeight);
            $n = $m;
        }
        $cur = $this->irapSet[$count-1]; $cur->setDeleted(true);
        // Delete marked rows
        $this->deleteMarkedRows();
    }
    
    protected function deleteMarkedRows()
    {
        $irapSet = new IRAPRecordSet($irapheader);
        $irapSet->setCsvType(CSVHandler::CSVT_IRAP);
        $irapSet->setHeaders($this->irapSet->getHeaders());
        /** @var IRAPRecord $irap */
        foreach ($this->irapSet as &$irap) {
            if ($irap->isDeleted()) {
                unset($irap);
            }
            else {
                $irapSet[] = $irap;
            }
        }
        $this->irapSet = $irapSet;
    }
    
    protected function mergeShortLengthRows() {
        $count = count($this->irapSet);
        $n = 0;
        while ($n < $count-1) {
            /** @var IRAPRecord $cur */
            $cur = $this->irapSet[$n];
            $m = $n+1;
            while ($m < $count && $cur->getFieldvalue('length') < $this->minLength) {
                /** @var IRAPRecord $next */
                $next = $this->irapSet[$m];
                $bMethod = 0;
                if ($next->getRank() < $cur->getRank()) {
                    $bMethod = 0;
                }
                elseif ($cur->getRank() < $next->getRank()) {
                    $bMethod = 1;
                }
                else {
                    if ($next->getFieldvalue('length') < $cur->getFieldvalue('length')) {
                        $bMethod = 0;
                    }
                    else {
                        $bMethod = 1;
                    }
                }
                if ($bMethod == 0) {
                    $cur->mergeWKTPoints($next->getLatlong());
                    $cur->setFieldvalue('length', $next->getFieldvalue('length') + $cur->getFieldvalue('length'));
                    $next->setDeleted(true);
                    
                } else {
                    $next->mergeWKTPoints($cur->getLatlong(), false);
                    $next->setFieldvalue('length', $next->getFieldvalue('length') + $cur->getFieldvalue('length'));
                    $cur->setDeleted(true);
                    $cur = $next;
                }
                $n = $m;
                ++$m;
            }
        }
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
    
    /**
     * @return string
     */
    public function getAverageHeight()
    {
        return $this->averageHeight;
    }

    /**
     * @return number
     */
    public function getMaxDivergence()
    {
        return $this->maxDivergence;
    }

    /**
     * @return number
     */
    public function getMinLength()
    {
        return $this->minLength;
    }

    /**
     * @return number
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * @param string $averageHeight
     */
    public function setAverageHeight($averageHeight)
    {
        $this->averageHeight = $averageHeight;
    }

    /**
     * @param number $maxDivergence
     */
    public function setMaxDivergence($maxDivergence)
    {
        $this->maxDivergence = $maxDivergence;
    }

    /**
     * @param number $minLength
     */
    public function setMinLength($minLength)
    {
        $this->minLength = $minLength;
    }

    /**
     * @param number $maxLength
     */
    public function setMaxLength($maxLength)
    {
        $this->maxLength = $maxLength;
    }
    
    /**
     * @return \App\Service\SPointsOrObstacleRecordSet
     */
    public function getSpoSet()
    {
        return $this->spoSet;
    }

    /**
     * @return \App\Service\SurveyRecordSet
     */
    public function getSurveySet()
    {
        return $this->surveySet;
    }

    /**
     * @return \App\Service\MinorSectionRecordSet
     */
    public function getMinorSet()
    {
        return $this->minorSet;
    }

    
}

