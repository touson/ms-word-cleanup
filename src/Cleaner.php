<?php

namespace Touson\Mswordcleanup;

class Cleaner
{

	function __construct($html)
	{
		$this->html = $html;

		$this->keepSpaces = true;
		$this->keepClasses = true;
		$this->removeTablewidths = true;

		$this->foundcode = false;
		$this->foundif = false;

		$this->imagePath = "/images/";

		$this->incode = false;

		$this->last_was_wordlist = false;
		$this->numimages = false;
		$this->imagenum = 1;

		$this->inItem = false;
		$this->currentLevel = "0";

		$this->webmastersherpa = strpos(getenv('HTTP_HOST'), 'webmastersherpa.com') !== false ? true : false;
	}

	public function cleanHtml()
	{
		$converted_lines = '';
		// clean that shit
		$lines = explode("\n", $this->html);
		$previousLine = '';
		foreach ($lines as $lineNum => $line) {
			$converted_lines .= $this->cleanLine($line, $previousLine);
			$previousLine = $line;
		}
		// --- get rid of extra <br /> at beginning and extra &nbsp; at end...
		$converted_lines = str_ireplace("<div><br />","<div>",$converted_lines);
		$converted_lines = str_ireplace("&nbsp;</div>","</div>",$converted_lines);
		$converted_lines = str_ireplace("&nbsp;<br />","<br />",$converted_lines);
		$converted_lines = preg_replace("/ [ ]+/"," ",$converted_lines);

		$converted_lines = str_ireplace("<br /></ol>","</ol>",$converted_lines);

		$converted_lines = preg_replace('/<v:[^>]+>/','',$converted_lines);
		$converted_lines = preg_replace('/<\/v:[^>]+>/','',$converted_lines);
		$converted_lines = preg_replace('/<o:[^>]+>/','',$converted_lines);
		$converted_lines = preg_replace('/<\/o:[^>]+>/','',$converted_lines);

		// --- get rid of extra spaces and that immediately follow a <li>...
		$converted_lines = preg_replace("/<li>(&nbsp;)+/", "<li>", $converted_lines);

		// --- get rid of extra extra <strong> that are sometimes put back in (e.g., in Japanese text)
		$converted_lines = preg_replace('/(<strong>)+/','<strong>',$converted_lines);
		$converted_lines = preg_replace('/(<\/strong>)+/','</strong>',$converted_lines);

		// Delete blank class tags
		$converted_lines = preg_replace('/ class=""/','',$converted_lines);

		// --- get rid of width statement in a table...
		if ($this->removeTablewidths && stristr($converted_lines,"<td")) {
			$converted_lines = preg_replace('/ width=\"[^\"]+\"/','',$converted_lines);
		}
		// ---------------------------------------------------------------------------------------------------------------
		// --- finally, let's get rid of extra blank lines
		// ---------------------------------------------------------------------------------------------------------------
		$this->cleanedHtml = removeEmptyLines($converted_lines);
		return $this->cleanedHtml;
	}

	public static function clean($html)
	{
		$cleaner = new static($html);
		return $cleaner->cleanHtml();
	}

