<?php
/*
			hyphenation 1.1 for MoreCSS 1.0 BETA 4
			Developed by yellowgreen designbüro published under MIT License.
			
			http://morecss.yellowgreen.de/documentation/plugins/hyphenation/
			http://morecss.yellowgreen.de/documentation/information/legal/
*/

			// Standard settings (see hyphenation.php for other settings)
    		$GLOBALS["path_to_patterns"] = "patterns/";
    		$GLOBALS["dictionary"] = "dictionary.txt";
    		$GLOBALS["hyphen"] = "&shy;";
    		$GLOBALS["exclude_tags"] = array("code", "pre", "script", "style");
    		
    		// MoreCSS settings
    		if(isset($_POST["language"])) switch($_POST["language"]) {
    			case "german" : case "de" : $GLOBALS["language"] = "de"; break;
    			case "english" : case "en" : $GLOBALS["language"] = "en"; break;
    			case "spanish" : case "es" : $GLOBALS["language"] = "es"; break;
    			case "french" : case "fr" : $GLOBALS["language"] = "fr"; break;
    			case "dutch" : case "nl" : $GLOBALS["language"] = "nl"; break;
    			case "swedish" : case "sv" : $GLOBALS["language"] = "sv"; break;
    			default : $GLOBALS["language"] = "en"; break;
    		}
    		
// OUTPUT PHP

			if(isset($_POST["text"])) {
				header('content-type: text/html; charset=utf-8');
	
				if(isset($_POST["restrict"])) switch($_POST["restrict"]) {
					case "large-words" : $GLOBALS["leftmin"] = 4; $GLOBALS["rightmin"] = 4; $GLOBALS["charmin"] = 10; break;
					case "normal" : $GLOBALS["leftmin"] = 2; $GLOBALS["rightmin"] = 3; $GLOBALS["charmin"] = 6; break;
					case "none" : default : $GLOBALS["leftmin"] = 2; $GLOBALS["rightmin"] = 2; $GLOBALS["charmin"] = 2; break;
				}
				
				// Get hyphenated content
				include("hyphenation.php");
				echo hyphenation(stripslashes($_POST["text"]));
			} else {

// OUTPUT JAVASCRIPT

				header('content-type: text/javascript; charset=utf-8');
				
				echo '				
					MoreCSS.properties["hyphenation"] = "hyphenation";

					// hyphenation
					MoreCSS.hyphenation = function(element, properties) {
						var mode = MoreCSS.getPropertyValue(properties, "hyphenation", "english");
						var restrict = MoreCSS.getPropertyValue(properties, "hyphenation-restrict", "normal");
						
						if(mode != "no-hyphenation") {
							var hyphContent = MoreCSS.createXMLHttpRequest();
							hyphContent.open("post", "' . $_SERVER['PHP_SELF'] . '", true);
							hyphContent.setRequestHeader("content-type", "application/x-www-form-urlencoded");
							hyphContent.send("text=" + encodeURIComponent(element.innerHTML) + "&language=" + mode + "&restrict=" + restrict);
							hyphContent.onreadystatechange = function() {
								if(hyphContent.readyState == 4 && hyphContent.status == 200) element.innerHTML = decodeURIComponent(hyphContent.responseText);
							};
						} else {
							html_entity_decode = function(string) {
								var tempElement = document.createElement("textarea");
								tempElement.innerHTML = string.replace(/</g, "&lt;").replace(/>/g, "&gt;");
								return tempElement.value;
							}
							
							if(element.innerHTML.search(new RegExp(html_entity_decode("' . $GLOBALS["hyphen"] . '"))) > 0)
								element.innerHTML = element.innerHTML.split(html_entity_decode("' . $GLOBALS["hyphen"] . '")).join("");
						}
					};
					
				';
			}
?>