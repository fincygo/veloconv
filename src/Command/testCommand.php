<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Service\CSVHandler;
USE App\Service\IRAPRecordSet;
USE App\Converter\ConvertProcess;


class testCommand extends Command
{
    protected static $defaultName = 'app:test';
    protected static $defaultDescription = "Test for anything";

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var ContainerBagInterface
     */
    protected $params;
    

    public function __construct(string $name = null, ContainerBagInterface $params, LoggerInterface $logger)
    { 
        $this->logger = $logger;
        $this->params = $params;
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $process = new ConvertProcess( $this->params );
        /*
        if ( ! $process->doConvert( "iRAP/irap-aggregated-export.csv", array() ) )
        {
            echo "ERROR: ".$process->getErrorMessage(). "\n";
        }
        */
        if ( ! $process->doConvert( "ECS/minor_sections.csv", array() ) )
        {
            echo "ERROR: ".$process->getErrorMessage(). "\n";
        }
        echo "---- end of converting ----\n\n\n";

        /*
        $handler = new CSVHandler( $this->params );

        if ( $handler->openCSVfile( "iRAP/irap-aggregated-export.csv" ) )
        {
            $IRAPrecords = new IRAPRecordSet( $handler->getHeaderInfo() );
            $handler->loadCSVDataToRecordset( $IRAPrecords );
            $handler->saveCSVFile(CSVHandler::CSVT_IRAP, "iRAP/irap-write-test.csv", $IRAPrecords );

            //echo "LOADED RECORDS ".count($IRAPrecords)."\n";
        } 
        */       
        //$handler->loadfile("iRAP/irap-aggregated-export.csv");
        //$handler->loadfile("ECS/minor_sections.csv");               
        //$handler->loadfile("ECS/surveys.csv");
        //$handler->loadfile("ECS/survey_points_crossing_or_obstacle.csv");
        //echo $handler->getLastError();

        /*
        $handler->loadfile("ECS/survey_points_accommodation_or_food.csv");
        $handler->loadfile("ECS/survey_points_attraction.csv");
        $handler->loadfile("ECS/survey_points_bike_services.csv");        
              
        $handler->loadfile("ECS/survey_points_rest_area.csv");
        $handler->loadfile("ECS/survey_points_signing.csv");
        $handler->loadfile("ECS/surveys.csv");
        */
        $output->writeln('end of test.');
        return Command::SUCCESS;
    }
}