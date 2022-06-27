<?php
namespace App\Converter;

use App\Service\CSVHandler;

/**
 *
 * @author petrics.lajos
 *        
 */
class BaseConverter
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
     * @var CSVHandler
     */
    protected $csvhandler;
    
    /**
     */
    public function __construct()
    {}
}

