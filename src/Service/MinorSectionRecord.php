<?php
namespace App\Service;

use App\Model\AbstractRecord;

/**
 *
 * @author petrics.lajos
 *        
 */
class MinorSectionRecord extends AbstractRecord
{
    /**
     * @var array
     */
    private $record;

    
    /**
     */
    public function __construct(array $fields)
    {
        parent::__construct($fields);
    }
}

