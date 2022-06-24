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
    const GEO_R = 6371000;

    /**
     */
    public function __construct()
    {
        
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

