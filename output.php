
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



	
	$userName = $_POST['userUrl'];
	if(strval($userName) == ""){ $userName ="Webnique";}
	
	$userURL = "http://www.webnique.de/";
	$userHTML = $_POST['userHtml'];
	$userCSS = $_POST['userCss'];
	$userCheckBox = 'off'; //All subpages will be included by default!

	$elementtype =   ['h1','h2','h3','h4','h5','h6','p'];
	$cssProperty = ['fontFamily','color','font-weight'];







/* =============== Process User-Input  =============== */
	$userURLToCheck = $_POST['userUrl'];



//TODO: Besseres Errorhandling


	// ------ URL-Type: www.* ------ (Needs to be casted to the Typ below, to avoid an error)
	if(substr( $userURLToCheck, 0, 4 ) === "www.") {
	//$userURL = 'https://' . $userURL;
	
	// ------ Everything else: not valid
	} else if ((substr( $userURLToCheck, 0, 5 ) !== "https")){
		echo "<script> location.href='404.html'; </script>";
		exit;
	}

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
	
$host = $userURL;
	
	



/* =============== Crawling all Subpages ================== */
/*
1) Checking if selected URL has a sitemap.
2) If that's not the case, we'll crawl all subpages ourself.
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
			//print_r($urlSitemap);
			
			

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

	//Debug-Thing: Setting $userCheckBox to "off" excludes all subpages from the process!
		if($userCheckBox == "on"){
			$urls = $subpages;
			$userUrls = [$userURL,];
		} else {
			$urls = array ();
			array_push($urls,$userURL);
		};

	$urlCollection = array ();
	$elementCollection = array();
	
	
//Variable Template
	$elementCollectionEP = $elementtype; //Element Collection for the entire Page.


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
			$cssProperties[$prop] = $browser->script(file_get_contents('styleInFile.js' )."return styleInPage('$prop','$element');") [0];
			foreach ($cssProperties[$prop] as $cssAttribute) {
				array_push($elementCollectionEP[$element][$prop],$cssAttribute);
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

	
	
/* ===============  Output Variables  ===============
//Variable to store all unique properties of all pages!

- All Information, seperately accessable for each URL can found in: data =  $urlCollection[URL][HTML-TAG][CSS-Property]
- All Information, regardless of the URL's can be found in $elementCollectionEP[HTML-TAG][CSS-Property]

--------- Example: ---------


$elementCollectionEP['h2']['fontFamily'] = array_unique($elementCollectionEP['h2']['fontFamily']);
	for($i = 0;  $i < sizeof($elementCollectionEP['h2']['fontFamily']); $i++){
		if(strval($elementCollectionEP['h2']['fontFamily'][$i]) != "" ){
			print_r("Schriftart_0[".$i."] ".$elementCollectionEP['h2']['fontFamily'][$i]."\n");
		}
}


		
====================================================


/* ========================= HTML OUTPUT - Test =========================


print_r(" ======================= TESTAUSGABE ====================="."/n");

print_r("---------- H1"."\n");


for ( $i = 0; $i < sizeof( $elementCollectionEP['h1']['fontFamily'] ); $i ++ ) {
		print_r($elementCollectionEP['h1']['fontFamily'][$i]."\n");
}

print_r("---------- H2"."\n");
$elementCollectionEP['h2']['fontFamily'] = str_replace('\"', '', $elementCollectionEP['h2']['fontFamily']);


for ( $i = 0; $i < sizeof( $elementCollectionEP['h2']['fontFamily'] ); $i ++ ) {
		print_r(strval($elementCollectionEP['h2']['fontFamily'][$i])."\n");
	
}
*/



//TODO: Font-Family Namen lesbar machen!

	//TODO: Nur für Ausgabe aufbereiten
        //Zuerst einfach ausgeben lassen!


	//TODO: Für Stylesheet-Verwendgung


