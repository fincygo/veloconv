<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use App\Converter\IrapToEcsConverter;
use App\Converter\EcsToIrapConverter;
use App\Converter\ConvertProcess;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @author petrics.lajos
 *        
 */
class VeloconvController extends AbstractController
{
    /**
     * @Route("/veloconv", name="veloconv", methods={"POST","GET"})
     */
    public function veloconv(Request $request, ContainerBagInterface $params, LoggerInterface $logger) {
        $payload = $request->getContent();
        $dataObj = json_decode($payload);
        
        if (null === $dataObj) {
            throw $this->createAccessDeniedException("Access Denied. False input.") ;
        }
        
        $options = array();
        if (!isset($dataObj->file)) {
            throw $this->createAccessDeniedException("Access Denied. False input.") ;
        }
        if (isset($dataObj->options)) {
            if (isset($dataObj->options->m)) {
                $options[IrapToEcsConverter::NAME_MINLENGTH] = $dataObj->options->m;
            }
            if (null !== $dataObj->options->x) {
                $options[IrapToEcsConverter::NAME_MAXLENGTH] = $dataObj->options->x;
            }
            if (null !== $dataObj->options->z) {
                $options[IrapToEcsConverter::NAME_AVGHEIGHT] = $dataObj->options->z;
            }
            if (null !== $dataObj->options->p) {
                $options[IrapToEcsConverter::NAME_MAXDIVERGENCE] = $dataObj->options->p;
            }
            if (null !== $dataObj->options->s) {
                $options[EcsToIrapConverter::NAME_SEGMENTLENGTH] = $dataObj->options->s;
            }
            if (null !== $dataObj->options->i) {
                $options[IrapToEcsConverter::NAME_SURVEYID] = $dataObj->options->i;
            }
        }
        
        $result = array("result"=>true, "message"=>"OK");
        $process = new ConvertProcess( $params, $logger );
        
        if ( ! $process->doConvert( $dataObj->file, $options ) )
        {
            $result["result"] = false;
            $result["message"] = "ERROR: " . $process->getErrorMessage();
        }
        
        return new JsonResponse($result);
        
    }
}

