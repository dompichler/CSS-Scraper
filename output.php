
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Chrome\ChromeProcess;

$process = (new ChromeProcess(__DIR__ . '/chromedriver'))->toProcess();
$process->start();

$options = (new ChromeOptions)->addArguments(['--disable-gpu', '--headless']);

$capabilities = DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options);

$driver = retry(5, function () use($capabilities) {
	return RemoteWebDriver::create('http://localhost:9515', $capabilities);
}, 50);


$browser = new Browser($driver);

/* =============== GENERAL SETTINGS & CONFIGURATIONS=============== */

	
	$userName = $_POST['userUrl'];
	if(strval($userName) == ""){ $userName ="Webnique";}
	
	$userURL = "https://www.webnique.de/";
	$userCheckBox = 'on'; //If var is set "on", all subpages will be included by default!

	$elementtype =   ['h1','h2','h3','h4','h5','h6','p', '*'];
	$cssProperty = ['fontFamily','color','font-weight'];


/* =============== Process User-Input  =============== */
//TODO: Testing HostCode Extraction
$userURLToCheck = $_POST['userUrl'];

// ------ URL-Type: www.* ------ (Needs to be casted to the Typ below, to avoid an error)
	if(substr( $userURLToCheck, 0, 4 ) === "www.") {
	
	//Generating Hostcode (needed to crawl all subpages)
		$search = 'www' ;
		$trimmed = str_replace($search, '', $userURLToCheck) ;
		$hostCode = substr($trimmed, 0, strpos($trimmed, "."));
	
	//Casting URL
		$userURLToCheck = 'https://' . $userURLToCheck;
		


// ------ URL-Type: https.* ------ (No cast needed)
	} else if ((substr( $userURLToCheck, 0, 5 ) == "https")){
		
		
		//Generating Hostcode (needed to crawl all subpages)
		
		$search = 'https://' ;
		$trimmed = str_replace($search, '', $userURLToCheck) ;
		$hostCode = substr($trimmed, 0, strpos($trimmed, "."));
		
		
		
// ------ URL-Type: http.* ------ (No cast needed)
	} else if ((substr( $userURLToCheck, 0, 4 ) == "http")){
		
		//Generating Hostcode (needed to crawl all subpages)
		$search = 'http://' ;
		$trimmed = str_replace($search, '', $userURLToCheck) ;
		$hostCode = substr($trimmed, 0, strpos($trimmed, "."));
		
	}
	
// ------ No valid URL-Type  - User will be sent to Error page ----
//TODO: Frühzeitige (ON-PAGE)- Erkennung einbauen um erneute Eingabe zu erlauben
	else {
		echo "<script> location.href='404.html'; </script>";
		exit;
		}
	
	
	$host = $userURLToCheck;


	
/*
//Extracting a hostcode out of the UserURL to identify all subpages that belong to the userURL
	$subject = strval($userURL);
	if(substr( $subject, 0, 4 ) === "http") {
		$search = 'https://' ;
		$trimmed = str_replace($search, '', $subject) ;
		$hostCode = substr($trimmed, 0, strpos($trimmed, "."));
	
	} else if (substr( $subject, 0, 3 ) === "www") {
		$search = 'www.' ;
		$trimmed = str_replace($search, '', $subject) ;
		$hostCode = substr($trimmed, 0, strpos($trimmed, "."));
	
	} else {
		$hostCode  = $subject;
	}

*/


/* =============== DEBUG-SETTINGS ==================

$hostCode='webnique';
$host = $userURL;

*/


