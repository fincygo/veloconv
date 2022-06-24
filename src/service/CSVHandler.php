<?php
namespace src\service;

/**
 *
 * @author petrics.lajos
 *        
 */
class CSVHandler
{
    /**
     * @var string
     */
    private $delimiter;
    
    /**
     * @var string
     */
    private $textwrapper;
    
    /**
     * @var string
     */
    private $defaultPath;

    /**
     */
    public function __construct(?string $defaultPath)
    {
        $this->defaultPath = $defaultPath;
    }
    
    public function loadFile(string $filename)
    {
        ;
    }
}

