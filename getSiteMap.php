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


$url = "https://schwanenstadt.at/";
$host = $url;
$hostCode = "schwanenstadt";





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
		
		print_r("Sitemap available: "."\n");
		print_r("URL: ".$urlSitemap."\n");
		$browser->visit($urlSitemap);
		$sitemapLinks = array();
		$subsitemaps = array ();
		$subpages = array ();
		
		$sitemapHTML = $browser->script('return document.body.innerHTML') [0];
		print_r($sitemapHTML);
		
		
		//Sitemap will be converted into an array of URLS
		
		
		$dom = new DOMDocument;
		$dom->loadHTML($sitemapHTML); //TODO: EXCEPTION FANGEN?
		
		if(sizeof($sitemapHTML) == null){
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
		}
		else {
			echo"<p> Doch keine SITEMAP!</p>";
			
			$urlsCrawled  = $browser->visit($host);
			
			echo "<p> GRÖßE: ".sizeof($browser->script( file_get_contents('getSubpages.js')."return getSubpages('$hostCode');")[0])."</p>";
			$hostSubpages = $browser->script( file_get_contents('getSubpages.js')."return getSubpages('$hostCode');")[0];
			
			echo "<p> Subpageausgabe von ".sizeof($hostSubpages)." Subpages</p>";
			foreach ($hostSubpages as $subpage) {
				echo "<p> Neue Subpage: ".$subpage."</p>";
			}
		}
		
		
		
		
		print_r('Subpages: ' .sizeof($subpages)."\n");
		print_r('SubSitemaps: '.sizeof($subsitemaps)."\n");
		
		//Subpages will be extracted from the Subsitemaps
		foreach($subsitemaps as $submap){
			$browser->visit($submap);
			$html = $browser->script('return document.body.innerHTML') [0];
			
			$dom = new DOMDocument;
			$dom->loadHTML($html);
			foreach ($dom->getElementsByTagName('a') as $node)
			{
				if(strpos($node->getAttribute("href"),$hostCode)){
					
					echo("<p> Subpage extracted from XML: ".$node->getAttribute("href")."</p>");
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


function crawlSubPages ($host){
	$urlsCrawled  = $browser->visit($host);
	$hostSubpages = $browser->script( file_get_contents( 'getSubpages.js' )) [0];
}