/* =============== Crawling all Subpages ==================
//TODO: Crawling Testen und optimieren
1) Check if selected URL has a sitemap.
2) If that's not the case, Subpages will be crawled.
*/


	// ------- Checks whether the site provides a Sitemap -------
		$checkForFiles = array('sitemap.xml');
		foreach($checkForFiles as $file){
			$url = $host.$file;
			$ch = curl_init ($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			
			$output = curl_exec ($ch);
		
		
		if(curl_getinfo($ch)['http_code'] = 200){
			$urlSitemap = $host . $file;
			
			

	// ------- Website provides a sitemap. Subpages can  be extracted from the sitemap -------
			$browser->visit($urlSitemap);
			$sitemapLinks = array();
			$subsitemaps = array ();
			$subpages = array ();
			
			$sitemapHTML = $browser->script('return document.body.innerHTML') [0];
			//print_r($sitemapHTML);
			
			
		//Sitemap will be converted into an array of URLS
			$dom = new DOMDocument;
			$dom->loadHTML($sitemapHTML);
			
			foreach ($dom->getElementsByTagName('a') as $node)
			{
				if(strpos($node->getAttribute("href"), $hostCode)){
					
					//print_r("Href gefunden! ");
					if(strpos($node->getAttribute("href"), 'xml')) {
						//Node is also a sitemap. We need to convert the Child-Submap into URL's as well
						
						array_push($subsitemaps,$node->getAttribute("href"));
						
						//print_r('Subsitemap added: '.$node->getAttribute("href")."\n");
						
					} else {
						//Node is a (real) subpage
						array_push($subpages,$node->getAttribute("href"));
						//print_r('Subpage added');
					}
				}
			}
			//print_r('Subpages: ' .sizeof($subpages)."\n");
			//print_r('SubSitemaps: '.sizeof($subsitemaps)."\n");
			
		//Subpages will be extracted from the Subsitemaps
			foreach($subsitemaps as $submap){
				$browser->visit($submap);
				$html = $browser->script('return document.body.innerHTML') [0];
				
				$dom = new DOMDocument;
				$dom->loadHTML($html);
				foreach ($dom->getElementsByTagName('a') as $node)
				{
					if(strpos($node->getAttribute("href"),$hostCode)){
					   array_push($subpages,$node->getAttribute("href"));
					}
				}
			
			}
			
		}  else {
		
	// ------- Site does not provide a sitemap. All Subpages have to be crawled manually -------
			
			//TODO: Rekursive Unterseiten durchsuchen
			$urlsCrawled  = $browser->visit($host);
			$hostSubpages = $browser->script( file_get_contents( 'getSubpages.js' )) [0];
			//print_r($hostSubpages);
			
		}
		curl_close($ch);
	}
		
		

/* =============== GET UNIQUE CSS-ELEMENTS =============== */

	//Debug-Thing: Setting $userCheckBox to "off" excludes the styles of all subpages.
		if($userCheckBox == "on"){
			$urls = $subpages;
			$userUrls = [$userURL,];
		} else {
			$urls = array ();
			array_push($urls,$host);
		};



/* ===============  Output Variables  ===============

- All Information, seperately accessable for each URL can found in: data =  $urlCollection[URL][HTML-TAG][CSS-Property]
- All Information, regardless of the subpages URL's can be found in $elementCollectionEP[HTML-TAG][CSS-Property]

--------- Example: ---------

$elementCollectionEP['h2']['fontFamily'] = array_unique($elementCollectionEP['h2']['fontFamily']);
	for($i = 0;  $i < sizeof($elementCollectionEP['h2']['fontFamily']); $i++){
		if(strval($elementCollectionEP['h2']['fontFamily'][$i]) != "" ){
			print_r("Schriftart_0[".$i."] ".$elementCollectionEP['h2']['fontFamily'][$i]."\n");
		}
}

*/

	$urlCollection = array (); //
	$elementCollection = array();
	
	
//Variable Template
	$elementCollectionEP = $elementtype; //Element Collection for the entire page,.


	foreach ($elementtype as  $element){
		$elementCollectionEP[$element] = $cssProperty;
		foreach ($cssProperty as $pro){
			$elementCollectionEP[$element][$pro] = array ();
		}
	}
	

foreach ($urls as $url) {
	$browser->visit($url);
	$elementCollection = [];
	foreach ($elementtype as $element){
		$cssProperties = [];
		foreach ($cssProperty as $prop){
			
			//TODO: Font-Families mit file_get_contents richtig encodieren!
			$cssProperties[$prop] = $browser->script(file_get_contents('styleInFile.js' )."return styleInPage('$prop','$element');") [0];
			//print_r("CSS: ".$cssProperties[$prop][0]."\n");
		
			foreach ($cssProperties[$prop] as $cssAttribute) {
				if(strval($cssAttribute) != ""){
					array_push($elementCollectionEP[$element][$prop],$cssAttribute);
				}
			}
		}
		$elementCollection[$element] = $cssProperties;
		
		//$elementCollectionEP[$element] = $cssProperties;
		//array_push($elementCollectionEP[$element],$cssProperties);
	}
	array_push($urlCollection,$elementCollection);
	
	
}
	$browser->quit();
	$process->stop();
?>



<!-- ===============  HTML OUTPUT  =============== -->


<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="stylesheet" href="style.css">
	
	
	<? echo"<title>Styleguide</title>"?>
</head>
<body>
<div class = "container">
	<div class="container_heading">
		<? echo"<h1>".$userName."- Styleguide</h1>"?>
	</div>
	
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> TYPOGRAPHIE</p>
		</div>
		<div class="labelbar">
			
			<?
			for ($j = 0; $j < 6; $j++) {
				
				echo "<div class='headingBox'>";
					$elementCollectionEP[$elementtype[$j]]['fontFamily'] = array_unique($elementCollectionEP[$elementtype[$j]]['fontFamily']);
					for($i = 0;  $i < sizeof($elementCollectionEP[$elementtype[$j]]['fontFamily']); $i++){
						if(strval($elementCollectionEP[$elementtype[$j]]['fontFamily'][$i] != "")){
							echo "<".$elementtype[$j].">"."H".($j + 1).":</".$elementtype[$j].">";
						}
					}
				echo "</div>";
			}
			
				$elementCollectionEP['p']['fontFamily'] = array_unique($elementCollectionEP['p']['fontFamily']);
				for($i = 0;  $i < sizeof($elementCollectionEP['p']['fontFamily']); $i++){
					if(strval($elementCollectionEP['p']['fontFamily'][$i] != "")){
						echo "<p>"."BODY ".":</p>";
					}
				}
			?>
			<br>
		</div>
		
		<div class="mainWindow">
			
			<?
			for ( $j = 0; $j < 6; $j ++ ) {
				echo "<div class='headingBox'>";
					$elementCollectionEP[ $elementtype[$j] ]['fontFamily'] = array_unique( $elementCollectionEP[ $elementtype[ $j ] ]['fontFamily'] );
					for ( $i = 0; $i < sizeof( $elementCollectionEP[ $elementtype[$j]]['fontFamily'] ); $i ++ ) {
						if ( strval( $elementCollectionEP[ $elementtype[ $j ] ]['fontFamily'][$i] != "" ) ) {
							echo "<".$elementtype[$j]."> HEADER  ".ucwords(strtolower($elementCollectionEP['h1']['fontFamily'][$i]), '_')." -  SIZE (TODO px):</".$elementtype[$j].">";
							//echo "<".$elementtype[$j]."> HEADER - ".$elementCollectionEP['h1']['fontFamily'][$i]." -  SIZE (TODO px):</".$elementtype[$j].">";
						}
					}
				echo "</div>";
			}
			
			
			$elementCollectionEP['p']['fontFamily'] = array_unique($elementCollectionEP['p']['fontFamily']);
			
			for ( $i = 0; $i < sizeof( $elementCollectionEP['p']['fontFamily'] ); $i ++ ) {
				if (strval( $elementCollectionEP['p']['fontFamily'][$i] != "" ) ) {
					echo "<p>Font: ".$elementCollectionEP['p']['fontFamily'][$i]."<br> "."Of all of the celestial bodies that capture our attention and fascination as astronomers, none has a greater influence on life on planet Earth than it’s own satellite, the moon. When you think about it, we regard the moon with such powerful significance that unlike the moons of other planets which we give names, we only refer to our one and only orbiting orb as THE moon. It is not a moon. To us, it is the one and only moon.</p>";
				}
			}
			?>
			
			<br>
		
		</div>
	</div>
	
	
	
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> COLOR </p>
		</div>
		<div class="labelbar">
			<p> PRIMARY</p>
		</div>
		
		<div class ="mainWindowColors">
		<?
		
		//TODO: REMOVE WHITE COLORS:
		//TODO: MAX CONTRAST TEXT-COLORS
		
		$elementCollectionEP['*']['color'] = array_unique($elementCollectionEP['*']['color']);
		
		for($i=0; $i < sizeof( $elementCollectionEP['*']['color'] ); $i = $i+3){
			
			echo "
            <div class=\"colorBlock1\" style='background-color: ".$elementCollectionEP['*']['color'][$i]."!important'>
				<p class=\"colorLabel\" >".$elementCollectionEP['*']['color'][$i]."</p>
			</div>
			
			<div class=\"colorBlock2\" style='background-color: ".$elementCollectionEP['*']['color'][$i+1]."!important'>
				<p class=\"colorLabel\" >".$elementCollectionEP['*']['color'][$i+1]."</p>
			</div>
			
			<div class=\"colorBlock3\" style='background-color: ".$elementCollectionEP['*']['color'][$i+2]."!important'>
				<p class=\"colorLabel\" >".$elementCollectionEP['*']['color'][$i+2]."</p>
			</div>
			
			";
			
			//TODO: AUftei
			if(sizeof( $elementCollectionEP['*']['color']) - $i == 3){
			//Two colors remaining from the previous loop
				echo "
				 <div class=\"colorBlock1\" style='background-color: ".$elementCollectionEP['*']['color'][$i+1]."!important'>
					<p class=\"colorLabel\" >".$elementCollectionEP['*']['color'][$i+1]."</p>
				</div>
			
			<div class=\"colorBlock2\"  style='background-color: ".$elementCollectionEP['*']['color'][$i+2]."!important'>
				<p class=\"colorLabel\">".$elementCollectionEP['*']['color'][$i+2]."</p>
			</div>
				";
				
				
			} else if (sizeof( $elementCollectionEP['*']['color']) - $i == 2){
			//One Color remaining from the previous loop
				
				echo "
				<div class=\"colorBlock1\" style='background-color: ".$elementCollectionEP['*']['color'][$i+1]."!important'>
					<p class=\"colorLabel\" >".$elementCollectionEP['*']['color'][$i+1]."</p>
				</div>
				
				";
				
			}
		
		}
		
		?>
		
	</div>
		
		
		
		<div class="labelbar">
			<p> SECONDARY</p>
		</div>
		
		<div class="colorBlockSC1">
			<p class="colorLabelSC" style="color: white"> #cdcdcd </p>
		</div>
		<div class="colorBlockSC2">
			<p class="colorLabelSC" style="color: white;"> #f2d3ee </p>
		</div>
		<div class="colorBlockSC3">
			<p class="colorLabelSC" style="color: gray"> #ab23ac </p>
		</div>
		
		<div class="colorBlockSC4">
			<p class="colorLabelSC" style="color: white"> #cdcdcd </p>
		</div>
		<div class="colorBlockSC5">
			<p class="colorLabelSC" style="color: white;"> #f2d3ee </p>
		</div>
		<div class="colorBlockSC6">
			<p class="colorLabelSC" style="color: gray"> #ab23ac </p>
		
		</div>
	
	</div>
	
	
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> BUTTONS </p>
		</div>
		<div id="buttonBlock1">
			<div class="buttonText">
				<b>Primary Button</b>
				<p class="buttonText">Font: Source Sans Pro Semi Bold <br>
				                      Size: 13px<br>
				                      Line Height Box: 55px<br>
				                      Weight: 700<br>
				                      Spacing: 0,6<br>
			</div>
		</div>
		
		
		<div id="buttonBlock2">
			<button class="btn_primary">PRIMARY</button><br>
			<button class="btn_primary--pressed">PRESSED</button><br>
			<button class="btn_primary--disabled">DISABLED</button><br>
		
		</div>
	
	
	
	</div>
	
	<div>
		<button class="btn" value ="test" onclick=""> Download</button>
		<button class="btn" value ="test" onclick="hideElement()"> Durchsuchte Seiten anzeigen</button>
		<button class="btn" value ="test" onclick="hideOldVersion()"> Alte Version anzeigen</button>
		
		<div id="urlList">
			<? foreach ($subpages as $pages){
				echo "<div>".$pages."</div>";
			}
			?>
		</div>
	
	
	</div>
	
	
	
	<!--
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> ICONGRAPHY </p>
		
		</div>
	</div>
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> INPUT FIELD </p>
		</div>
	</div>
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> LIST TYPO </p>
		
		</div>
	
	</div>
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> PRICING | <br> NUMBERS </p>
		
		</div>
	</div>
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> LOGO </p>
		
		</div>
	
	
	
	
	</div>
-->
</div>

<!-- ALTE VERSION -->

<div class="container" id="oldVersion">

	
<?
	$urlCounter = 0;
	$htmlCounter = 0;
	$cssCounter = 0;
	$elementCounter = 0;

// ------- Display all CI Information in a simple table -------
	foreach ($urlCollection as $url){
		
		//echo "<div class=\"wrapper\">"."\n";
		echo "\t"."<h1 class=\"urlHeading\">".$urls[$urlCounter]."</h1>"."\n";
		
		foreach ($url as $htmlElements){
			echo "\t\t"."<div><h3 class=\"elementHeading\">Element: ".$elementtype[$htmlCounter]."</h3>"."\n";
			
			echo "<div class =\" parent\">";
			foreach ($htmlElements as $cssProperties){
				
				echo "\t\t\t"."<div class=\"cssHeading child\"><h3>".$cssProperty[$cssCounter]."</h3>"."\n";
					foreach ($cssProperties as $cssElement){
					//TODO: Change Color for best Visability:
						// https://stackoverflow.com/questions/11867545/change-text-color-based-on-brightness-of-the-covered-background-area
						
						if( $cssProperty[$cssCounter] == 'color'){
							echo "\t\t\t\t"."<div style=\"background-color:".$cssElement."\" class=\"cssProperty\">".$cssElement."</div>"."\n";
						} else {
							
							echo "\t\t\t\t"."<div class=\"cssProperty\">".$cssElement."</div>"."\n";
						}
						$elementCounter++;
					}
					echo "\t\t\t"."</div>"."\n";
				$cssCounter++;
			}
			echo "</div>";
			$htmlCounter++;
			$cssCounter = 0;
			echo "\t\t"."</div>"."\n";
		}
		$urlCounter++;
		$htmlCounter = 0;
		//	echo "\t"."</div>"."\n";
	}
?>
</body>

<script>
  function hideElement() {
    var temp = document.getElementById("urlList");
    if (temp.style.display === "none") {
      temp.style.display = "block";
    } else {
      temp.style.display = "none";
    }
  }

  function hideOldVersion() {
    var temp = document.getElementById("oldVersion");
    if (temp.style.display === "none") {
      temp.style.display = "block";
    } else {
      temp.style.display = "none";
    }
  }

</script>


</html>