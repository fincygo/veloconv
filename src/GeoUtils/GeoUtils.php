<?php
namespace App\GeoUtils;


/**
 *
 * @author petrics.lajos
 *        
 */
class GeoUtils
{
    // Earth's radius in meters
    const GEO_R = 6378137 ;

    /**
     */
    public function __construct()
    {
        
    }
    
    /**
     * SPLITARC Calculates the coordinates between the start and end points at each split distance
     * All coord input in degrees.
     * Output array in degrees
     * @param float $latA
     * @param float $lonA
     * @param float $bearing
     * @param float $distance
     */
    public function splitArc(float $latStart, float $lonStart, float $latDest, float $lonDest, float $split) : array
    {
        $dest = array();
        $latA = deg2rad($latStart); $lonA = deg2rad($lonStart);
        $latB = deg2rad($latDest); $lonB = deg2rad($lonDest);

        $bearing = $this->bear($latA, $lonA, $latB, $lonB);
        $dist = $this->dist($latA, $lonA, $latB, $lonB);
        
        $newSplit = $split;
        while ($dist > $newSplit) {
            $point = $this->calcDest($latA, $lonA, $bearing, $newSplit);
            foreach ($point as $p) {
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
        $dest['lon'] = $dest[1] = $lonA + atan2( sin($bearing) * sin($angDist) * cos($latA), cos($angDist) - sin($latA) * sin($dest[0]) );
        
        return $dest;
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

