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

    
}

