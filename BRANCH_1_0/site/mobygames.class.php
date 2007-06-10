<?php
/* 	
	Open Media Collectors Database
	Copyright (C) 2001,2006 by Jason Pell

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

	-- CHANGLOG --
		
	Version		Comments
	-------		--------
	0.81		0.81p14 release
	
*/
include_once("./functions/SitePlugin.class.inc");

function parse_mobygames_release_date($date)
{
	// german months!
  	$months = array('jan', 'feb', 'mrz', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dez');
	if(preg_match("/([0-9]*)[\s]*([a-zA-Z]+)[\s]*([0-9]*)/", $date, $matches))
	{
	  	$day = $matches[1];
	  	if(!is_numeric($day))
	  		$day = 1; // first day of the month
	  	
		$key = array_search(strtolower($matches[2]), $months);
		if($key !== FALSE)
			$month = $key + 1;
		else
		  	$month = 1; // first month of the year,
	
		$year = $matches[3];
	
		return mktime(0, 0, 0, $month, $day, $year);
	}
	
	//else
	return FALSE;
}

/* 
Maps MobyGames ratings

ELSPA Ratings
	290	=> 18+
	291	=> 15+
	292	=> 11+
	293	=> 3+

OFLC Ratings
	416	=> G
	417	=> G8+
	418	=> M15+
	419	=> MA15+

USK Ratings
	432	=> Free for all
	433	=> 6+
	434	=> 12+
	435	=> 16+
	436	=> Not free for minors
*/
class mobygames extends SitePlugin
{
	function mobygames($site_type)
	{
		parent::SitePlugin($site_type);
	}
	
	function queryListing($page_no, $items_per_page, $offset, $s_item_type, $search_vars_r)
	{
		// standard block of code to cater for refresh option, where item already has
		// reference to site item unique ID.
		if(strlen($search_vars_r['mobygameid'])>0 && strlen($search_vars_r['mgpltfrmid'])>0)
		{
			$this->addListingRow(NULL, NULL, NULL, array('mobygameid'=>$search_vars_r['mobygameid'], 'mgpltfrmid'=>$search_vars_r['mgpltfrmid']));
			return TRUE;
		}

		$pageBuffer = $this->fetchURI('http://www.mobygames.com/search/quick?s=Search&q='.urlencode($search_vars_r['title']));
		if(strlen($pageBuffer)>0)
		{
		  	//<a href="/game/win3x/3x3-eyes-kyuusei-koushu-"><img alt="Windows 3.x Front Cover" border="0" src="/images/i/39/36/24686.jpeg" height="60" width="42" ></a>
			// Look up all thumbnails; they are of the form:
			$nmatches = preg_match_all(
				";<a href=\"/game/([^/]+)/([^\"]+)\"><img [^>]*?" .
					"src=\"([^\"<>]*?)\"" .
					";is",
				$pageBuffer,
				$matches,
				PREG_PATTERN_ORDER);
			
			// Enter all of the thumbnails into $games[]
			for ($i = 0; $i < $nmatches; $i++)
			{
				// 1 -> Platform ID
				// 2 -> Game ID
				// 3 -> Thumbnail image URL (relative)
				$games[$matches[1][$i]][$matches[2][$i]]['thumb'] = $matches[3][$i];
			}
		
			// Find all of the links to games
			$nmatches = preg_match_all(
				";<a href=\"/game/([^/]+)/([^\"]+)\">([^\"<>]*)</a>;i",
				$pageBuffer,
				$matches,
				PREG_PATTERN_ORDER);
			
			if($nmatches > 0)
			{	
				for ($i = 0; $i < $nmatches; $i++)
				{
					// 1 -> Platform ID
					// 2 -> Game ID
					// 3 -> Name
					$games[$matches[1][$i]][$matches[2][$i]]['title'] = $matches[3][$i];
				}

				while(list($mgpltfrmid, $entries) = each($games))
				{
				  	while(list($game_id, $game) = each($entries))
				  	{
				  		$platform = $this->moby_platform_to_name[$mgpltfrmid];
					  
					  	$thumburl = NULL;
					  	if(strlen($game['thumb'])>0)
						{
						  	$thumburl = 'http://www.mobygames.com'.$game['thumb'];
						}
					  	
						$this->addListingRow($game[title], $thumburl, $platform, array('mgpltfrmid'=>$mgpltfrmid, 'mobygameid'=>$game_id));
					}
				}
			}
					  	
			return TRUE;
		}
		else
		{
		  return FALSE;
		}
	}

	function parse_game_value($pageBuffer, $title)
	{
		if(preg_match("!$title</div><div[^>]*><a href=\"[^\"]*\">([^<]*)</a>!i", $pageBuffer, $regs))
		{
			return $regs[1];
		}
	}

	/* parse_moby_data
	 * Return $attributes[], an array of attributes describing the game
	 * with the given ID.
	 * Attributes include:
	 *	title			Game title
	 *	release_date	Year when game was released
	 *	publisher		Who published the game
	 *	developer		Who wrote the game
	 *	score			MobyGames popularity score: 0(1?) to 5, in .5 increments
	 *	genre			Game genre: adventure, puzzle, etc.
	 */
	function queryItem($search_attributes_r, $s_item_type)
	{
		$pageBuffer = $this->fetchURI("http://www.mobygames.com/game/".$search_attributes_r['mgpltfrmid']."/".$search_attributes_r['mobygameid']);
		
		// no sense going any further here.
		if(strlen($pageBuffer)>0)
		{
			//<div id="gameTitle"><a href="/game/windows/counter-strike-source">Counter-Strike: Source</a></div>
			if (preg_match("!<div id=\"gameTitle\"><a href=\"[^\"]*\">([^<]*)</a>!", $pageBuffer, $matches))
			{
				$this->addItemAttribute('title', $matches[1]);
			}
			
			$this->addItemAttribute('publisher', $this->parse_game_value($pageBuffer, 'published by'));
			$this->addItemAttribute('developer', $this->parse_game_value($pageBuffer, 'developed by'));
			$this->addItemAttribute('genre', $this->parse_game_value($pageBuffer, 'genre'));
			$this->addItemAttribute('platform', $this->parse_game_value($pageBuffer, 'platform'));
			$this->addItemAttribute('perspective', $this->parse_game_value($pageBuffer, 'perspective'));
			$this->addItemAttribute('non-sport', $this->parse_game_value($pageBuffer, 'non-sport'));
			
			$release_date = $this->parse_game_value($pageBuffer, 'released');
			$timestamp = parse_mobygames_release_date($release_date);
    		$date = date('d/m/Y', $timestamp);
    		
    		$this->addItemAttribute('gamepbdate', $date);
    		
			// Get Description
			if (preg_match(";<h2 class=\"m5\">Description</h2>" .
						   "(.*?)<div" .
						   ";si",
						   $pageBuffer, $matches))
			{
				$description = $matches[1];
				$description = preg_replace(":\s*<br>\s*$:si", "", $description);
				$description = preg_replace(":\s*<br>\s*:si", "\n", $description);
				$description = preg_replace(":<p>\s*(.*?)\s*</p>:si",
											"\\1\n",
											$description);
				$description = preg_replace(":\s*<p>\s*:si", "\n", $description);
				$description = trim($description);

				$this->addItemAttribute('description', $description); 
			}
		
			$coverImages = $this->fetchURI("http://www.mobygames.com/game/covers/p,".$search_attributes_r['mgpltfrmid']."/gameId,".$search_attributes_r['mobygameid']);
			
			//game/genesis/phantasy-star-iii-generations-of-doom/cover-art/gameCoverId,22908/
			if (preg_match_all("!<a href=\"/game/".$search_attributes_r['mgpltfrmid']."/".$search_attributes_r['mobygameid']."/cover-art/gameCoverId,([0-9]+)/\">!", $coverImages, $matches))
			{
				for($i=0; $i<count($matches[1]); $i++)
				{
					//	http://www.mobygames.com/game/dos/spear-of-destiny/cover-art/gameCoverId,12676/
					$coverImage = $this->fetchURI("http://www.mobygames.com/game/".$search_attributes_r['mgpltfrmid']."/".$search_attributes_r['mobygameid']."/cover-art/gameCoverId,".$matches[1][$i]."/");
					
					//<img alt="DOS Front Cover" border="0" src="http://www.mobygames.com/images/covers/large/1021808019-00.jpg" height="767" width="640" >
					if (preg_match("!src=\"http://www.mobygames.com/images/covers/large/([^\"]+)\"!", $coverImage, $matches2))
					{
						$this->addItemAttribute('imageurl', "http://www.mobygames.com/images/covers/large/".$matches2[1]);
					}
				}
			}
			
			$techInfoPage = $this->fetchURI("http://www.mobygames.com/game/".$search_attributes_r['mgpltfrmid']."/".$search_attributes_r['mobygameid']."/techinfo");
			if($techInfoPage!==FALSE)
			{
				//<table SUMMARY="Tech-Info Notes" border=0 cellpadding=0 cellspacing=0>
				if (preg_match(":<table SUMMARY=\"Tech-Info Notes\".*?</table>:si", $techInfoPage, $matches))
				{
					$techInfoPage = $matches[0];
					if(preg_match_all(";<tr.*?</tr>;si", $techInfoPage, $matches, PREG_PATTERN_ORDER))
					{
						for ($i=0; $i<count($matches[0]); $i++)
						{
							$row = $matches[0][$i];
							//<tr valign="middle">
							//<td  width="40%">Business&nbsp;Model</td>
							//<td  width="60%"><a href="/attribute/sheet/attributeId,124/p,2/">Commercial</a></td>
							//</tr>
							if (preg_match("!<tr.*?<td.*?>(.*?)</td.*?<td.*?>(.*?)</td.*?</tr>!si", $row, $row_matches))
							{
								$key = strtolower(preg_replace("/[\s]+/i", "_", trim($row_matches[1])));
								$value = $row_matches[2];
								
								//<a href="/attribute/sheet/attributeId,65/p,2/">
								if (preg_match_all("!<a href=\"/attribute/sheet/attributeId,([0-9]+)/p,([0-9]+)/\">!si",
												   $value,
												   $value_matches))
								{
									$value = $value_matches[1];
									
									$this->addItemAttribute($key, $value);
								}
							}
						}
					}
				}
			}
			
			//http://www.mobygames.com/game/rise-of-the-robots/rating-systems
			$ratingPage = $this->fetchURI("http://www.mobygames.com/game/".$search_attributes_r['mgpltfrmid']."/".$search_attributes_r['mobygameid']."/rating-systems");
			if($ratingPage!==FALSE)
			{
				//<table SUMMARY="Rating Categories and Descriptors" border=0 cellpadding=2 cellspacing=0>
				if (preg_match(":<table SUMMARY=\"Rating Categories and Descriptors\".*?</table>:si", $ratingPage, $matches))
				{
					$ratingPage = $matches[0];
					if(preg_match_all(";<tr.*?</tr>;si", $ratingPage, $matches, PREG_PATTERN_ORDER))
					{
						for ($i=0; $i<count($matches[0]); $i++)
						{
							$row = $matches[0][$i];
							
							//<tr><td>ELSPA Rating</td><td> : </td><td><em>unknown</em></td></tr>
							//<tr><td>OFLC Rating</td><td> : </td><td><img alt="MA15+" border="0" src="/images/i/11/26/50726.gif" height="20" width="20" >&nbsp;<a href="/attribute/sheet/attributeId,419/">MA15+</a></td></tr>
							if (preg_match("!<tr><td>(.*)</td><td>[\s]*:[\s]*</td><td>(.*)</td></tr>!i", $row, $row_matches))
							{
								$field = $row_matches[1];
								$value = $row_matches[2];

								// Some interesting ratings: ESRB, USK, ELSPA, OFLC Rating
								if (preg_match("/rating$/i", $field))
								{
									$rating_name = strtolower(preg_replace("/[\s]+/i", "_", trim($field)));
									
									//<a href="/attribute/sheet/attributeId,92/">Teen</a>
									if (preg_match("!<a href=\"/attribute/sheet/attributeId,([0-9]+)/\">!si",
												   $value,
												   $rating_matches))
									{
										$rating = $rating_matches[1];
										$this->addItemAttribute($rating_name, $rating);
									}
								}
							}
						}
					}
				}
			}
						
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	var $moby_platform_to_name =
	array(
		'linux' => "Linux",
		'dos' => "DOS",
		'windows' => "Windows",
		'pc-booster' => "PC Booster",
		'win3x' => "Windows 3.x",
		'playstation' => "PlayStation",
		'ps2' => "PlayStation 2",
		'dreamcast' => "Dreamcast",
		'n64' => "Nintendo 64",
		'gameboy' => "Game Boy",
		'gameboy-color' => "Game Boy Color",
		'gameboy-advance' => "Game Boy Advance",
		'xbox' => "Xbox",
		'gamecube' => "GaneCube",
		'snes' => "SNES",
		'genesis' => "Genesis",
		'jaguar' => "Jaguar",
		'lynx' => "Lynx",
		'amiga' => "Amiga",
		'sega-cd' => "Sega CD",
		'sega-32x' => "Sega 32X",
		'nes' => "NES",
		'saturn' => "Saturn",
		'atari-st' => "Atari ST",
		'game-gear' => "Game Gear",
		'sega-master-system' => "Sega Master System",
		'c64' => "Commodore 64",
		'atari-2600' => "Atari 2600",
		'colecovision' => "ColecoVision",
		'intellivision' => "Intellivision",
		'apple2' => "Apple II",
		'ngage' => "N-Gage",
		'atari-5200' => "Atari 5200",
		'atari-7800' => "Atari 7800",
		'3do' => "3DO",
		'vectrex' => "Vectrex",
		);
}
?>