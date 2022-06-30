<?php
namespace App\Converter;

use App\GeoUtils\GeoUtils;
use App\Service\CSVHandler;
use App\Service\IRAPRecord;
use App\Service\MinorSectionRecord;
use App\Service\SPointsOrObstacleRecord;
use App\Service\SurveyRecord;
use Psr\Log\LoggerInterface;

/**
 *
 * @author petrics.lajos
 *        
 */
class IrapToEcsConverter
{
    const DEFA_AVGHEIGHT     = 0;
    const DEFA_MAXDIVERGENCE = 1.0;
    const DEFA_MINLENGTH     = 200.0;
    const DEFA_MAXLENGTH     = 5000.0;

    const NAME_AVGHEIGHT     = "avgheight";
    const NAME_MAXDIVERGENCE = "maxdivergence";
    const NAME_MINLENGTH     = "minlength";
    const NAME_MAXLENGTH     = "maxlength";
    
    const SPEED_LIMIT = [
        1 => '<30km/h',
        3 => '40km/h',
        5 => '50km/h',
        7 => '60km/h',
        9 => '70km/h',
        11 => '80km/h',
        13 => '90km/h',
        15 => '100km/h',
        17 => '110km/h',
        19 => '120km/h',
        21 => '130km/h',
        23 => '140km/h',
        25 => '>=150km/h',
        31 => '<20mph',
        33 => '30mph',
        35 => '40mph',
        37 => '50mph',
        39 => '60mph',
        41 => '70mph',
        43 => '80mph',
        45 => '>=90mph',
        ];
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
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
     * @var array
     */
    protected $irapSet;
    
    /**
     * @var array
     */
    protected $spoSet;
    
    /**
     * @var array
     */
    protected $surveySet;
    