	private function cleanLine($line, $previousLine)
	{
		// -----------------------------------------------------------------------------------------------------------------------------
		// --- let's handle <code></code> lines a bit differently...
		// -----------------------------------------------------------------------------------------------------------------------------
		if (stristr($line,"<code")) $this->incode = true;
		if (stristr($line,"</code")) $this->incode = false;

		// -----------------------------------------------------------------------------------------------------------------------------
		// --- tidy up the line...
		// -------------------------------------------------------------------------------------------------------
		if (!$this->incode) $line = $this->tidy($line);

		// Fix all list items
		$line = $this->fixLists($line);

		// Update any image references
		$line = $this->replaceImages($line);

		// -----------------------------------------------------------------------------------------------------------
		// --- replace any comments (e.g. <!-- [if !supportFootnotes]-->, <!--[endif]-->, etc.
		$line = preg_replace("/<![^>]+>/","",$line);

		// -----------------------------------------------------------------------------------------------------------
		// --- get rid of <a name=...> code...
		$count = substr_count($line,"<a name");
		for ($i=0; $i<$count; $i++) {
			if (stristr($line,"<a name")) {
				$start = strpos($line, "<a name");
				$temp = substr($line,$start);
				$stop = strpos($temp, ">")+$start+1;
				$line1 = substr($line,0,$start);
				$line2 = substr($line,$stop);
				$line = $line1 . $line2;

				// now find the </a> and delete it...
				$start2 = strpos($line, "</a>");
				$line1 = substr($line,0,$start2);
				$stop2 = $start2+4;
				$line2 = substr($line,$stop2);
				$line = $line1 . $line2;
			}
		}

		// --- replace extra/multiple spaces (won't make a difference in display but tidies up the code...
		$line = preg_replace("/ [ ]+/"," ",$line);
		$line = str_ireplace("<li> ","<li>",$line);
		$line = str_ireplace("<li>&nbsp;","<li>",$line);

		// --- Sometimes there are blank lines after FCKeditor works on MS Word pastes so get rid of them....
		if (strlen($line)<=3) {
			$line = str_replace("\r","",$line);
			$line = str_replace("\n","",$line);
		}

		// --- replace &nbsp;/ to help with later footnote manipualation
		$line = str_ireplace("&nbsp;/"," /",$line);
		$line = str_ireplace("/&nbsp;","/ ",$line);

		// --- sometimes an <a title for footnote doesn't have a closing </a> tag - let's fix that...
		if (stristr($line,"<a title=") && stristr($line,"</h1>")) if (!stristr($line,"</a></h1>")) $line = str_ireplace("</h1>","</a></h1>",$line);
		if (stristr($line,"<a title=") && stristr($line,"</h2>")) if (!stristr($line,"</a></h2>")) $line = str_ireplace("</h2>","</a></h2>",$line);
		if (stristr($line,"<a title=") && stristr($line,"</h3>")) if (!stristr($line,"</a></h3>")) $line = str_ireplace("</h3>","</a></h3>",$line);
		if (stristr($line,"<a title=") && stristr($line,"</h4>")) if (!stristr($line,"</a></h4>")) $line = str_ireplace("</h4>","</a></h4>",$line);

		// --- use the following to handle case where a heading has no associated text but holds other sub-entries
		if (stristr($line,"<h2") && stristr($previousLine,"<h1")) $line = "<br />\n" . $line;
		if (stristr($line,"<h3") && stristr($previousLine,"<h2")) $line = "<br />\n" . $line;
		if (stristr($line,"<h4") && stristr($previousLine,"<h3")) $line = "<br />\n" . $line;
		if (stristr($line,"<h5") && stristr($previousLine,"<h4")) $line = "<br />\n" . $line;

		if (stristr($line,"<table") && stristr($line,"</table")) {
			preg_match_all('/<table(.*?)<\/table>/', $line, $matches);
			if($matches) {
				foreach($matches[0] as $match) {
					$cleanedTable = $this->cleanTableContents($match);
					$line = str_replace($match, $cleanedTable, $line);
				}
			}
		}

		return $line;
	}

	private function cleanTableContents($line)
	{
		// --- catch any line that is still encased in <p></p> statements...
		$line = preg_replace("/<p[^r][^>]+>/","\n",$line);
		$line = str_ireplace("<p>","\n",$line);
		$line = str_ireplace("</p>","\n",$line);
		return $line;
	}

