<?php
namespace App\service;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
// $this->params->get('res_download_path');
// $regex = "/\"( (?:[^\"\\\\]++|\\\\.)*+ ) \" | ' ( (?:[^'\\\\]++|\\\\.)*+ ) ' | \( ( [^)]* ) \) | [\s,]+ /x ";
// $tags = preg_split($regex, $str, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

/**
 *
 * @author petrics.lajos
 *        
 */
class CSVHandler
{
    const ERROR_CONFIG  = "Error while reading the fields configuration file.";
    const ERROR_HEADER  = "Error in processing CSV header or header missing.";    
    const ERROR_DATA    = "Error in processing data line.";
    const REGEX_FIELDS  = "/\"( (?:[^\"\\\\]++|\\\\.)*+ ) \" | ' ( (?:[^'\\\\]++|\\\\.)*+ ) ' | \( ( [^)]* ) \) | [\s#]+ /x ";

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
    private $hdrFields;

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
     */
    public function __construct( ContainerBagInterface $params )
    {
        $this->params            = $params;
        $this->fieldsDefinitions = false;
    }


    //==============================================================================================================
    public function loadConfig():bool
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        if (is_array($this->fieldsDefinitions) && count($this->fieldsDefinitions) > 0 )
            return true;

        $fullConfigPath  =  $this->params->get('csv_header_configuration');
        if ( false === ($cfgFields = \file_get_contents( $fullConfigPath )) )
        {
            throw new \RuntimeException( CSVHandler::ERROR_CONFIG );
            return false;
        }

        return ( null !== ($this->fieldsDefinitions = json_decode( $cfgFields , true )) );
    }
    //
    //==============================================================================================================
    

    //==============================================================================================================
    public function loadFile(string $filename):bool
    //--------------------------------------------------------------------------------------------------------------
    //
    {
        $this->filename = $filename;
        $fullFilePath   =  $this->params->get('csv_file_rootpath') . "/" . $this->filename;
        try
        {
            $this->loadConfig();
            $file = new \SplFileObject( $fullFilePath, "r") ;
            $isHeaderDetected = false;
            while ( !$file->eof() )
            {
                if ( empty( $line = trim( $file->fgets() )) )
                    continue;

                if ( ! $isHeaderDetected )
                {
                    if ( false === $this->analyseHeader( $line ) )
                        throw new \RuntimeException( CSVHandler::ERROR_HEADER );
                    $isHeaderDetected = true;
                }
                else
                {
                    if ( false === $this->analyseData( $line ) )
                        throw new \RuntimeException( CSVHandler::ERROR_DATA );
                }
            }
        } catch( \RuntimeException $e )
        {
            echo "error: {$e->getMessage()}\n" ;
            $file = null;
            return false;
        }

        $file = null;
        return true;        
    }
    //==============================================================================================================



    //==============================================================================================================
    protected function csvFieldName( string $name ):string
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
        $this->delimiter = $delimiters[ array_key_first($cntr) ];
        $fieldCountByDelimiter = $cntr[ array_key_first($cntr) ] + 1;
        if ( substr_count($line, "\"" ) > $fieldCountByDelimiter || substr_count($line, "\'" ) > $fieldCountByDelimiter )
        {
            //
            // A fejlécben észlelt delimiterek meghatározzák a mezők számát. Elvileg minden mezőnevet határolók közé kell rakni, 
            // ha ilyen formátuma van a CSV-nek. Tehát ha minden mezőt határolunk, akkor 2x annyinak kell a határolóból lenni, mint
            // a mezőből. Ezért a > 0 helyett legalább a fieldCountByDelimiter-hez viszonyítom.
            //
            $this->textwrapper = true;
            $pattern  = str_replace( "#", $this->delimiter,  CSVHandler::REGEX_FIELDS );
            $fields   = preg_split( $pattern, $line, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
        }
        else
            $fields   = explode( $this->delimiter, $line );
        
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
        $listTypes       = array();
        $this->hdrFields = array();

        foreach ( $fields as $field )
        {
            $this->hdrFields[ $field ] = $this->csvFieldName( $field );
            if ( false === ($aFieldDef = $this->findFieldDefinition( $this->hdrFields[ $field ]) ))
            {
                $listInvalid[] = $this->hdrFields[ $field ];
            }
            else
            {
                if ( !array_key_exists( $aFieldDef["type"], $listTypes ) )
                    $listTypes[ $aFieldDef["type"] ] = 0;
                ++$listTypes[ $aFieldDef["type"] ];
                
                if ( !array_key_exists( $aFieldDef["filename"], $listTemplates ) )
                    $listTemplates[ $aFieldDef["filename"] ] = 0;
                ++$listTemplates[ $aFieldDef["filename"] ];
            }
        }
        arsort( $listTypes );
        arsort( $listTemplates );

        $this->csvType     = array_key_first( $listTypes     );
        $this->csvTemplate = array_key_first( $listTemplates );
        
        echo "found:" . array_key_first($listTypes) . " - " . array_key_first($listTemplates) . "\n";

        return true;
    }
    //==============================================================================================================



    //==============================================================================================================    
    protected function findFieldDefinition( $id ):?array
    //--------------------------------------------------------------------------------------------------------------
    {
            if ( false === ($index = array_search( $id, array_column($this->fieldsDefinitions["fields"],"id") )))
                return false;
            return $this->fieldsDefinitions["fields"][$index];
    }
    //==============================================================================================================    




    //==============================================================================================================
    protected function createFieldsConfigFile( $field )
    //--------------------------------------------------------------------------------------------------------------
    // save to config file to all fileds in json
    //
    {
        $strJson  = "{'id':'{$this->hdrFields[ $field ]}',";
        $strJson .= "'name':'{$field}',";
        $strJson .= "'type':'" . (strpos( $this->filename, "survey") === false && strpos( $this->filename, "minor") === false ? "IRAP" : "ECS") . "',";

        $aPart    = explode( '/', $this->filename );
        $aPart    = explode( '.', $aPart[1]);

        $strJson .= "'filename':'{$aPart[0]}',";
        $strJson .= "'default':0, 'converted':false },\n";

        $fh = fopen("s:/munka/sabrina/data/csvheader.conf", "a");
        fwrite( $fh, $strJson );
        fclose($fh);
    }
    //==============================================================================================================



    //==============================================================================================================
    protected function analyseData( $line ):bool
    //--------------------------------------------------------------------------------------------------------------
    {
        return false;
    }
    //==============================================================================================================
}

