<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\CSVField;

/**
 *
 * @author finta.gabor
 *        
 */
class CSVConfig
{

    /**
     * @var ContainerBagInterface
     */    
    private $param;

    /**
     * @var array
     */    
    private $fieldsDefinitions;

    /**
     * @var bool
     */    
    private $isLoaded;


    /**
     */
    public function __construct( ContainerBagInterface $params )
    {
        $this->params            = $params;
        $this->fieldsDefinitions = false;
        $this->isLoaded          = $this->load();        
    }

    //==============================================================================================================
    public function isLoaded()
    //--------------------------------------------------------------------------------------------------------------
    {
        return $this->isLoaded;
    }
    //==============================================================================================================

    //==============================================================================================================
    public function createCanonicalFieldName( string $name ):string
    //--------------------------------------------------------------------------------------------------------------
    //  The names in the csv header are converted to standard data table column names. 
    //  The capital letters are converted to small letters, 
    //  special codes, e.g. spaces, “ ”, brackets, “(”, ”)”  slashes “/” , “\”, dashes “-” are replaced by underscore, “_”. 
    //  Instead of multiple underscores only one is used, e.g. “<space><dash><space>“ converted to a single underscore ”_”. 
    //  No underscore at the end of a column name, 
    //  e.g. the column name of the irap.csv header “Vehicle flow (AADT)” is converted to “vehicle_flow_aadt”.
    //    
    {
        $pattern = "/['_','\-','\/','\\\',' ','(',')']+/i";
        return  strtolower( trim( preg_replace($pattern, '_', $name ), '_' ));
    }
    //==============================================================================================================    


    //==============================================================================================================    
    protected function load():bool
    //--------------------------------------------------------------------------------------------------------------
    // load configuration from the services.yaml::csv_header_configuration file in .env::CSV_CONFIGPATH path
    //
    {        
        $fullConfigPath  =  $this->params->get('csv_header_configuration');
        if ( false === ($cfgFields = \file_get_contents( $fullConfigPath )) )
            return false;

        if ( null != ($aData = json_decode( $cfgFields , true )))
        {            
            $this->fieldsDefinitions = array();
            foreach( $aData["fields"] as $data )
            {
                $newField = new CSVField( count($this->fieldsDefinitions)+1, $data );
                $this->fieldsDefinitions[] = $newField;
            }
        }
        return true;
    }
    //==============================================================================================================    



    //==============================================================================================================    
    public function findFieldsByName( string $name )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        if ( ($this->isLoaded === false) )
            return false;

        $aResult = array();
        foreach ($this->fieldsDefinitions as $field )
        {
            if ( strcmp( $field->getCanonical(), $this->createCanonicalFieldName( $name ) ) === 0 )
                $aResult[] = $field;
        }

        return ( count($aResult) > 0 ? $aResult : false );
    }
    //==============================================================================================================    


    //==============================================================================================================    
    public function getFieldByName( int $type, string $name )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        if ( ($this->isLoaded === false) )
            return false;
    
        foreach ($this->fieldsDefinitions as $field )
        {
            if ( ($field->getFileId() == $type) && (strcmp( $field->getCanonical(), $this->createCanonicalFieldName( $name ) ) === 0) )
                return $field;
        }
       
        return false;
    }
    //==============================================================================================================    
    


    //==============================================================================================================    
    public function getCSVFieldsByType( int $type)
    //--------------------------------------------------------------------------------------------------------------
    {
        if ( ($this->isLoaded === false) )
            return false;

        $aResult = array();            
        foreach ($this->fieldsDefinitions as $field )
        {
            if ($field->getFileId() == $type )
                $aResult[] = $field;
        }
        
        return ( count($aResult) > 0 ? $aResult : false );
    }
    //==============================================================================================================    


    //==============================================================================================================    
    public function getCSVFieldArrayByType( int $type )
    //--------------------------------------------------------------------------------------------------------------
    {
        if ( ($this->isLoaded === false) )
            return false;

        $aResult = array();            
        foreach ($this->fieldsDefinitions as $field )
        {
            if ($field->getFileId() == $type )
                $aResult[ $field->getCanonical() ] = null;
        }
        
        return ( count($aResult) > 0 ? $aResult : false );
    }
    //==============================================================================================================    


    //==============================================================================================================    
    public function getTemplateNameByType( int $type )
    //--------------------------------------------------------------------------------------------------------------
    {
        if ( ($this->isLoaded === false) )
            return false;

        $aResult = array();            
        foreach ($this->fieldsDefinitions as $field )
        {
            if ($field->getFileId() == $type )
                return $field->getFileName();
        }        
        return false;
    }
    //==============================================================================================================      
}