	private function fixLists($line)
	{
		$this->item = array();
		$nomatch = 0;
		$listType = array();
		$list = array();
		// ---------------------------------------------------------------------------------------------------------------
		// --- NOTE: this function can handle as many levels of lists as possible with one major exception: NO symbol type can be used more than once
		// ---       if so, the function will assume that was a previous level and will close it...
		// --- NOTE: we must handle two different situations: 1. a list item is only one line long so <p></p> will
		// ---       be on the same line and 2. a list item runs multiple lines so the <p> is on one line and the </p>
		// ---       is on a subsequent line.
		// ---------------------------------------------------------------------------------------------------------------
		$match = false;
		$tag["numeric"] = "ol";
		$tag["upperalpha"] = "ol";
		$tag["loweralpha"] = "ol";
		$tag["upperroman"] = "ol";
		$tag["lowerroman"] = "ol";
		$tag["middot"] = "ul";
		$tag["sect"] = "ul";
		$tag["circle"] = "ul";

		// --- sometimes we just need to manually end a list in the code - you can do that by adding a line where you
		// ---   want the list to be closed with the following code: <endlist>
		// --- you could just add a </ul> but this will keep the cleanit list tracking accurate and (hopefully) won't
		// ---   end up closing lists after the fact, leading to html that doesn't validate...
		$endlist = false;
		if (stristr($line,"<endlist>")) {
			$endlist = true;
			$line = str_ireplace("<endlist>","",$line);
		}

		if (preg_match("/<p>([0-9]+)\)/i",$line)
				|| preg_match("/<p>([0-9]+)\./i",$line)
				|| preg_match("/<p>(\([a-z]\))/i",$line)
				|| preg_match("/<p>([a-z]\))/i",$line)
				|| preg_match("/<p>([a-z])\./i",$line)
				|| preg_match("/<p>([IVXMDC]+)\./",$line)
				|| preg_match("/<p>([ivxmdc]+)\./",$line)
				|| preg_match("/<p>&middot;/i",$line)
				|| preg_match("/<p>&sect;/i",$line)
				|| preg_match("/<p>&acirc;/i",$line)) {
			$match = true;
			$this->inItem = true;
			$currentList = "";
			$nomatch = 0;
		}
		// --- check if it's a table inside a list item - don't want to add/close lists if so...
		if (stristr($line,"<table")) $table = true;

		// ----------------------------------------------------------------------------------------------------------------
		// --- First, see if the line is already a properly formed list item - sometimes FCKeditor does get it right after all. If so, skip this code...
		// ---
		if (preg_match("/<ul/i",$line)
				|| preg_match("/<ol/i",$line)
				|| preg_match("/<li/i",$line)
				|| preg_match("/<\/li>/i",$line)
				|| preg_match("/<\/ul>/i",$line)
				|| preg_match("/<\/ol>/i",$line)) {
			$wordlist = true;
			if (stristr($line,"<li>") && !stristr($line,"</li>")) $line .= "</li>\n";
			if (stristr($line,"</li>") && !stristr($line,"<li>")) $line = str_ireplace("</li>","",$line);
		} else {
			// --- not sure how accurate the roman numeral match is - could catch other things by mistake - investigate later...
			if (preg_match("/<p>([0-9]+)\)/i",$line)) {
				$search = "<p>([0-9]+)\)";
				$currentList = "numeric";
				$class = "";
			}
			if (preg_match("/<p>([0-9]+)\./i",$line)) {
				$search = "<p>([0-9]+)\.";
				$currentList = "numeric";
				$class = "";
			}
			if (preg_match("/<p>([a-z])\./",$line)) {
				$search = "<p>([a-z])\.";
				$currentList = "loweralpha";
				$class = " class=\"loweralpha\"";
			}
			if (preg_match("/<p>(\([a-z]\))/i",$line)) {
				$search = "<p>(\([a-z]\))";
				$currentList = "loweralpha";
				$class = " class=\"loweralpha\"";
			}
			if (preg_match("/<p>([a-z]\))/i",$line)) {
				$search = "<p>([a-z]\))";
				$currentList = "loweralpha";
				$class = " class=\"loweralpha\"";
			}
			if (preg_match("/<p>([A-Z])\./",$line)) {
				$search = "<p>([A-Z])\.";
				$currentList = "upperalpha";
				$class = " class=\"upperalpha\"";
			}

			// --- this will catch roman numeral lists but it needs work - in particular, how can I
			// --- reasonably differentiate between a C roman numeral list and a C alpha list? For now,
			// --- I am limiting the possible matched - so long as a alpha list doesn't reach i/I we
			// --- should be fine. Maybe I should pass a variable to toggle roman check on or off?
			if (preg_match("/<p>([ivxm]+)\./",$line)) {
				$search = "<p>([ivxmdc]+)\.";
				$currentList = "lowerroman";
				$class = " class=\"lowerroman\"";
			}
			if (preg_match("/<p>([IVXM]+)\./",$line)) {
				$search = "<p>([IVXMDC]+)\.";
				$currentList = "upperroman";
				$class = " class=\"upperroman\"";
			}

			if (preg_match("/<p>&middot;/i",$line)) {
				$search = "<p>&middot;";
				$currentList = "middot";
				$class = " class=\"disc\"";
			}
			if (preg_match("/<p>&sect;/i",$line)) {
				$search = "<p>&sect;";
				$currentList = "sect";
				$class = " class=\"square\"";
			}
			if (preg_match("/<p>&acirc;/i",$line)) {
				$search = "<p>&acirc;";
				$currentList = "circle";
				$class = " class=\"circle\"";
			}

			// ------------------------------------------------------------------------------------------------------------
			// --- Does beginning of line indicate a list item?
			// ------------------------------------------------------------------------------------------------------------
			if ($match) {
				// ------------------------------------------
				// --- If Yes, does this level already exist?
				// ---
				if (!$list) $list = array();
				if (in_array($currentList, $list)) {
					// ----------------------------------------
					// --- If Yes, mark this line as a new item
					// ---
					$line = preg_replace($search." ","<li>",$line);

					// -----------------------------------------------------------------------------------------------------
					// --- Now, was the last item a deeper level than this one? If Yes, close the deeper level list and delete from $list array...
					// ---
					$this->currentLevel = array_search($currentList,$list);
					$this->item[$this->currentLevel]++;
					if ((count($list)-1)>$this->currentLevel || $endlist) {
						if ($tag[$list[count($list)-1]]) $line = "</".$tag[$list[count($list)-1]]."><br />\n" . $line;
						$this->item[count($list)-1] = 0;
						unset($list[count($list)-1]);
					}
					// -----------------------------------------------------------------------------------------------------
					// --- repeat for occasion where two nested lists both end...
					// ---
					if ((count($list)-1)>$this->currentLevel || $endlist) {
						if ($tag[$list[count($list)-1]]) $line = "</".$tag[$list[count($list)-1]]."><br />\n" . $line;
						$this->item[count($list)-1] = 0;
						unset($list[count($list)-1]);
					}
				} else {
					// ---
					// --- If no, create new list
					// ---
					$replace = "<".$tag[$currentList].$class.">\n<li>";
					$line = preg_replace($search." ",$replace,$line);
					$count = count($list);
					$list[$count] = $currentList;
					$this->item[$count] = 1;
				}
			} else {
				$nomatch++;

				// --------------------------------------------------------------------------------------------------------
				// --- only worry about closing lists if (1) we're in a list; (2) there are no items
				// ---      (3) we're not in a list and displaying a table; (4) we aren't on a non-item text
				// ---      line after just ending a list that FCKeditor converted properly from word
				// --------------------------------------------------------------------------------------------------------
				if (count($list)>0
						&& !$this->inItem
						&& !$table
						&& !$wordlist
						&& !$this->last_was_wordlist) {
					// --- we should track number of blank lines to see if we need to close lists (below) but in case
					// ---   we come across a new heading we should just go ahead and close all lists now...
					if (stristr($line,"<em") || stristr($line,"<h1>") || stristr($line,"<h2>") || stristr($line,"<h3>") || $endlist) {
						for ($inc=count($list); $inc>=0; $inc--) {
							if ($list[$inc] && $tag[$list[$inc]]) $line = "</".$tag[$list[$inc]]."><br />\n".$line;
						}
						unset($list);
						$this->currentLevel = "";
						$nomatch = 0;
					}

					// -------------------------------------------------------------------------------------------------
					// --- if we encounter an image in a list item we don't want to increment our nomatch; in fact,
					// --- since an image usually has a <br /> before and after, we will reset nomatch
					// -------------------------------------------------------------------------------------------------
					if ($nomatch<2 && stristr($line,"<img")) $nomatch = 0;

					// -------------------------------------------------------------------------------------------------
					// --- If we're in a multi-line list item, we won't encounter a </p> so no need to worry about
					// --- closing the current list unless we do. BUT, if we just closed a list the last time and
					// --- if the current line is a </p> or <br /> then we will want to close the current list
					// -------------------------------------------------------------------------------------------------
					if ( $list[$this->currentLevel] && (stristr(substr($line,-6),"</p>") || ($nomatch>=2 && substr($line,0,6)=="<br />")) ) {
						if ($tag[$list[$this->currentLevel]]) {
							$line = "</".$tag[$list[$this->currentLevel]]."><br />\n" . $line;
							unset ($list[$this->currentLevel]);
							$this->currentLevel--;
							if ($this->currentLevel<0) $this->currentLevel = 0;
							if (count($list)==0) $list = array();
						}
					}
					// --- if we are in a multi-level list and have 2+ nomatches then we should close 2 levels...
					if ($list[$this->currentLevel] && $nomatch>=2 && (substr($line,0,6)=="<br />" || stristr($line,"<img") || stristr($line,"<p"))) {
						if ($tag[$list[$this->currentLevel]]) {
							$line = "</".$tag[$list[$this->currentLevel]]."><br />\n" . $line;
							unset ($list[$this->currentLevel]);
							$this->currentLevel--;
							if ($this->currentLevel<0) $this->currentLevel = 0;
							if (count($list)==0) $list = array();
						}
					}
					// --- since we need to have 2 line breaks to indicate closing of multi-level list let's remove
					// --- the <br /> line(s) so we don't violate W3C standards (note we add a <br /> after closing list
					if ($nomatch>=1) {
						$line = str_ireplace("<br />","",$line);
					} else {
						$line = str_ireplace("</p>","<br />",$line);
					}

				}
				// --- let's close any manually added <endlist> that weren't handled already...
				if ($endlist) {
					//$line .= "<br>jackie<br>\n";
					$line = "</".$tag[$list[count($list)-1]].">\n" . $line;
					$this->item[count($list)-1] = 0;
					unset($list[count($list)-1]);
				}
			}
		}

		//echo $nomatch . " - " . htmlentities($line)."<br>";
		// ------------------------------------------------------------------------------------------------------------
		// --- If we're in a list item and we have a ending </p> let's close that list item...
		// ------------------------------------------------------------------------------------------------------------
		if ($this->inItem && stristr(substr($line,-6),"</p>")) {
			$line = str_ireplace("</p>","</li>",$line);
			$this->inItem = false;
		}


		if (stristr($line,"</table")) $table = false;
		if ($this->last_was_wordlist) $this->last_was_wordlist = false;
		if ( (stristr($line,"</ul") || stristr($line,"</ol")) && $wordlist) {
			$wordlist = false;
			$this->last_was_wordlist = true;
		}

		// -----------------------------------------------------------------------------------------------------------
		// ------ handle exisitng lists (repace type="" for those that FCKeditor did convert) {do we want to keep the start= statements?}
		$line = preg_replace('/ type=[^>]+>/','>',$line);

		return $line;
	}

