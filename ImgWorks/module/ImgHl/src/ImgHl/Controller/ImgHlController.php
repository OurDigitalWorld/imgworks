<?php
/*
    ImgHl.php - utility functions for ODW image objects:
        a) use Lucene index to highlight terms
           on images
        b) extract region of image
        
        - art rhyno, ourdigitalworld

        (c) Copyright GNU General Public License (GPL)
*/

namespace ImgHl\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use ZendSearch\Lucene;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Index;
use ZendSearch\Lucene\Analysis\Analyzer;

class ImgHlController extends AbstractActionController {

    /*
        sortOutParams() - extract parameters from
            zend routes.
    */
    public function sortOutParams($config){
        $resLoc = $config['module_config']['asset_location'];

        return array("resLoc" => $config['module_config']['asset_location'],
            "site" => $this->params()->fromRoute('site', 'ink'),
            "collection" => $this->params()->fromRoute('collection', 'newspapers'),
            "container" => $this->params()->fromRoute('container', 'efp'),
            "reel" => $this->params()->fromRoute('reel', ''),
            "page" => $this->params()->fromRoute('page', ''));
    }//sortOutParams

    /*
        sortOutHlCoords() - pull coordinates from Lucene
            index. Zend Lucene support is at 2.x version.
    */
    public function sortOutHlCoords(){
        //Lucene operators
        $operators = array("and", "or", "not");

        $config = $this->getServiceLocator()->get('config');
        $paramInfo = $this->sortOutParams($config);

        //collect building blocks
        $resLoc = $paramInfo['resLoc'];
        $site = $paramInfo['site'];
        $collection = $paramInfo['collection'];
        $container = $paramInfo['container'];
        $reel = $paramInfo['reel'];
        $page = $paramInfo['page'];

        //the all important query
        $hl = $this->params()->fromRoute('hl', '');

        //coordinates to pass back
        $coords = [];

        //pass back empty coordinate set if any of these parameters
        //are missing
        if ($this->isNullOrEmpty($reel) || $this->isNullOrEmpty($page) || 
            $this->isNullOrEmpty($hl)) 
        {
            return array("imgloc" => '', "indloc" => '', 
                "coords" => $coords,);
        }//if

        //location of files - ODW file layout
        $resLoc .= ('/' . $site . '/' . $collection . '/' .
            $container . '/' . $reel . '/odw/' . $page . '/'); 
        $imgLoc = $resLoc . '../../' . $page . '.jpg';
        $iaLoc = $resLoc . 'ia/' . $page . '.jpg';

        //not all images will have IA derivative
        if (file_exists($iaLoc) !== false) {
            $imgLoc = $iaLoc;
        }
        $indLoc = $resLoc . 'index/imgworks';

        //get coordinates from Lucene index
        $searchText = '';
        //use Lucene tokens for searching
        $queryTokens = Analyzer\Analyzer::getDefault()->tokenize($hl);
        foreach ($queryTokens as $token) {
           $searchTerm = $token->getTermText();
           if (!in_array($searchTerm, $operators)){
               //no snowball analyzer or other stemming option
               //in Lucene 2.x, so create stem seperately
               $searchText .= stem_english($searchTerm);
               //Lucene dropped this limitation after 2.x 
               //but this version won't wildcard without
               //at least 3 characters in term
               if (strlen($searchTerm) >= 3) {
                   $searchText .= "* ";
               }//if strlen
           }//if
        }//foreach 

        //now do search
        $index = Lucene\Lucene::open($indLoc); 
        $searchResults = $index->find($searchText);

        //assemble results
        foreach ($searchResults as $searchResult) {
            array_push($coords,[
                $searchResult->x1, $searchResult->y1,
                $searchResult->x2, $searchResult->y2
            ]);
        }//foreach

        //pass back image and index location in addition to results
        return array("imgloc" => $imgLoc, "indloc" => $indLoc, 
            "coords" => $coords);
    }//sortOutHlCoords

    /*
        isNullOrEmpty() - simple function to weed out empty variables
    */
    public function isNullOrEmpty($str){
        return (!isset($str) || trim($str)==='');
    }//isNullOrEmpty

