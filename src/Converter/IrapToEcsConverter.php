<?php
namespace App\Converter;

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
     */
    public function __construct()
    {
        $this->averageHeight = 0;
        $this->maxDivergence = 1.0;
        $this->minLength = 200.0;
        $this->maxLength = 5000.0;
    }
}