	private function replaceImages($line)
	{
		// -----------------------------------------------------------------------------------------------------------
		// --- replace image with best guess of the right one to use. There are 2 ways to handle this. First,
		// ---    we can copy all the images created when saving a MS Word document as filtered HTML to the
		// ---    /images/<filename directory> (typically imgage0XX.XXX)
		// ---    Alternatively, FCKedtior creates multiple images (not sure how/why) in a temp directory. In
		// ---    theory we could copy those files over to the same directory and just use the image # found in
		// ---    the html created by FCKedtitor. I am choosing the former...
		// -----------------------------------------------------------------------------------------------------------
		$endpos = 0;
		if (!$this->numimages) {			// --- use for tracking when v:shapes exists instead of image#...
			$this->numimages = 0;
			if ($this->imagenum>1) $this->numimages = $this->imagenum;
		}
		if (stripos($line,".jpg")
				|| stripos($line,".gif")
				|| stripos($line,".png")
				|| stristr($line,"<img")
				|| stripos($line,"v:imagedata")) {
			// --- handle case where just closed a list item or entire list - need to keep the closing tag...(for wembastersherpa demo)
			if (substr($line,0,5)=="</li>" || substr($line,0,5)=="</ul>" || substr($line,0,5)=="</ol>") $pre = substr($line,0,5);
			$this->numimages++;
			$startpos = stripos($line,"image");
			$endpos = stripos($line,".jpg");
			if ($endpos==0) $endpos = stripos($line,".gif");
			if ($endpos==0) $endpos = stripos($line,".png");
			// --- handle case where no image # is created by FCKeditor but a v:shapes instead...
			if (!$startpos || isset($_POST["imagestartnum"])) {
				$vshapes = true;
				$startpos = stripos($line,"v:shapes=");
				$endpos = stripos($line,"/>");
				if ($this->numimages<1000) $image = $this->imagePath."image".$this->numimages.".gif";
				if ($this->numimages<100) $image = $this->imagePath."image0".$this->numimages.".gif";
				if ($this->numimages<10) $image = $this->imagePath."image00".$this->numimages.".gif";
			} else {
				$image = $this->imagePath.substr($line,$startpos,($endpos-$startpos+4));
			}
			if (!$this->webmastersherpa && $this->imagePath!="/images/") {
				$size = @getimagesize("..".$image);
				// --- may have guessed to use .gif when actually should have used .jpg so try again if no size...
				if (!$size) {
					$image = substr($image,0,strlen($image)-3)."jpg";
					//echo $image ."- " . $this->numimages. "<br>";
					$size = @getimagesize("..".$image);
				}
				$width = $size[0];
				$height = $size[1];
				//echo $image . " imagesize - " . $width . "<br>";
			}
			$temp = '<img src="'.$image.'" ';
			if (isset($width)) $temp .= 'width="'.$width.'" ';
			if (isset($height)) $temp .= 'height="'.$height.'" ';
			//$alt = ' alt="'.$altPrefix.' image number '.$this->imagenum.'"';
			$alt = ' alt="image number '.$this->imagenum.'"';
			$temp .= $alt . ' />';
			if ($this->webmastersherpa) {
				// --- for demo script on Webmastersherpa site just show the sherpa logo...
				$this->imagePath = "/images/";
				// --- if we see src=" in the line code assume that some using the demo specified an image and leave it alone...
				if (!stristr($line,"src="))	$line = $pre . '<img src="/images/logo.gif" /><br />';
			}
			$this->imagenum++;
		}

		// --- if I wanted to try method 2, the following line would be a start but
		// --- $temp = "<img src=\"".$imagepath."clip_image\" /><br />";
		// --- $line = preg_replace("/<img+[^>]+image([0-9]+)(\.[a-zA-Z]{3}).+>/",$temp\\1\\2,$line);

		if (isset($vshapes) && $vshapes == true && !$this->webmastersherpa) {
			$line = preg_replace("/<img+[^>]+>/",$temp,$line);
			$vshapes = false;
		} else {
			if ($this->imagePath=="/images/") {		// --- this means that the entry is direct from admin rather than via cleanit/add to db
				$line = $line;
			} else {
				$line = preg_replace("/<img+[^>]+image([0-9]+)(\.[a-zA-Z]{3}).+>/",$temp,$line);
			}
		}

		return $line;
	}

