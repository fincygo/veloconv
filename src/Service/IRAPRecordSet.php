<?php
namespace App\Service;

use App\Model\AbstractRecordSet;

/**
 *
 * @author petrics.lajos
 *        
 */
class IRAPRecordSet extends AbstractRecordSet
{

    /**
     */
    public function __construct(?array $headers)
    {
        parent::__construct($headers);
    }
}

