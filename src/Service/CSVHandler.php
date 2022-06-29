<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\CSVConfig;

/**
 *
 * @author petrics.lajos
 *        
 */
class CSVHandler
{
    const ERROR_CONFIG            = "Error while reading the fields configuration file.";
    const ERROR_HEADER            = "Error in processing CSV header or header missing.";    
    const ERROR_DATA              = "Error in processing data line.";
    const ERROR_BADDATA           = "Bad fileformat, the data does not match the header.";
    const ERROR_TYPEMISMATCH      = "CSV file type not equal the specified type.";
    const ERROR_CSVNOTOPEN        = "Open the CSV file first.";

    const REGEX_FIELDS            = "/\"( (?:[^\"\\\\]++|\\\\.)*+ ) \" | ' ( (?:[^'\\\\]++|\\\\.)*+ ) ' | \( ( [^)]* ) \) | [\s#]+ /x ";

    const CSVT_IRAP               = 1;
    const CSVT_ECS_SURVEYS        = 2;
    const CSVT_ECS_MINORSECTION   = 3;
    const CSVT_ECS_POINTS         = 4;

    /**
     * @var CSVConfig
     */
    private $config;

    /**
     * @var string
     */
    private $filename;


    /**
     * @var string
     */
    private $delimiter;
    
    /**
     * @var bool
     */
    private $textwrapper;
    
    

    /**
     * @var array
     */
    private $headerInfo;

    /**
     * @var object
     */
    private $fieldsDefinitions;

    /**
     * @var string
     */
    private $csvType;

    /**
     * @var string
     */
    private $csvTemplate;


    /**
     * @var ContainerBagInterface
     */
    private $params;

    /**
     * @var int
     */
    private $firstDataLinePosition;

    /**
     * @var string
     */
    private $lastError;


    //***************************************************************************************************************
    public function __construct( ContainerBagInterface $params )
    //===============================================================================================================
    {
        $this->params                = $params;
        $this->fieldsDefinitions     = false;
        $this->config                = new CSVConfig( $this->params );
        $this->firstDataLinePosition = false;
    }
    //**************************************************************************************************************


    //==============================================================================================================
    protected function setLasterror( string $error )
    //--------------------------------------------------------------------------------------------------------------
    //
    {    
        $this->lastError = $error;
    }
    //==============================================================================================================