/*
 
 * Aktuelles Format ?

source_sans_proregular
entypo-fontello
source_code_prolight
source_code_proregular
source_sans_proitalic
source_sans_prolight_italic

Baskerville, "Palatino Linotype", Palatino, "Times New Roman", serif
 *
 *
 * Zulässiges Format?
 * <Body> , Spezifikationen (sans serif, etc ...
 *
 */

/* ======================================================================== */



?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="Styleguide/styleguide.css">
	
	
	<? echo"<title>".$hostCode."- Styleguide</title>"?>
</head>
<body>
<!-- NEUE VERSION -->


<div class = "container">
	<div class="container_heading">
		<? echo"<h1>".$userName."- Styleguide</h1>"?>
	</div>
	
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> DESKTOP<br>TYPOGRAPHIE</p>
		
		</div>
		<div class="labelbar">
			
			<?
			
			for ($j = 0; $j < 6; $j++) {
				$elementCollectionEP[$elementtype[$j]]['fontFamily'] = array_unique($elementCollectionEP[$elementtype[$j]]['fontFamily']);
				for($i = 0;  $i < sizeof($elementCollectionEP[$elementtype[$j]]['fontFamily']); $i++){
					if(strval($elementCollectionEP[$elementtype[$j]]['fontFamily'][$i] != "")){
						echo "<".$elementtype[$j].">"."H".($j + 1).":</".$elementtype[$j].">";
					}
				}
				
			}
			
			$elementCollectionEP['p']['fontFamily'] = array_unique($elementCollectionEP['p']['fontFamily']);
			for($i = 0;  $i < sizeof($elementCollectionEP['p']['fontFamily']); $i++){
				if(strval($elementCollectionEP['p']['fontFamily'][$i] != "")){
					echo "<p>"."Text ".":</p>";
					echo "<p>".$elementCollectionEP['p']['fontFamily'][$i]."</p>";
				}
			}
			
			
			?>
			
			<!--
			$elementCollectionEP['h1']['fontFamily'] = array_unique($elementCollectionEP['h1']['fontFamily']);
			for($i = 0;  $i < sizeof($elementCollectionEP['h1']['fontFamily']); $i++){
				if(strval($elementCollectionEP['h1']['fontFamily'] != "")){
					echo "<h1>H1:</h1>";
				}
			}
			
			$elementCollectionEP['h2']['fontFamily'] = array_unique($elementCollectionEP['h2']['fontFamily']);
			for($i = 0;  $i < sizeof($elementCollectionEP['h2']['fontFamily']); $i++){
				if(strval($elementCollectionEP['h2']['fontFamily'] != "")){
					echo "<h2>H2:</h2>";
				}
			}
			?>
			<h3>H3:</h3>
			<h4>H4:</h4>
			<h5>H5:</h5>
			<h6>H6:</h6>
			-->
			
			
			<br>
			
			<!--
			<p class="text-BodyCopy">Body Copy 1a (short text):</p><br>
			<p> Source Sans Pro</p><br>
			<p class = "text-BodyCopy2"> Body Copy 2:</p><br>
			<p> Source Sans Pro</p><br>
			-->
		
		</div>
		<div class="mainWindow">
			
			<?
			for ( $j = 0; $j < 6; $j ++ ) {
				$elementCollectionEP[ $elementtype[$j] ]['fontFamily'] = array_unique( $elementCollectionEP[ $elementtype[ $j ] ]['fontFamily'] );
				for ( $i = 0; $i < sizeof( $elementCollectionEP[ $elementtype[$j]]['fontFamily'] ); $i ++ ) {
					if ( strval( $elementCollectionEP[ $elementtype[ $j ] ]['fontFamily'][$i] != "" ) ) {
						echo "<".$elementtype[$j]."> HEADER - ".ucwords(strtolower($elementCollectionEP['h1']['fontFamily'][$i]), '_')." -  SIZE (TODO px):</".$elementtype[$j].">";
						//echo "<".$elementtype[$j]."> HEADER - ".$elementCollectionEP['h1']['fontFamily'][$i]." -  SIZE (TODO px):</".$elementtype[$j].">";
					}
				}
				
			}
			
			$elementCollectionEP['p']['fontFamily'] = array_unique($elementCollectionEP['p']['fontFamily']);
			
			for ( $i = 0; $i < sizeof( $elementCollectionEP['p']['fontFamily'] ); $i ++ ) {
				if ( strval( $elementCollectionEP['p']['fontFamily'][$i] != "" ) ) {
					echo "<p style = font-family:".$elementCollectionEP['p']['fontFamily'][$i].">Of all of the celestial bodies that capture our attention and fascination as astronomers, none has a greater influence on life on planet Earth than it’s own satellite, the moon. When you think about it, we regard the moon with such powerful significance that unlike the moons of other planets which we give names, we only refer to our one and only orbiting orb as THE moon. It is not a moon. To us, it is the one and only moon.</p>";
				}
			}
			
			
			
			?>
			<!--
			//TODO: Brauch ich die unique Funktion hier überhaupt noch?
			$elementCollectionEP['h1']['fontFamily'] = array_unique($elementCollectionEP['h1']['fontFamily']);
			for($i = 0;  $i < sizeof($elementCollectionEP['h1']['fontFamily']); $i++){
				if(strval($elementCollectionEP['h1']['fontFamily'][$i]) != ""){
					echo "<h1> HEADER - ".str_replace(ucwords(strtolower($elementCollectionEP['h1']['fontFamily'][$i]), '_'),'_',' ')." -  SIZE (TODO px):</h1>";
				}
			}
			
			
			$elementCollectionEP['h2']['fontFamily'] = array_unique($elementCollectionEP['h2']['fontFamily']);
			for($i = 0;  $i < sizeof($elementCollectionEP['h2']['fontFamily']); $i++){
				if(strval($elementCollectionEP['h2']['fontFamily'][$i]) != ""){
					echo "<h2> HEADER - ".$elementCollectionEP['h2']['fontFamily'][$i]." -  SIZE (TODO px):</h2>";
				}
			}
			
			?>
			
			<h3>HEADER  - Font - SIZE (30px)</h3>
			<h4>HEADER  - Font - SIZE (22p)</h4>
			<h5>HEADER  - Font - SIZE (20px)</h5>
			<h6>HEADER  - Font - SIZE (15p)</h6>
			
			
			-->
			<br>
			<!--
			<p>Of all of the celestial bodies that capture our attention and fascination as astronomers</p>
			<p>Of all of the celestial bodies that capture our attention and fascination as astronomers, none has a greater influence on life on planet Earth than it’s own satellite, the moon. When you think about it, we regard the moon with such powerful significance that unlike the moons of other planets which we give names, we only refer to our one and only orbiting orb as THE moon. It is not a moon. To us, it is the one and only moon.</p>
			-->
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
	<div class="wrapper">
		<div class ="sidebar">
			<p class="sidebar_heading"> COLOR </p>
		</div>
		<div class="labelbar">
			<p> PRIMARY</p>
		</div>
		
		<div class="colorBlock1">
			<p class="colorLabel"> #cdcdcd </p>
		</div>
		
		<div class="colorBlock2">
			<p class="colorLabel" style="color: black;"> #f2d3ee </p>
		</div>
		<div class="colorBlock3">
			<p class="colorLabel"> #ab23ac </p>
		
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

</div>

<!-- ALTE VERSION -->

<div class="container">
	
	<div>
		<button class="btn" value ="test" onclick="hideElement()"> Durchsuchte Seiten anzeigen</button>
		<div id="urlList">
			<? foreach ($subpages as $pages){
					echo "<div>".$pages."</div>";
				}
			?>
		</div>
	</div>
	
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

</script>


</html>