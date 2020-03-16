<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link rel="stylesheet" href="style.css">
	
	
	<title>Document</title>
</head>
<body onload="showLoader()">


<div id="gradient">

<div class="container">
		<div class="home_Heading">
			<h1 style="font-family: 'Source Sans Pro'"> Steuere Deine Webseite <br>  auf Erfolgskurs.</h1>
			<div class="lds-roller" id="loadIcon"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
		</div>
	
	
	
		<div class = "userInput">
			<form class ="userForm" action="output.php" method="post">
				<div class ="inputBox">
					<label class="basicLabel" for="urlInput"></label>
					<input id="urlInput" class=" basicSearch" placeholder="Gib eine Domain ein und führe einen CI-Check durch!" type="text" name="userUrl" required>
					<input class="btn_input basicInput" onclick="showLoader()" type="submit" value="START">
				</div>
				
			</form>
		
		</div>
	</div>

</body>



<!-- TODO: FIX: Loader wird auch angezeigt wenn der Input die Validierung nicht besteht! -->
<script>
  function showLoader() {
    
    var loader = document.getElementById("loadIcon");
    
	    if(loader.style.display === "none"){
	      loader.style.display = "inline-block";
	    } else {
	      loader.style.display ="none";
	    }
	  
    }
    
    document.getElementsByName(user)
	
</script>

</html>