    /*
        adjSize() - coordinates may need to be adjusted between
            original images and derivative image used for search
            results.
    */
    public function adjSize($origNum,$adjNum,$imgNum) {
        return round(($origNum * $imgNum) / $adjNum);
    }//adjSize

    /*
        clusterHls() - identify portion of image with most terms
            (cluster) based on supplied size, there is probably an 
            argument to give prefrence to original terms over stems 
            but this treats everything equally for now.
    */
    public function clusterHls($hls,$clusterW,$clusterH,
        $imgLoc,$indLoc) 
    {

        //initialize
        $bestPnt = 0;
        $bestCluster = [];
        $cluster = [];
        $adjW = $clusterW;
        $adjH = $clusterH;
        $imgW = 0;
        $imgH = 0;
        $iaW = 0;
        $iaH = 0;

        //use existing file for getting size info of images
        //rather than dynamically calculating
        $handle = fopen($indLoc . "/info.txt", "r");
        if ($handle) {
            if (($line = fgets($handle)) !== false) {
                // process the line read.
                $nums = explode(" ",$line);
                $numlen = count($nums);
                if ($numlen >= 2) {
                    $imgW = intval($nums[0]);
                    $imgH = intval($nums[1]);
                    if ($numlen >= 4) {
                        $iaW = intval($nums[2]);
                        $iaH = intval($nums[3]);
                        $adjW = $this->adjSize($imgW,
                             $iaW,$clusterW); 
                        $adjH = $this->adjSize($imgH,
                             $iaH,$clusterH); 
                   }//if numlen >= 4
                }//if numleb >= 2
            }//if
           fclose($handle);
        }//if 

        //if no existing file for sizing, calculate against image
        //slight performance penalty
        if (!$handle || $imgW == 0 || $imgH == 0) {
           // get sizes from images
           $img = imagecreatefromjpeg($imgLoc);
           $imgW = imagesx($img);
           $imgH = imagesy($img);
        }//if 

        //at this point, if no image sizing is available, return empty cluster
        if ($imgW == 0 || $imgH == 0) {
            return array("imgw" => $imgW, "imgh" => $imgH,
                "iaw" => $iaW, "iah" => $iaH,
                "cluster" => []);
        }//if 

        //go through and determine biggest cluster based on image
        //request
        for ($i = 0; $i < count($hls); $i++) {
             $hl = $hls[$i];
             $leftPtX = $hl[0];
             $leftPtY = $hl[1];
             $inds = [];
             for ($j = $i+1; $j < count($hls); $j++) {
                  $hlcmp = $hls[$j];
                  $rightPtX = $hlcmp[2];
                  $rightPtY = $hlcmp[3];

                  //headlines can squeeze out everything else
                  //so we check size of term on image
                  $coordW = $rightPtX - $hlcmp[0];
                  $coordH = $rightPtY - $hlcmp[1];

                  //distances from hightlighted term
                  $xGap = abs($rightPtX - $leftPtX);
                  $yGap = abs($rightPtY - $leftPtY);

                  //can term fit in same window?
                  if ($xGap < $clusterW && $yGap < $clusterH &&
                      $coordW < $clusterW && $coordH < $clusterH) 
                  {
                      array_push($inds,$j);
                  }//if
             }//for j

             //keep running total of cluster points
             if (count($inds) > count($bestCluster)) {
                 $bestPnt = $i; 
                 $bestCluster = $inds;
             }//if
        }//for i

        //add original point to candidate cluster
        array_push($bestCluster,$bestPnt);

        //derivative image is typically smaller, so adjust
        //coordinates if derivative exists,  otherwise, use
        //original coordinates
        foreach ($bestCluster as $ind) {
            $adjCluster = $hls[$ind];
            if ($iaW > 0 || $iaH > 0) {
                  //adjust coordinates here
                  $adjCluster[0] = 
                      $this->adjSize($adjCluster[0],$imgW,$iaW);
                  $adjCluster[1] = 
                      $this->adjSize($adjCluster[1],$imgH,$iaH);
                  $adjCluster[2] = 
                      $this->adjSize($adjCluster[2],$imgW,$iaW);
                  $adjCluster[3] = 
                      $this->adjSize($adjCluster[3],$imgH,$iaH);
             }//if
             array_push($cluster,$adjCluster);
        }//foreach
            
        return array("imgw" => $imgW, "imgh" => $imgH,
            "iaw" => $iaW, "iah" => $iaH,
            "cluster" => $cluster);
    }//clusterHls

