<?php
namespace App\Service;

use App\Model\AbstractRecordSet;

/**
 *
 * @author petrics.lajos
 *        
 */
class SPointsOrObstacleRecordSet extends AbstractRecordSet
{

    /**
     */
    public function __construct(array $headers = null)
    {
        parent::__construct($headers);
    }
}