    //==============================================================================================================
    public function openCSVFile( string $filename ):int 
    //--------------------------------------------------------------------------------------------------------------
    // Open file, read header information and check type
    //
    {        
        $this->filename = $filename;
        $fullFilePath   =  $this->params->get('csv_file_rootpath') . "/" . $this->filename;

        try
        {
            if ( ! $this->config->isLoaded()  )
                throw new \RuntimeException( CSVHandler::ERROR_CONFIG );

            $file = new \SplFileObject( $fullFilePath, "r") ;
            while ( !$file->eof() )
            {
                if ( empty( $line = trim( $file->fgets() )) )
                    continue;

                if ( false === $this->analyseHeader( $line ) )
                   throw new \RuntimeException( CSVHandler::ERROR_HEADER );
                $this->firstDataLinePosition = $file->ftell();
                break;
            }
        } 
        catch( \RuntimeException $e )
        {
            $this->setLastError( $e->getMessage() );
            $this->firstDataLinePosition = false;
            $file = null;
            return false;
        }
        $file = null;
        return $this->getType();        
    }
    //
    //==============================================================================================================
    public function loadCSVDataToRecordset( &$recordSet )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        if ( empty($this->filename) || $this->firstDataLinePosition === false )
        {
            $this->setLastError( CSVHandler::ERROR_CSVNOTOPEN );
            return false;
        }
        $fullFilePath   =  $this->params->get('csv_file_rootpath') . "/" . $this->filename;
        try
        {
            if ( ! $this->config->isLoaded()  )
                throw new \RuntimeException( CSVHandler::ERROR_CONFIG );

            $file = new \SplFileObject( $fullFilePath, "r") ;
            $file->fseek( $this->firstDataLinePosition );
            $recno = 0;
            while ( !$file->eof() )
            {
                if ( empty( $line = trim( $file->fgets() )) )
                    continue;
                
                if ( false === ($aRecord = $this->analyseData( $line )) )
                   throw new \RuntimeException( CSVHandler::ERROR_DATA );

                ++$recno;
                switch ($this->csvType)
                {
                    case CSVHandler::CSVT_IRAP:
                        $this->addIRAPRecord( $recno, &$recordSet, $aRecord );
                        break;
                    case CSVHandler::CSVT_ECS_SURVEYS:
                        $this->addECSSurveyRecord( $recno,&$recordSet, $aRecord );
                        break;
                    case CSVHandler::CSVT_ECS_MINORSECTION:
                        $this->addECSSectionRecord( $recno, $recordSet, $aRecord );
                        break;
                    case CSVHandler::CSVT_ECS_POINTS:
                        $this->addECSPointRecord( $recno, $recordSet, $aRecord );
                        break;           
                }
                $aRecord = false;
            }
        } 
        catch( \RuntimeException $e )
        {
            $this->setLastError( $e->getMessage() );
            $this->firstDataLinePosition = false;
            $file = null;
            return false;
        }
        $file = null;
        return true;        
    }
    //
    //==============================================================================================================    
    public function addIRAPRecord( $recno, &$recordset, $data )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        $newRecord = new IRAPRecord($data);
        $newRecord->setId( $recno );
        $newRecord->setVertex( $recno === 1 );
        $recordset[] = $newRecord;
    }
    //
    //==============================================================================================================    
    public function addECSSurveyRecord( $recno, $recordset, $data )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
    }
    //
    //==============================================================================================================    
    public function addECSSectionRecord( $recno, $recordset, $data )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        $newRecord = new MinorSectionRecord($data);
        //$newRecord->setId( $recno );
        $recordset[] = $newRecord;
    }
    //
    //==============================================================================================================    
    public function addECSPointRecord( $recno, $recordset, $data )
    //--------------------------------------------------------------------------------------------------------------
    //
    {
    }
    //
    //==============================================================================================================    




    //==============================================================================================================
    protected function splitByDelimiter( string $line ):array
    //--------------------------------------------------------------------------------------------------------------
    {
        $fields = array();
        $text   = "";
        $inside = false;
        for ( $p = 0; $p<strlen($line); ++$p )
        {
            $ch = substr( $line, $p, 1 );
            if ( $inside )
            {
                if ($ch == '"' )  $inside = false; else $text .= $ch;    
            }
            else
            {
                if ( $ch === $this->delimiter )
                {
                    $fields[] = $text;
                    $text = "";
                }
                else
                {
                    if ($ch === '"') $inside = true; else $text .= $ch;
                }
            }
        }
        if (strlen($text) > 0 ) $fields[] = $text;

        /*
        if ( $this->textwrapper )
        {
            //
            // A fejlécben észlelt delimiterek meghatározzák a mezők számát. Elvileg minden mezőnevet határolók közé kell rakni, 
            // ha ilyen formátuma van a CSV-nek. Tehát ha minden mezőt határolunk, akkor 2x annyinak kell a határolóból lenni, mint
            // a mezőből. Ezért a > 0 helyett legalább a fieldCountByDelimiter-hez viszonyítom.
            //
            $pattern  = str_replace( "#", $this->delimiter,  CSVHandler::REGEX_FIELDS );
            $fields   = preg_split( $pattern, $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
        }
        else
            $fields   = explode( $this->delimiter, $line );
        */            
        return $fields;
    }
    //==============================================================================================================


    //==============================================================================================================
    protected function analyseHeader( string $line ):bool
    //--------------------------------------------------------------------------------------------------------------
    //
    // 3.1. iReg
    //      first row is a header that includes name of columns; 
    //      column names have capital letters and space;
    //      values are strictly separated by comma;
    //      quotations marks are not used for texts;
    // 3.2. ECS
    //      values are strictly separated by comma; 
    //      quotation marks may be used for texts.
    //
    // we accept comma semicolon and tab separators only.
    // 
    {  
        $delimiters = array("comma" => ',', "semi" => ';', "tab" => '\t');
        $cntr       = array("comma" =>  substr_count($line, $delimiters["comma"] ),
                            "semi"  =>  substr_count($line, $delimiters["semi"]  ),
                            "tab"   =>  substr_count($line, $delimiters["tab"]   )        
                           );
        arsort( $cntr );
        $this->delimiter       = $delimiters[ array_key_first($cntr) ];
        $fieldCountByDelimiter = $cntr[ array_key_first($cntr) ] + 1;
        $this->textwrapper     = ( substr_count($line, "\"" ) > $fieldCountByDelimiter || substr_count($line, "\'" ) > $fieldCountByDelimiter );
        $fields                = $this->splitByDelimiter( $line );
        
        if ( count($fields) > 0 )
        {
            $this->detectCSVbyFieldset( $fields );
            return true;
        }
        return false;
    }
    //==============================================================================================================

     

    //==============================================================================================================    
    protected function detectCSVbyFieldset( $fields ):bool
    //--------------------------------------------------------------------------------------------------------------
    // Ha van azonos nevű mező (pld. id), akkor előfordulhat, hogy másik típushoz és/vagy fájlhoz találja meg.
    // Ezért itt nem lehet betölteni a config szerinti mezőadatokat. Azt lehetne csinálni, hogy amikor megvan
    // a tényleges típus és a template, akkor összerendelni. De mi van, ha kapunk egy olyan mezőt ami abban 
    // a template-ben nem szerepel? Akkor el lehet utasítani, vagy az ilyen mezőt ki kell hagyni a feldolgozásból.
    // A listából nem szabad törölni, mert ha a fájl egyébként helyes CSV, akkor vannak hozzá adatok az adatsorban. 
    //
    {
        $listInvalid     = array();
        $listTemplates   = array();
        $listFileId      = array( 0=>0, 1=>0, 2=>0, 3=>0, 4=>0 );
        $hdrFields       = array();

        foreach ( $fields as $field )
        {            
            $canonicalFieldName = $this->config->createCanonicalFieldName( $field ); 
            $hdrFields[ $canonicalFieldName ] = $field;    //  hdrFields[<cononical field name>] = <original field name>
            if ( false === ($aFieldDefs = $this->config->findFieldsByName( $field ) ))
            {
                $listInvalid[] = $hdrFields[ $field ];
            }
            else
            {
                foreach ($aFieldDefs as $fieldDef )
                {
                    if ( ($fidInd = $fieldDef->getFileId()) != -1 )                
                        ++$listFileId[ $fidInd ];
                
                    if ( !array_key_exists( $fieldDef->getFileName(), $listTemplates ) )
                        $listTemplates[ $fieldDef->getFileName() ] = 0;
                    ++$listTemplates[ $fieldDef->getFileName() ];
                }
            }
        }
        arsort( $listTemplates );
        arsort( $listFileId );

        $this->csvType     = array_key_first( $listFileId    );
        $this->csvTemplate = array_key_first( $listTemplates );
        $this->setHeaderInfo( $hdrFields );
        // echo "found:" . array_key_first($listFileId) . " - " . array_key_first($listTemplates) . "\n";
        return true;
    }
    //==============================================================================================================



    //==============================================================================================================    
    protected function setHeaderInfo( $fields )
    //--------------------------------------------------------------------------------------------------------------
    // set headerinfo 
    // csv => conatines the field name from CSV. Maybe an unknown field name
    // def => conatines the field deffinition from the config file for the csv field. false if csv is unknown field name
    //
    {
        $this->headerInfo = array();
        foreach ( $fields as $canonicalName => $originalName )
        {
            $data = array();
            $data["csv"] = $originalName;
            $data["def"] = $this->config->getFieldByName($this->csvType, $canonicalName );
            $this->headerInfo[$canonicalName] = $data;
        }
    }
    //==============================================================================================================    
    public function getHeaderInfo()
    //--------------------------------------------------------------------------------------------------------------
    {
        return $this->headerInfo;
    }
    //==============================================================================================================    
    public function getNumberOfFields()
    //--------------------------------------------------------------------------------------------------------------
    {
        return (is_array( $this->headerInfo ) ? count( $this->headerInfo ) : 0);
    }
    //============================================================================================================== 



    //==============================================================================================================
    protected function analyseData( $line )
    //--------------------------------------------------------------------------------------------------------------
    {
        $fields = $this->splitByDelimiter( $line );
        if ( count( $fields ) === $this->getNumberOfFields()  ) 
        {
            $fieldNames = array_keys( $this->headerInfo );
            $hdrIndex   = -1;
            $aResult    = array();
            foreach ($fields as $value )
                $aResult[ $fieldNames[++$hdrIndex] ] = $value;

            return ( count($aResult) > 0 ? $aResult : false );
        }
        else
            throw new \RuntimeException( CSVHandler::ERROR_BADDATA );

        return false;
    }
    //==============================================================================================================


    //==============================================================================================================
    public function saveCSVFile( $nType, $fileName, $recordset ):bool
    //--------------------------------------------------------------------------------------------------------------
    // 
    {
        $this->filename = $fileName;
        $fullFilePath   =  $this->params->get('csv_file_rootpath') . "/" . $this->filename;
        try
        {
            if ( ! $this->config->isLoaded()  )
                throw new \RuntimeException( CSVHandler::ERROR_CONFIG );
            
            $file = new \SplFileObject( $fullFilePath, "w");
            //.......................................................................
            //
            $header = $this->config->getCSVFieldsByType( $nType );
            $this->delimiter = ( $nType == CSVHandler::CSVT_IRAP ? ';' : ',' );
            $line = "";
            foreach ( $header as $field )
            {
                $line .= ( empty($line) ? "" : $this->delimiter ) . $field->getName();
            }
            $line .= "\r\n";
            $file->fwrite( $line );
            $file->fflush();
            //
            //.......................................................................
            //
            //$records = $recordset->getRecords();            
            //foreach ( $recordset as $record )
            $nIndex = -1;
echo "number of records:" . count($recordset) ;
            while (++$nIndex < count($recordset) )
            {
                $record  = $recordset[$nIndex];
                $nField  = 0;
                $line    = "";
                foreach ( $header as $fields )
                {
                    $line .= ( ++$nField == 1 ? "" : $this->delimiter );                                        
                    switch ( $fields->getDataType() )
                    {
                        case "date":
                            $line .= sprintf( "%s", $record->getFieldValue( $fields->getCanonical() ) );
                            break;
                        case "string":
                            $value = $record->getFieldValue( $fields->getCanonical() );
                            if ( strpos($value, $this->delimiter ) !== false )
                                $line .= sprintf( "%c%s%c", '"', $value, '"' );
                            else
                                $line .= sprintf( "%s", $value );
                            break;
                        default:
                            $line .= sprintf( $fields->getFormat(), $record->getFieldValue( $fields->getCanonical() ) );
                            break;
                    }
                }
                $line .= "\r\n";
                $file->fwrite( $line );
                $file->fflush();       
            }
        } 
        catch( \RuntimeException $e )
        {
            $this->setLastError( $e->getMessage() );
            $this->firstDataLinePosition = false;
            $file = null;
            return false;
        }
        
        $file = null;             
        return true;
    }
    //==============================================================================================================



    //==============================================================================================================
    public function getConfig():CSVConfig
    //--------------------------------------------------------------------------------------------------------------
    // return the CSV type
    {
        return $this->config;
    }
    //==============================================================================================================
    public function getType():int 
    //--------------------------------------------------------------------------------------------------------------
    // return the CSV type
    {
        return $this->csvType;
    }
    //==============================================================================================================
    public function getTemplate():string
    //--------------------------------------------------------------------------------------------------------------
    // return the template name
    {
        return $this->csvTemplate;
    }
    //==============================================================================================================
    public function checkTemplate( string $templateName ):bool
    //--------------------------------------------------------------------------------------------------------------
    {
        return ($this->csvTemplate && $this->csvTemplate == $templateName );             
    }
    //==============================================================================================================
    public function checkType( string $type ):bool
    //--------------------------------------------------------------------------------------------------------------
    {
        return ($this->csvType && $this->csvType == $type );
    }
    //==============================================================================================================
    public function getLastError():string
    //--------------------------------------------------------------------------------------------------------------
    {
        return $this->lastError;
    }
    //==============================================================================================================


}

