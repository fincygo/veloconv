<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use App\Converter\IrapToEcsConverter;
use App\Converter\EcsToIrapConverter;
use App\Converter\ConvertProcess;

class SabrinaVeloconvCommand extends Command
{
    protected static $defaultName = 'sabrina:veloconv';
    protected static $defaultDescription = "The veloconv tool can carry out the following two conversions:
\tfrom an ECS minorsections.csv file to an iRAP file;
\tfrom an iRAP file to an ECS surveys.csv, a survey_points_crossing_or_obstacle.csv and a minorsections.csv files;
  The conversion changes the geometry, i.e modifies the length of the centerline and the attributes. 
";

    /**
     * @var ContainerBagInterface
     */
    private $params;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    public function __construct(string $name = null, ContainerBagInterface $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;
        
        parent::__construct($name);
    }
    
    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'IRAP csv file or ECS minor_sections csv file with path.')
            ->addOption('min', 'm', InputOption::VALUE_REQUIRED, 'Minimum length of ECS segments in metres, default value m=200.')
            ->addOption('max', 'x', InputOption::VALUE_REQUIRED, 'Maximum length of ECS segments in metres, default value x=200.')
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Value is i2e or e2i, Defines the direction of the conversion')
            ->addOption('height', 'z', InputOption::VALUE_REQUIRED, 'Average height to be used as “z” in linestring during conversion to ECS.')
            ->addOption('pgen', 'p', InputOption::VALUE_REQUIRED, 'Parameter for generalisation of route lines, p means the maximum divergence from the original polygone. Default value is 1 metre, if p=0 then no generalisation, all segments will be converted to the polyline.')
            ->addOption('slen', 's', InputOption::VALUE_REQUIRED, 'Length of iRAP segments in metres, default is s=100.')
            ->addOption('id', 'i', InputOption::VALUE_REQUIRED, 'ID in survey table, default is i=1')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');
        
        $min = $input->getOption('min');
        $max = $input->getOption('max');
        $height = $input->getOption('height');
        $pgen = $input->getOption('pgen');
        $slen = $input->getOption('slen');
        $id = $input->getOption('id');

        // This option parameter unused yet
        $dir = $input->getOption('dir');
        
        $params = array();
        if (null !== $min) {
            $params[IrapToEcsConverter::NAME_MINLENGTH] = $min;
        }
        if (null !== $max) {
            $params[IrapToEcsConverter::NAME_MAXLENGTH] = $max;
        }
        if (null !== $height) {
            $params[IrapToEcsConverter::NAME_AVGHEIGHT] = $height;
        }
        if (null !== $pgen) {
            $params[IrapToEcsConverter::NAME_MAXDIVERGENCE] = $pgen;
        }
        if (null !== $slen) {
            $params[EcsToIrapConverter::NAME_SEGMENTLENGTH] = $slen;
        }
        if (null !== $id) {
            $params[IrapToEcsConverter::NAME_SURVEYID] = $id;
        }
        
        $process = new ConvertProcess( $this->params, $this->logger );
        
        if ( ! $process->doConvert( $file, $params ) )
        {
            $io->error("ERROR: " . $process->getErrorMessage());
            return Command::FAILURE;        }

        $io->success('The conversion is successfully ended.');

        return Command::SUCCESS;
    }
}