    /*
        sortOutImg() - using image parameters, place highlights 
            on requested portion of image
    */
    public function sortOutImg($imgLoc,$imgInfo,$w,$h)
    {
        //get required parameters
        $imgW = $imgInfo["imgw"];
        $imgH = $imgInfo["imgh"];
        $iaW = $imgInfo["iaw"];
        $iaH = $imgInfo["iah"];

        if ($iaW > 0 || $iaH > 0) {
            $imgW = $iaW;
            $imgH = $iaH;
        }//if

        //the all important cluster
        $cluster = $imgInfo["cluster"];
        
        $x1 = $y1 = $x2 = $y2 = 0;

        //find farthest left & right points first
        foreach ($cluster as $coords) {
            if ($coords[0] < $x1 || $x1 == 0) {
                $x1 = $coords[0];
            }//if
            if ($coords[1] < $y1 || $y1 == 0) {
                $y1 = $coords[1];
            }//if
            if ($coords[2] > $x2 || $x2 == 0) {
                $x2 = $coords[2];
            }//if
            if ($coords[3] > $y2 || $y2 == 0) {
                $y2 = $coords[3];
            }//if
        }//foreach

	//calculate shift values to try to center cluster
        $xShift = round(($w - ($x2 - $x1))/2);
	$yShift = round(($h - ($y2 - $y1))/2);

        //apply shift to furthest left hand coordinates
	$x1 -= $xShift;
	$y1 -= $yShift;
	$x1 = abs($x1);
	$y1 = abs($y1);

        //make sure there is enough room, not sure this would
        //happen but just in case
        if (($x1 + $w) > $imgW) {
            $x1 = $imgW - $w;
        }//if

        if (($y1 + $h) > $imgH) {
            $y1 = $imgH - $h;
        }//if

        //most expensive call - need image to extract portion from
        $img = imagecreatefromjpeg($imgLoc);

        //create portion for highlights
        $dest = imagecreatetruecolor($w,$h);
        imagecopy($dest, $img, 0, 0, $x1, $y1, $w, $h);
        imagedestroy($img); //free up memory
        //define blue for highlights
        $blue = imagecolorallocatealpha($dest, 0, 0, 255, 75);

        //go through and highlight rectangles for terms
        foreach ($cluster as $coords) {
            $hlX1 = $coords[0];
            $hlY1 = $coords[1];
            //change numbers to reflect sizing 
            $hlX2 = $coords[2] - $x1;
            $hlY2 = $coords[3] - $y1;

            $hlX1 -= $x1; 
            $hlY1 -= $y1; 
            imagefilledrectangle($dest, $hlX1, $hlY1, $hlX2, $hlY2, $blue);
        }//foreach

        //pass back resulting image
        return $dest;
    }//sortOutImg

    /*
        indexAction() - default action, show fallback image
    */
    public function indexAction()
    {
        $config = $this->getServiceLocator()->get('config');
        $fbLoc = $config['module_config']['fallback_location'];
        $fbContent = @file_get_contents($fbLoc);
         
        $response = $this->getResponse();

        //seemingly most standard way to deliver image content in zend    
        $response->setContent($fbContent);
        $response
            ->getHeaders()
            ->addHeaderLine('Content-Transfer-Encoding', 'binary')
            ->addHeaderLine('Content-Type', 'image/jpeg')
            ->addHeaderLine('Content-Length', mb_strlen($fbContent));

        return $response;
    }//indexAction

