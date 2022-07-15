<?php
namespace App\GeoUtils;
/*
---------------sample values in Europe
X = longitude      26.00434344
Y = latitude       43.81530001

LINESTRING Z ( X Y Z, .... )

*/

/**
 *
 * @author petrics.lajos
 *        
 */
class GeoUtils
{
    // Earth's radius in meters
    // const GEO_R = 6378137;
    const GEO_R = 6371000;
    
    /**
     */
    public function __construct()
    {
        
    }
    
    /**
     * SPLITARC Calculates the coordinates between the start and end points at each split distance
     * All coord input in degrees.
     * Output array in degrees
     * @param float $latStart
     * @param float $lonStart
     * @param float $latDest
     * @param float $lonDest
     * @param float $split
     */
    public function splitArc(float $latStart, float $lonStart, float $latDest, float $lonDest, float $split) : array
    {
        $dest = array();
        $latA = deg2rad($latStart); $lonA = deg2rad($lonStart);
        $latB = deg2rad($latDest);  $lonB = deg2rad($lonDest);

        $bearing = $this->bear($latA, $lonA, $latB, $lonB);
        $dist    = $this->distH($latStart, $lonStart, $latDest, $lonDest);
        
        $newSplit = $split;
        while ($dist > $newSplit) 
        {
            $point = $this->calcDest($latA, $lonA, $bearing, $newSplit);
            foreach ($point as &$p) {
                $p = rad2deg($p);
            }            
            $dest[] = $point;        
            $newSplit += $split;
        }    
        return $dest;
    }
    
    
    /**
     * CALCDEST Calculates destination point given distance and bearing from start point
     *     Formula:	φ2 = asin( sin φ1 ⋅ cos δ + cos φ1 ⋅ sin δ ⋅ cos θ )
     *     λ2 = λ1 + atan2( sin θ ⋅ sin δ ⋅ cos φ1, cos δ − sin φ1 ⋅ sin φ2 )
     *     where	φ is latitude, λ is longitude, θ is the bearing (clockwise from north), δ is the angular distance d/R; d being the distance travelled, R the earth’s radius
     * 
     * Input latA,lonA in radian.
     * Output array in radian
     * @param float $latA
     * @param float $lonA
     * @param float $bearing
     * @param float $distance
     */
    public function calcDest(float $latA, float $lonA, float $bearing, float $distance) : array
    {
        $dest = array();
        $angDist = $distance / self::GEO_R;
        $dest['lat'] = $dest[0] = asin( sin($latA) * cos($angDist) + cos($latA) * sin($angDist) * $bearing );
        $dest['lon'] = $dest[1] = $lonA + atan2( sin($bearing) * sin($angDist) * cos($latA), cos($angDist) - sin($latA) * sin($dest['lat']) );
        
        return $dest;
    }
    
    
    /**
     * getIntermediatePoints Calculates the coordinates between the start and end points at each split distance
     * Formula:
     * a = sin((1−f)⋅δ) / sin δ
     * b = sin(f⋅δ) / sin δ
     * x = a ⋅ cos φ1 ⋅ cos λ1 + b ⋅ cos φ2 ⋅ cos λ2
     * y = a ⋅ cos φ1 ⋅ sin λ1 + b ⋅ cos φ2 ⋅ sin λ2
     * z = a ⋅ sin φ1 + b ⋅ sin φ2
     * φi = atan2(z, √x² + y²)
     * λi = atan2(y, x)
     * where f is fraction along great circle route (f=0 is point 1, f=1 is point 2), δ is the angular distance d/R between the two points.
     * 
     * All coord input in degrees.
     * Output array in degrees
     * @param float $latStart
     * @param float $lonStart
     * @param float $latDest
     * @param float $lonDest
     * @param float $split
     */
    public function getIntermediatePoints(float $latStart, float $lonStart, float $latDest, float $lonDest, float $split) : array
    {
        $impoints = array();
        
        $latA = deg2rad($latStart); $lonA = deg2rad($lonStart);
        $latB = deg2rad($latDest);  $lonB = deg2rad($lonDest);
        
        $dist = $this->distH($latStart, $lonStart, $latDest, $lonDest);
        $dR   = $dist / self::GEO_R;
        
        $newSplit = $split;
        while ($newSplit < $dist)
        {
            $f = $newSplit/$dist;
            $a = sin((1-$f) * $dR) / sin($dR);
            $b = sin($f * $dR) / sin($dR);
            $x = ($a * cos($latA) * cos($lonA)) + ($b * cos($latB) * cos($lonB));
            $y = ($a * cos($latA) * sin($lonA)) + ($b * cos($latB) * sin($lonB));
            $z = ($a * sin($latA)) + ($b * sin($latB));
            
            $point = array();
            $point['lat'] = $point[0] = rad2deg( atan2( $z, sqrt( ($x*$x) + ($y*$y) ) ) );
            $point['lon'] = $point[1] = rad2deg( atan2( $y, $x ) );
            
            $impoints[] = $point;
            $newSplit += $split;
        }
        
        return $impoints;
    }
    
