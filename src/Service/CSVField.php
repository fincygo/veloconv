<?php
namespace App\Service;

class CSVField
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var array
     */
    private $data;


    public function __construct( int $nID, array $data )
    {
        $this->id   = $nID;
        $this->data = $data;        
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    } 

    /**
     * @param string
     */
    public function setId($id)
    {
        $this->id = $id;
    }    

    /**
     * @return string
     */
    public function getCanonical()
    {
        return $this->data["id"];
    } 

    /**
     * @param string
     */
    public function setCanoncical($canonical)
    {
        $this->data["id"] = $canonical;
    }    

    /**
     * @return string
     */
    public function getName()
    {
        return $this->data["name"];
    } 

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->data["name"] = $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->data["type"];
    } 

    /**
     * @param string
     */
    public function setType($type)
    {
        $this->data["type"] = $type;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->data["filename"];
    } 

    /**
     * @param string
     */
    public function setFileName($filename)
    {
        $this->data["filename"] = $filename;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->data["default"];
    } 

    /**
     * @param mixed $default
     */
    public function setDefault($default)
    {
        $this->data["default"] = $default;
    }
   
   /**
     * @return bool
     */
    public function getConvert()
    {
        return $this->data["converted"];
    } 

    /**
     * @param bool $convert
     */
    public function setConvert($convert)
    {
        $this->data["converted"] = $convert;
    }
 
   /**
     * @return int
     */
    public function getFileId()
    {
        return ( array_key_exists( "fileid", $this->data ) ? $this->data["fileid"] : -1 );
    } 

    /**
     * @param int
     */
    public function setFileId($fileid)
    {
        $this->data["fileid"] = $fileid;
    }

    public function getDataType()
    {
        return ( array_key_exists( "datatype", $this->data ) ? $this->data["datatype"] : "unknown" );       
    }

    /**
     * @param string
     */
    public function setDataType( $dtype )
    {
        $this->data["datatype"] = $dtype;       
    }

    public function getFormat()
    {
        return ( array_key_exists( "format", $this->data ) ? $this->data["format"] : "%s" );       
    }

    /**
     * @param string
     */
    public function setFormat( $format )
    {
        $this->data["format"] = $format;       
    }

}