    /*
        imgAction() - add highlights to images
    */
    public function imgAction()
    {

        $config = $this->getServiceLocator()->get('config');
        $fbLoc = $config['module_config']['fallback_location'];

        $w = intval($this->params()->fromRoute('w', '0'));
        $h = intval($this->params()->fromRoute('h', '0'));

        if ($w <= 0 || $h <= 0) {
            $imgResponse = imagecreatefromjpeg($fbLoc);
        } else { 
            $hlInfo = $this->sortOutHlCoords();
            $imgLoc = $hlInfo["imgloc"];
            $indLoc = $hlInfo["indloc"];
            $hlCoords = $hlInfo["coords"];

            $imgCoords = [];
            if (count($hlCoords) > 0) {
                $imgInfo = $this->clusterHls($hlCoords,$w,$h,
                    $imgLoc,$indLoc);
                $imgCoords = $imgInfo["cluster"];
                if (count($imgCoords) > 0) {
                    $imgResponse = $this->sortOutImg($imgLoc,
                        $imgInfo,$w,$h);
                }//if
            }//if
        }//if $w

        //if still no image results, use fallback
        if (!isset($imgResponse)) {
            $imgResponse = imagecreatefromjpeg($fbLoc);
        }//if

        //disable view and layout - this doesn't seem to be 
        //necessary.
        //Zend_Layout::getMvcInstance()->disableLayout(); 
        //$this->_helper->viewRenderer->setNoRender(); 

        //set headers 
        header("Content-Type: image/jpeg"); 
        //could also adjust quality here
        imagejpeg($imgResponse);
        // free memory used 
        imagedestroy($imgResponse); 
    }//imgAction

    /*
        jsonAction() - return coordinate info in json
    */
    public function jsonAction()
    {
        //at some point, may want coordinates for specific portion
        $w = intval($this->params()->fromRoute('w', '0'));
        $h = intval($this->params()->fromRoute('h', '0'));

        $hlCoords = $this->sortOutHlCoords();
        $jsonResponse = json_encode($hlCoords);

        $response = $this->getResponse();
        $response->setContent($jsonResponse);
        $response
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'application/text')
            ->addHeaderLine('Content-Length', strlen($jsonResponse));

        return $response;
    }//jsonAction

    /*
        cutAction() - extract region of image
    */
    public function cutAction()
    {

        $x = $this->params()->fromQuery('x',0);
        $y = $this->params()->fromQuery('y',0);

        $config = $this->getServiceLocator()->get('config');
        $paramInfo = $this->sortOutParams($config);
        $fbLoc = $config['module_config']['fallback_location'];

        $w = intval($this->params()->fromRoute('w', '0'));
        $h = intval($this->params()->fromRoute('h', '0'));

        //collect building blocks
        $resLoc = $paramInfo['resLoc'];
        $site = $paramInfo['site'];
        $collection = $paramInfo['collection'];
        $container = $paramInfo['container'];
        $reel = $paramInfo['reel'];
        $page = $paramInfo['page'];

        //pass back empty coordinate set if any of these parameters
        //are missing
        if ($this->isNullOrEmpty($reel) || $this->isNullOrEmpty($page) ||
            $w <= 0 || $h <= 0)
        {
            $imgResponse = imagecreatefromjpeg($fbLoc);
        } else {
            //location of files - ODW file layout
            $resLoc .= ('/' . $site . '/' . $collection . '/' .
                $container . '/' . $reel . '/odw/' . $page . '/'); 
            $imgLoc = $resLoc . '../../' . $page . '.jpg';
            $iaLoc = $resLoc . 'ia/' . $page . '.jpg';
            //not all images will have IA derivative
            if (file_exists($iaLoc) !== false) {
                $imgLoc = $iaLoc;
            }

            $img = imagecreatefromjpeg($imgLoc);

            //create portion for highlights
            $imgResponse = imagecreatetruecolor($w,$h);
            imagecopy($imgResponse, $img, 0, 0, $x, $y, $w, $h);
            imagedestroy($img); //free up memory

            //set headers 
            header("Content-Type: image/jpeg"); 
            //could also adjust quality here
            imagejpeg($imgResponse);
            // free memory used 
            imagedestroy($imgResponse); 
        }//if
    }//cutAction

    /*
        testAction() - use for trying out different approaches
            as well as timing
    */
    public function testAction()
    {
        //timing example
        $timeStart = microtime(true);

        //html output for tests
        $testText = '<pre>test</pre>';

        $response = $this->getResponse();
        $response->setContent($testText);
        $response
            ->getHeaders()
            ->addHeaderLine('Content-Type', 'text/html')
            ->addHeaderLine('Content-Length', strlen($testText));

        return $response;
    }//testAction
}
