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
use App\Service\SurveyRecord;
use App\Service\MinorSectionRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class IrapToEcsConverter
{
    
    /**
     * survey id parameter
     *
     * @var integer
     */
    protected $surveyId;
    
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
     * @var \DateTime
     */
    private $firstDate;
    
    /**
     * @var \DateTime
     */
    private $lastDate;
    
    /**
     */
    public function __construct(CSVHandler $csvhandler, $avgHeight = 0, $maxDiv = 1.0, $minLen = 200, $maxLen = 5000, $surveyId = 1)
    {
        $this->averageHeight = 0;
        $this->maxDivergence = 1.0;
        $this->minLength = 200.0;
        $this->maxLength = 5000.0;
        $this->surveyId = 1;
        
        $this->firstDate = null;
        $this->lastDate = null;
        
        if (null !== $avgHeight) {
            $this->averageHeight = $avgHeight;
        }
        if (null !== $maxDiv) {
            $this->maxDivergence = $maxDiv;
        }
        if (null !== $minLen) {
            $this->minLength = $minLen;
        }
        if (null !== $maxLen) {
            $this->maxLength = $maxLen;
        }
        if (null !== $surveyId) {
            $this->maxLength = $maxLen;
        }
        
        $this->csvhandler = $csvhandler;
    }
    
    public function processIrapFile() : bool
    {
        $this->irapSet = new IRAPRecordSet();
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
            // find first date
            $date = \DateTime::createFromFormat('j.n.Y', $irap->getFieldvalue('road_survey_date'));
            if (null == $this->firstDate) {
                $this->firstDate = $date;
                $this->lastDate = $date;
            }
            else {
                if ($date < $this->firstDate) {
                    $this->firstDate = $date;
                }
                if ($date > $this->lastDate) {
                    $this->lastDate = $date;
                }
            }
            
            if ($irap->getFieldvalue('pedestrian_crossing_inspected_road') != 7 && $irap->getFieldvalue('intersection_type') != 12) {
                $spo = new SPointsOrObstacleRecord($spoheader);
                
                $spo->setFieldvalue('id', $serial);
                $spo->setFieldvalue('survey_id', $this->surveyId);
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
        
        // ​4.3.1.10.​ Merge Short Length Rows
        // and
        // 4.3.2.4.​ Finalising Data of the survey_points_crossing_or_obstacle
        $this->mergeShortLengthRows();
        
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
                    $this->irapSet[$n]->getFieldvalue('latitude'), $this->irapSet[$n]->getFieldvalue('longitude'),
                    $this->irapSet[$m+1]->getFieldvalue('latitude'), $this->irapSet[$m+1]->getFieldvalue('longitude'),
                    $this->irapSet[$m]->getFieldvalue('latitude'), $this->irapSet[$m]->getFieldvalue('longitude')
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
        $irapSet = new IRAPRecordSet();
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
        // cross point
        $spoCount = count($this->spoSet);
        $serial = 1;
        $nSpo = 0;
        // irap
        $count = count($this->irapSet);
        $n = 0;
        $distance = 0;
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
            $n = $m;
            /** @var SPointsOrObstacleRecord $spo */
            // lastt merged irap id and before irap ids  belong to the current session
            $cur->setNewId($serial);
            // update distance
            $distance += $cur->getFieldvalue('length');
            $cur->setFieldvalue('distance', $distance);
            $spo = $spo = $this->spoSet[$nSpo];
            if ($n < $count-1) {
                $next = $this->irapSet[$n];
                while ($nSpo < $spoCount && $spo->getFieldvalue('minor_section_id') < $next->getId()) {
                    $spo->setFieldvalue('minor_section_id', $serial);
                    ++$nSpo;
                }
            }
            ++$serial;
        }
        if ($n == $count-1) {
            $cur = $this->irapSet[$n];
            $distance += $cur->getFieldvalue('length');
            $cur->setFieldvalue('distance', $distance);
        }
        // last section if it exists
        while ($nSpo < $spoCount) {
            $spo->setFieldvalue('minor_section_id', $serial);
            ++$nSpo;
        }
        // Delete marked rows
        $this->deleteMarkedRows();
    }

    protected function generatingValuesOfSurveys() {
        $header = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_SURVEYS);
        $this->surveySet = new SurveyRecordSet($header);
        $this->surveySet->setCsvType(CSVHandler::CSVT_ECS_SURVEYS);
        /** @var IRAPRecord $irap */
        $irap = $this->irapSet[count($this->irapSet)-1];
        
        $record = new SurveyRecord($header);
        
        $record->setFieldvalue('id', $this->surveyId);
        $record->setFieldvalue('start_date', $this->firstDate->format(DATE_ATOM));
        $record->setFieldvalue('end_date', $this->lastDate->format(DATE_ATOM));
        $record->setFieldvalue('by', $irap->getFieldvalue('coder_name'));
        $record->setFieldvalue('device', 'unknow');
        $record->setFieldvalue('app_version', '0.0');
        $record->setFieldvalue('length', $irap->getFieldvalue('distance'));
        $record->setFieldvalue('minor_section_count', count($this->irapSet));
        $record->setFieldvalue('point_count', count($this->spoSet));
        $record->setFieldvalue('daily_section_id', '1');
    }
    
    protected function generatingValuesOfMinorSection() {
        $header = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_MINORSECTION);
        $this->minorSet = new MinorSectionRecordSet($header);
        $this->minorSet->setCsvType(CSVHandler::CSVT_ECS_MINORSECTION);
        /** @var IRAPRecord $irap */
        $irap = $this->irapSet[count($this->irapSet)-1];
        
        $rec = new MinorSectionRecord($header);
        /** @var IRAPRecord $irap */
        foreach ($this->irapSet as $irap) {
            $rec->setFieldvalue('id', $irap->getId());
            $rec->setFieldvalue('survey_id', $this->surveyId);
            $rec->setFieldvalue('index', $irap->getId());
            $date = \DateTime::createFromFormat('j.n.Y', $irap->getFieldvalue('road_survey_date'));
            $rec->setFieldvalue('date', $date->format(DATE_ATOM));
            $rec->setFieldvalue('length', $irap->getFieldvalue('length'));
            $rec->setFieldvalue('i1_legal', $value);
        }
    }
    
    protected function getL1legalValue(IRAPRecord $irap): string
    {
        
    }
    
    protected function makeWKTLinestring(array $points): string
    {
        $coord = [];
        foreach ($points as $p); {
            $coord[] = implode(' ', $p); 
        }
        return 'LINESTRING Z (' . implode(', ', $coord) . ')"';
        
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