    /**
     * CROSSARC Calculates the shortest distance in meters
     * between an arc (defined by p1 and p2) and a third point, p3.
     * Input latA,lonA,latB,lonB,latC,lonC in degrees.
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @param float $lat3
     * @param float $lon3
     * @return float
     */
    public function crossArc(float $latA, float $lonA, float $latB, float $lonB, float $latC, float $lonC) : float
    {
        $lat1 = deg2rad($latA); $lat2 = deg2rad($latB); $lat3 = deg2rad($latC);
        $lon1 = deg2rad($lonA); $lon2 = deg2rad($lonB); $lon3 = deg2rad($lonC);
        
        // Prerequisites for the formulas
        $bear12 = $this->bear($lat1, $lon1, $lat2, $lon2);
        $bear13 = $this->bear($lat1, $lon1, $lat3, $lon3);
        $dist13 = $this->dist($lat1, $lon1, $lat3, $lon3);
        
        $diff = abs($bear13-$bear12);
        if ($diff > pi()) {
            $diff = 2 * pi() - $diff;
        }

        // Is relative bearing obtuse?
        if ($diff > (pi()/2)) {
            $result = $dist13;
        }
        else {
            // Find the cross-track distance.
            $dxt = asin( sin($dist13 / self::GEO_R)* sin($bear13 - $bear12) ) * self::GEO_R;
            
            // Is p4 beyond the arc?
            $dist12 = $this->dist($lat1, $lon1, $lat2, $lon2);
            $dist14 = acos( cos($dist13 / self::GEO_R) / cos($dxt / self::GEO_R) ) * self::GEO_R;
            if ($dist14 > $dist12) {
                $result = $this->dist($lat2, $lon2, $lat3, $lon3);
            }
            else {
                $result = abs($dxt);
            }
        }
        
        return $result;
    } 
    
    /**
     * DISTH Finds the distance between two lat/lon points uses the ‘haversine’ formula.
     * All parameters in degrees
     * @param float $latA
     * @param float $lonA
     * @param float $latB
     * @param float $lonB
     * @return float
     */
    public function distH(float $latA, float $lonA, float $latB, float $lonB) : float
    {
        $lat1 = deg2rad($latA);
        $lat2 = deg2rad($latB);
        $dlat = deg2rad($latB-$latA);
        $dlon = deg2rad($lonB-$lonA);
        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
        $c = atan2(sqrt($a), sqrt(1-$a)) * 2;

        return $c * self::GEO_R;
    }
    
    /**
     * DIST Finds the distance between two lat/lon points.
     * All parameters in radian
     * @param float $latA
     * @param float $lonA
     * @param float $latB
     * @param float $lonB
     * @return float
     */
    public function dist(float $latA, float $lonA, float $latB, float $lonB) : float
    {
        $result = acos( sin($latA)*sin($latB) + cos($latA)*cos($latB)*cos($lonB-$lonA) ) * self::GEO_R;
        
        return $result;
    }
    
    /**
     * BEAR Finds the bearing from one lat/lon point to another.
     * All parameters in radian
     * @param float $latA
     * @param float $lonA
     * @param float $latB
     * @param float $lonB
     */
    public function bear(float $latA, float $lonA, float $latB, float $lonB) : float
    {
        $result = atan2( sin($lonB-$lonA)*cos($latB) , cos($latA)*sin($latB) - sin($latA)*cos($latB)*cos($lonB-$lonA) );
        
        return $result;
    }
    
    
}