	private function replaceFontSize($line)
	{
		$fontSizeConversions = [
			6 => "h1",
			5 => "h2",
			4 => "h3",
			3 => "h4",
			2 => ""
		];

		foreach($fontSizeConversions as $fontSize => $titleSize) {
			if (stristr($line,"<strong><font size=\"{$fontSize}\">")) {
				if(empty($titleSize)) {
					$line = str_ireplace("<font size=\"{$fontSize}\">","",$line);
					$line = str_ireplace("</font>","",$line);
				} else {
					$line = str_ireplace("<strong><font size=\"{$fontSize}\">","<{$titleSize}>",$line);
					$line = str_ireplace("</font></strong>","</{$titleSize}>",$line);
				}
			}
		}
		return $line;
	}

	/**
	 * Fixes special characters, extraneous tags etc.
	 *
	 * @param  string $line  A string of HTML
	 * @param  string $font6 HTML title size
	 * @param  string $font5 HTML title size
	 * @param  string $font4 HTML title size
	 * @param  string $font3 HTML title size
	 * @param  string $font2 HTML title size
	 * @return String        Cleaned string
	 */
	private function tidy($line)
	{
		// ---------------------------------------------------------------------------------------------------------------
		// --- sometimes FCKeditor pastes <b> statements and sometimes <strong> so first let's make all <b> <strong>
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("<b>","<strong>",$line);
		$line = str_ireplace("</b>","</strong>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace <font size=""> statements...
		// --- probably o.k. to just assume one </font> per line since using FCKeditor cleanup but may
		// ---   need to check this to verify...
		// ---   assume that if a size=6 exists it's b/c we are including chapters in the document...
		// ---------------------------------------------------------------------------------------------------------------
		$line = $this->replaceFontSize($line);

		$line = preg_replace('/MsoNormal/', '', $line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace <div> statements that surround <hx> statements...
		// ---------------------------------------------------------------------------------------------------------------
		if (stristr($line,"<div><h")) {
			$line = str_ireplace("<div><h","<h",$line);
			$line = str_ireplace("</div>","",$line);
		}

		// ---------------------------------------------------------------------------------------------------------------
		// --- in fckeditor fck_paste.html file all <p> tags are converted to <div> but apparently this only
		// --- works in IE and it kills this cleanit function which always looks for <p> tags for lists so
		// --- let's just undo the conversion (I know this is lame but...)
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("<div","<p",$line);
		$line = str_ireplace("div>","p>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- in case remove styles definitions option wasn't selected, let's remove all styles
		// ---------------------------------------------------------------------------------------------------------------
		$line = preg_replace('/ style="([^\"]*)"/','',$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- convert <span class="CodeChar"> to <code> o/w remove <span class...
		// ---------------------------------------------------------------------------------------------------------------
		if (stristr($line,"<span class=\"CodeChar\">")) {
			// --- added next 2 lines 12-10-08 - JB - to account for small changes in way FCKeditor new version works...
			$line = str_ireplace("<span>","",$line);
			$line = str_ireplace("</span></span>","</span>",$line);

			$line = preg_replace('/<span class="CodeChar">([^\<]*)<\/span>/','<code>\\1</code>',$line);
		}
		// --- treat <p class="Code"> as a block of code and not individual lines of code...
		if (!stristr($line,"<p class=\"Code\">") && $this->foundcode==true) {
			$line = "</code>\n" . $line;
			$this->foundcode = false;
		}
		if (stristr($line,"<p class=\"Code\">")) {
			if ($this->foundcode==false) {
				$line = str_ireplace('<p class="Code">','<code class="block">',$line);
				$line = str_ireplace('</p>','',$line);
				$this->foundcode = true;
			} else {
				$line = str_ireplace('<p class="Code">','',$line);
				$line = str_ireplace('</p>','',$line);
			}
		} else {
			if ($this->keepClasses == false
					&& !stristr($line,"<td")
					&& !stristr($line,"<ol")
					&& !stristr($line,"<ul")) {
				$line = preg_replace('/ class="([^\"]*)"/','',$line);
			}
		}

		// ---------------------------------------------------------------------------------------------------------------
		// --- FCKedit is a good editor, even converting from MS Word - but still some cleanup is helpful...
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("<p>&nbsp;</p>","<br />",$line);
		$line = str_ireplace("<strong><span>","",$line);
		$line = str_ireplace("</span></strong>","",$line);
		$line = str_ireplace("<p><strong>&nbsp;</strong></p>","<br />",$line);
		$line = str_ireplace("<p><span>&nbsp;</span></p>","<br />",$line);
		$line = str_ireplace("<pre><p>","<pre>",$line);
		$line = str_ireplace("</p></pre>","</pre>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- now handle pasting images from word and useless xml tags...
		// --- I haven't fully tested this. Basically, Firefox pastes a <img line but IE doesn't. Both paste a
		// --- <v:imagedata line. I'm not sure about other browsers so for now if using Firefox I will ignore the
		// --- <v:imagedata line and stick with the <img line...
		// ---------------------------------------------------------------------------------------------------------------
		$browser = $_SERVER['HTTP_USER_AGENT'];
		//echo $browser;
		if (stristr($browser,"Firefox")) {
			// --- remove anything inside <!--[if...><![endif]--> tags.
			if (stristr($line,"<!--[if") || $this->foundif==true) {
				$this->foundif = true;
				if (stristr($line,"<![endif]")) $this->foundif = false;
				if (stristr($line,"<img")) {
					$line = substr($line,stripos($line,"<img"));
				} else {
					$line = "";
				}
			}
		} else {
			$line = preg_replace('/<v:imagedata/','<img',$line);
		}
		$line = preg_replace('/<v:[^>]+>/','',$line);
		$line = preg_replace('/<v:[^>]+/','',$line);
		$line = preg_replace('/<\/v:[^>]+>/','',$line);
		$line = preg_replace('/<o:[^>]+>/','',$line);
		$line = preg_replace('/<o:[^>]+/','',$line);
		$line = preg_replace('/<\/o:[^>]+>/','',$line);
		$line = str_ireplace('o:title=""/>',"",$line);
		$line = preg_replace('/o:[^>]+>/','',$line);
		$line = preg_replace('/<st1:[^>]+>/','',$line);
		$line = preg_replace('/<\/st1:[^>]+>/','',$line);
		$line = preg_replace('/<p><img/','<img',$line);
		$line = preg_replace('/<p> [ ]+<img/','<img',$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace all the Microsoft special characters
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("&rsquo;","'",$line);
		$line = str_ireplace("&lsquo;","'",$line);
		$line = str_ireplace("&shy;","-",$line);
		$line = str_ireplace("&hellip;","...",$line);
		$line = str_ireplace('&quot;','"',$line);
		$line = str_ireplace('&ldquo;','"',$line);
		$line = str_ireplace('&rdquo;','"',$line);
		$line = str_ireplace('','',$line);
		$line = str_ireplace('&bull;','',$line);
		$line = str_ireplace("&#8217;", "'", $line);
		$line = str_ireplace("&#61553;", "&middot;", $line);
		$line = str_ireplace("&#9675;","&middot;",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace extraneous strong statements and white spaces...this is the same as in the clean strongs function
		// ---------------------------------------------------------------------------------------------------------------
		$line = preg_replace('/(<strong>)+/','<strong>',$line);
		$line = preg_replace('/(<\/strong>)+/','</strong>',$line);
		$line = preg_replace('/(<!--\[if !supportLists\]-->)(.+)(<strong>)(.+)(<!--\[endif\]-->)/','\\1\\2\\4\\5',$line);
		$line = preg_replace('/(<!--\[if !supportLists\]-->)(.+)(<\/strong>)(.+)(<!--\[endif\]-->)/','\\1\\2\\4\\5',$line);
		$line = preg_replace('/<p><strong><a title=(.+)<\/strong><\/p>/','<p><a title=.+\\1</p>',$line);
		$line = str_ireplace("/</strong><strong>/","",$line);
		$line = preg_replace("/ [ ]+/"," ",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace any remaining useless <font> tags MS Word adds...
		// ---------------------------------------------------------------------------------------------------------------
		$line = preg_replace('/<font[^>]+>/','',$line);
		$line = str_ireplace("</font>","",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace extra strong statement (sometimes occurs when you see a list item where the number is bold)
		// ---------------------------------------------------------------------------------------------------------------
		if (preg_match("/<p><strong>([0-9]+)\./",$line)) $line = str_ireplace("<p><strong>","<p>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- attempt to correct list items
		// --- fix strange things related to lists...
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("&#9675;","&middot;",$line);
		$line = str_ireplace('<span>&middot;&nbsp;</span>','<span>&middot;<span>&nbsp;&nbsp;  </span></span>',$line);
		$line = str_ireplace("<span>?<span>","<span>&middot; <span>",$line);
		$line = str_ireplace("<span>â—‹<span>","<span>&middot; <span>",$line);
		$line = str_ireplace("<span>o‹<span>","<span>&middot; <span>",$line);
		$line = str_ireplace('<span>o<span>&nbsp;','&acirc; ',$line);
		$line = preg_replace('/<span>o(&nbsp;+)<\/span>+/','<span>&acirc; </span>',$line);
		$line = str_ireplace("<span>-<span>&nbsp;&nbsp;","<span>&acirc; <span>",$line);
		$line = preg_replace("/<\/span>([a-z]+.)<span>/","</span><span>\\1<span>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- get rid of <!--[if !supportLists]--> and <!--[endif]--> (would get to it later with comments removal but need to do this special case first for lists...)
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("<!--[if !supportLists]--><strong>","",$line);
		$line = str_ireplace("<!--[if !supportLists]-->","",$line);
		$line = str_ireplace("<!--[endif]-->","",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- replace all the useless <span> tags MS Word adds...
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("  </span>","",$line);
		$line = preg_replace('/<span[^>]+>/','',$line);
		$line = str_ireplace("<span>","",$line);
		$line = str_ireplace("</span>","",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// ---   replace all the multiple space characters that are used instead of real lists (except for <code> lines)
		// ---------------------------------------------------------------------------------------------------------------
		if (!$this->incode && !$this->foundcode && !$this->keepSpaces) {
			$line = preg_replace("/&nbsp;(&nbsp;+)/", " ", $line);
			$line = preg_replace("/ [ ]+/"," ",$line);
			$line = str_ireplace(" &nbsp;"," ",$line);
			$line = str_ireplace("&nbsp; "," ",$line);
		}

		// --- even if we want to leave extra spaces in output, we must account for list items...
		$line = preg_replace("/([0-9]+)\.(&nbsp;)/", "\\1. ", $line);
		$line = preg_replace("/([a-z]+)\.(&nbsp;)/", "\\1. ", $line);

		$line = preg_replace("/<p> ([a-z]+.)/", "<p>\\1", $line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- clean up <Hx><u>...</u></Hx> tags by removing <u></u> tags - can use CSS instead
		// ---------------------------------------------------------------------------------------------------------------
		$line = preg_replace('/<h2><a name=(.+)<u>/','<h2><a name=\\1',$line);
		$line = str_ireplace("</u></a></h2>","</a></h2>",$line);

		// ---------------------------------------------------------------------------------------------------------------
		// --- clean up a silly thing mostly specific to my article conversions
		// ---------------------------------------------------------------------------------------------------------------
		$line = str_ireplace("</strong>:&nbsp;","</strong>: ",$line);

		return $line;
	}
}




// $cleaner = new Cleaner('html');
// $cleaner->cleanHtml();

// Cleaner::clean('html');