    /**
     * @var array
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
    public function __construct(CSVHandler $csvhandler, LoggerInterface $logger)
    {
        $this->averageHeight    = IrapToEcsConverter::DEFA_AVGHEIGHT;
        $this->maxDivergence    = IrapToEcsConverter::DEFA_MAXDIVERGENCE;
        $this->minLength        = IrapToEcsConverter::DEFA_MINLENGTH;
        $this->maxLength        = IrapToEcsConverter::DEFA_MAXLENGTH;
        $this->surveyId         = 1;
        
        $this->firstDate    = null;
        $this->lastDate     = null;
        
        $this->csvhandler   = $csvhandler;
        $this->logger       = $logger;
        
        $this->irapSet      = array();
        $this->surveySet    = array();
        $this->minorSet     = array();
        $this->spoSet       = array();
    }
    
    public function processIrapFile() : bool
    {
        if (!$this->csvhandler->loadCSVDataToRecordset($this->irapSet)) {
            return false;
        }
        
        // Generating Columns of the survey_points_crossing_or_obstacle
        $spoheader = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_POINTS);
        //$this->spoSet->setCsvType(CSVHandler::CSVT_ECS_POINTS);
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
            
            if ($irap->getFieldvalue('pedestrian_crossing_inspected_road') != 7 || $irap->getFieldvalue('intersection_type') != 12) {
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
        
        $this->generatingValuesOfSurveys();
        
        $this->generatingValuesOfMinorSection();
        
        return true;
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
                $lastRow['speed_limit'] = $irap->getFieldvalue('speed_limit');
                ++$rank;
            }
            
            if ($lastRow['bicycle_facility'] != $irap->getFieldvalue('bicycle_facility')) {
                $lastRow['bicycle_facility'] = $irap->getFieldvalue('bicycle_facility');
                ++$rank;
            }
            
            if ($lastRow['number_of_lanes'] != $irap->getFieldvalue('number_of_lanes')) {
                $lastRow['number_of_lanes'] = $irap->getFieldvalue('number_of_lanes');
                ++$rank;
            }
            if ($lastRow['lane_width'] != $irap->getFieldvalue('lane_width')) {
                $lastRow['lane_width'] = $irap->getFieldvalue('lane_width');
                ++$rank;
            }
            
            if ($lastRow['road_condition'] != $irap->getFieldvalue('road_condition')) {
                $lastRow['road_condition'] = $irap->getFieldvalue('road_condition');
                ++$rank;
            }
            
            if ($lastRow['skid_resistance_grip'] != $irap->getFieldvalue('skid_resistance_grip')) {
                $lastRow['skid_resistance_grip'] = $irap->getFieldvalue('skid_resistance_grip');
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
                ++$m;
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
            while ($m < $count-1 && $next->getRank() == 0 && (($cur->getFieldvalue('length')+$next->getFieldvalue('length')) * 1000) < ($this->maxLength - $this->minLength)) {
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
        $cur->addLatlongPoint($next->getFieldvalue('longitude'), $next->getFieldvalue('latitude'), $this->averageHeight);
        $next = $this->irapSet[$count-1]; $next->setDeleted(true);
        // Delete marked rows
        $this->deleteMarkedRows();
    }
    
    protected function deleteMarkedRows()
    {
        $irapSet = array();
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
            while ($m < $count && ($cur->getFieldvalue('length') * 1000) < $this->minLength) {
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
            if ($n < $count-1 && $nSpo < $spoCount) {
                $spo = $this->spoSet[$nSpo];
                $next = $this->irapSet[$n];
                while ($nSpo < $spoCount && $spo->getFieldvalue('minor_section_id') < $next->getId()) {
                    $spo->setFieldvalue('minor_section_id', $serial);
                    ++$nSpo;
                    $spo = $this->spoSet[$nSpo];
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
            $this->spoSet[$nSpo]->setFieldvalue('minor_section_id', $serial);
            ++$nSpo;
        }
        // Delete marked rows
        $this->deleteMarkedRows();
    }

    protected function generatingValuesOfSurveys() {
        $header = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_SURVEYS);
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
        
        $this->surveySet[] = $record;
    }
    
    protected function generatingValuesOfMinorSection() {
        $header = $this->csvhandler->getConfig()->getCSVFieldArrayByType(CSVHandler::CSVT_ECS_MINORSECTION);
        
        /** @var IRAPRecord $irap */
        foreach ($this->irapSet as $irap) {
            $rec = new MinorSectionRecord($header);
            
            $rec->setFieldvalue('id', $irap->getNewId());
            $rec->setFieldvalue('survey_id', $this->surveyId);
            $rec->setFieldvalue('index', $irap->getNewId());
            $date = \DateTime::createFromFormat('j.n.Y', $irap->getFieldvalue('road_survey_date'));
            $rec->setFieldvalue('date', $date->format(DATE_ATOM));
            $rec->setFieldvalue('length', $irap->getFieldvalue('length'));
            $rec->setFieldvalue('i1_legal', $this->getI1legalValue($irap));
            $rec->setFieldvalue('i2_type', $this->getI2Type($irap));
            $rec->setFieldvalue('i2_direction', $this->getI2Direction($irap, $rec));
            $rec->setFieldvalue('i2_traffic_volume', $irap->getFieldvalue('vehicle_flow_aadt'));
            $rec->setFieldvalue('i2_traffic_speed', self::SPEED_LIMIT[$irap->getFieldvalue('speed_limit')]);
            $rec->setFieldvalue('i3_surface_type', $this->getI3SurfaceType($irap));
            $rec->setFieldvalue('i2_traffic_category', $this->getI2TrafficCategory($irap));
            $rec->setFieldvalue('comment', $this->createMinorComment($irap));
            $rec->setFieldvalue('log_position_y', $irap->getFieldvalue('latitude'));
            $rec->setFieldvalue('log_position_x', $irap->getFieldvalue('longitude'));
            $rec->setFieldvalue('geometry', $this->makeWKTLinestring($irap->getLatlong()));
            
            $this->minorSet[] = $rec;
        }
    }
    
    protected function createMinorComment(IRAPRecord $irap): string
    {
        return sprintf("%s;%s;%s", $irap->getFieldvalue('image_reference'), $irap->getFieldvalue('road_name'), $irap->getFieldvalue('section'));
    }
    
    protected function getI2TrafficCategory(IRAPRecord $irap): string
    {
        $result = '';
        
        $aadt = $irap->getFieldvalue('vehicle_flow_aadt');
        if ($aadt < 500) {
            $result = 'low';
        }
        elseif ($aadt >= 500 && $aadt < 10000) {
            $result = 'moderated';
        }
        elseif ($aadt  >= 10000) {
            $result = 'high';
        }

        return $result;
    }
    
    protected function getI3SurfaceType(IRAPRecord $irap): string
    {
        $result = 'unknow';
        
        $bicycle_facility = $irap->getFieldvalue('bicycle_facility');
        $skid = $irap->getFieldvalue('skid_resistance_grip');
        if ($bicycle_facility >= 3 && $bicycle_facility <= 6) {
            switch ($skid) {
                case 1:
                case 2:
                case 3:
                    $result = 'asphalt/concrete';
                    break;
                case 4:
                    $result = 'stabilised grave';
                    break;
                case 5:
                    $result = 'gravel/dirt';
                    break;
                default:
                    $result = 'unknow';
            }
        }

        return $result;
    }
    
    protected function getI2Direction(IRAPRecord $irap, MinorSectionRecord $rec): string
    {
        $type = $rec->getFieldvalue('i2_type');
        $median = $irap->getFieldvalue('median_type');
        
        $result ='two-way';
        if ($type == 'Public road' && $median == 13) {
            $result ='one-way';;
        }
        
        return $result;
    }
    
    protected function getI1legalValue(IRAPRecord $irap): string
    {
        $code = $irap->getFieldvalue('carriageway_label');
        $lanes = $irap->getFieldvalue('number_of_lanes');
        $bphf = $irap->getFieldvalue('bicyclist_peak_hourly_flow');
        $speed = $irap->getFieldvalue('speed_limit');
        
        if ($code != 3 && $lanes >= 2 && (is_null($bphf) || 0 == $bphf) && $speed >= 17) {
            $result = 'Entry forbidden';
        }
        else {
            $result = 'cyclingAllowed';
        }
        
        return $result;
    }
    
    protected function getI2Type(IRAPRecord $irap) : string
    {
        $result = 'unknow';
        $bicycle_facility = $irap->getFieldvalue('bicycle_facility');
        $vehicle_flow_aadt = $irap->getFieldvalue('vehicle_flow_aadt');
        $pedestrian_observed = $irap->getFieldvalue('pedestrian_observed_flow_along_the_road_passenger_side ');
        
        if ($bicycle_facility >= 4 && $bicycle_facility <= 6 && $vehicle_flow_aadt > 0) {
            $result = 'Public road';
        }
        elseif ($bicycle_facility == 3 && $vehicle_flow_aadt > 0) {
            $result = 'Painted cycle lane';
        }
        elseif (($bicycle_facility == 1 || $bicycle_facility == 2 || $bicycle_facility == 7) && $vehicle_flow_aadt > 0) {
            $result = 'Cycle path';
            if ($bicycle_facility != 2 && $pedestrian_observed > 0) {
                $result = 'Cycle and pedestrian path';
            }
        }
        return $result;
    }
    
    protected function makeWKTLinestring(array $points): string
    {
        $coord = array();
        foreach ($points as $p) {
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
     * @return array
     */
    public function getSpoSet()
    {
        return $this->spoSet;
    }

    /**
     * @return array
     */
    public function getSurveySet()
    {
        return $this->surveySet;
    }

    /**
     * @return array
     */
    public function getMinorSet()
    {
        return $this->minorSet;
    }

    
}

