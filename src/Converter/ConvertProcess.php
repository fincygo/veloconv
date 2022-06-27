<?php
namespace App\Converter;

use App\Service\CSVHandler;

/**
 *
 * @author petrics.lajos
 *        
 */
class ConvertProcess
{

    const ERRCP_OPEN         = array( "code" => -1, "message" => "Input file can't process. Invalid file format or open error." );
    const ERRCP_ECS_TYPE     = array( "code" => -2, "message" => "iRAP conversion detected but the converter assumes records for the minor section of the ECS survey." );    
    const ERRCP_IRAP_PROCESS = array( "code" => -3, "message" => "Error in ECS to iRAP conversion." );
    const ERRCP_ECS_PROCESS  = array( "code" => -4, "message" => "Error in iRAP to ECS conversion." );
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
     * @var string
     */
    protected $inputFilePath;

    /**
     * @var string
     */
    protected $outputFilePath;

    /**
     * @var int
     */
    protected $inputType;

    /**
     * @var int
     */
    protected $outputType;
  
    
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
     * @param string $path
     */
    public function setInputFilePath($path)
    {
        $this->inputFilePath = $path;
    }

    /**
     * @param string $path
     */
    public function setOutputFilePath($path)
    {
        $this->outputFilePath = $path;
    }

    /**
     * @param int $type
     */
    public function setInputType($type)
    {
        $this->inputType = $type;
    }

    /**
     * @param int $type
     */
    public function setOutputType($type)
    {
        $this->outputType = $type;
    }


    //***************************************************************************************************************
    public function __construct( ContainerBagInterface $params )
    //===============================================================================================================
    {
        $this->params = $params;
    }
    //***************************************************************************************************************


    //===============================================================================================================
    public function doConvert( $userParams ):bool
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        $inputHandle = new CSVHandler( $this->params );
        if ( false === ($this->inputType = $inputHandle->openCSVFile( $this->inputFilePath )) )
        {
            switch ($this->inputType)
            {
                case CSVHandler::CSVT_IRAP:   
                                 
                    $converter = new IrapToEcsConverter( $inputHandle, $this->outputFilePath );

                    if ( is_array($userParams) && array_key_exists( IrapToEcsConverter::NAME_AVGHEIGHT, $userParams ) )
                        $converter->setAverageHeight( $userParams[ IrapToEcsConverter::NAME_AVGHEIGHT] );
                    if ( is_array($userParams) && array_key_exists( IrapToEcsConverter::NAME_MAXDIVERGENCE, $userParams ) )
                        $converter->setAverageHeight( $userParams[IrapToEcsConverter::NAME_MAXDIVERGENCE] );
                    if ( is_array($userParams) && array_key_exists( IrapToEcsConverter::NAME_MINLENGTH, $userParams ) )
                        $converter->setAverageHeight( $userParams[IrapToEcsConverter::NAME_MINLENGTH] );
                    if ( is_array($userParams) && array_key_exists( IrapToEcsConverter::NAME_MAXLENGTH, $userParams ) )
                        $converter->setAverageHeight( $userParams[IrapToEcsConverter::NAME_MAXLENGTH] );

                    if ( $converter->processIrapFile() )
                    {
                        $outputPath = getPath( $this->inputFilePath );
                        $outputFileName = $outputPath . "/" . $inputHandle->getConfig->getTemplateNameByType( CSVHandler::CSVT_ECS_SURVEYS ) . ".csv";
                        $inputHandle->saveCSVFile(CSVHandler::CSVT_ECS_SURVEYS, $outputFileName, $converter->getSurveySet() );
                        ...
                        return true;
                    }

                    $this->setError( ERRCP_IRAP_PROCESS );
                    break;

                case CSVHandler::CSVT_ECS_MINORSECTION:    
                    break;

                case CSVHandler::CSVT_ECS_SURVEYS:    
                case CSVHandler::CSVT_ECS_MINORSECTION:
                    $this->setError( ERRCP_ECS_TYPE );
                    break;                    
            }
        }
        else
            $this->setError( ERRCP_OPEN );
        return false;
    }
    //===============================================================================================================
    
}

