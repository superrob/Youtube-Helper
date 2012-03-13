<?
/*
	A PHP class which can help with parsing comments on Youtube videos
    Copyright (C) 2011 Robin Madsen (http://robserob.dk)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class youtubeHelper {
	public $comments = array();
	public $youtubeID = "";
	public $youtubeUser = "";
	public $mysqlEnabled = false; // Is mysql support enabled to store subs?
	
	function __construct( ){ }
	
	// Parses an simpleXML object into an PHP array to help ease of use.
	public function xml2phpArray($xml, $namespaces) {
		$iter = 0;
		$arr = array();
		foreach ($namespaces as $namespace => $namespaceUrl) {
			foreach ($xml->children($namespaceUrl) as $b) {
				$a = $b->getName();
				if ($b->children($namespaceUrl)) {
					$arr[$a][$iter] = array();
					$arr[$a][$iter] = $this->xml2phpArray($b, $namespaces, $arr[$a][$iter]);
				} else {
					$arr[$a] = trim($b[0]);
				}
				$iter++;
			}
		}
		return $arr;
	}	
	// Used in order to find a string inside the first occurances of two other strings. 
	// For example
	// If we want to find "apple" inside of the string "i want an apple for breakfast" we use it the following way
	// findPart('an ', ' for', 'i want an apple for breakfast');
	// And it will return apple.
	public function findPart($from, $to, $text) {
		$posstart = strpos($text, $from);
		$posstart = $posstart + strlen($from);
		$cut = substr($text, $posstart);
		$posend = strpos($cut, $to);
		return substr($cut, 0, $posend);
	}
	// Returns the number of comments.
	public function numComments() {
		return count($this->comments);
	}
	
	// Loads all comments from the current youtubeID into the comments array.
	public function loadComments($shouldReturnArray = false) {
		if ($this->youtubeID != "") {
			$index = 1; // Start at index 1.
			$moreToGet = true;
			while ($moreToGet) {
				$url = "https://gdata.youtube.com/feeds/api/videos/".$this->youtubeID."/comments?max-results=50&start-index=".$index;
				$xml = simplexml_load_file($url);
				$namespaces = array_merge(array('' => ''), $xml->getDocNamespaces(true));
				$temp = $this->xml2phpArray($xml, $namespaces);
				if (count($temp['entry']) > 0) {
					foreach($temp['entry'] as $tempe) {
						$count++;
						$this->comments[] = array("content" => $tempe['content'], "author" => $tempe['author'][9]['name']);
					}
					$index += 50;
				} else {
					$moreToGet = false;				
				}
			}
			if ($shouldReturnArray) {
				return $this->comments;
			}
		} else {
			return false; // Youtube video ID not set.
		}
	}
	
	// Filtrates all double comments.
	public function removeDoubles($returnCount = false) {
		$tempNames = array(); // Temporary array which contains all the names allready gone through.
		$dob = 0; // The number of double comments which are returned if the returnCount parameter is true.
		foreach($this->comments as $key => $comment) {
			if (!in_array($comment['author'], $tempNames)) { // Have we allready encountered this person?
				$tempNames[] = $comment['author']; // The person is unique. Add him to the list of allready used names.
			} else {
				unset($this->comments[$key]); // The user is not unique, remove this comment from the list.
				$dob++;
			}
		}
		$this->comments = array_reverse(array_reverse($this->comments)); // Fixes the indexes destoyed by the filtration.
		unset($tempNames); // Unset the temporary array.
		if ($returnCount) {
			return $dob;
		}
	}
	
	// Checks if the $user is a subscriber to the current youtubeUser.
	public function checkSubscriber($user) {
		if ($user != "" and $this->youtubeUser != "") {
			$index = 1; // Index
			$foundChannel = false;
			$total = 0;
			$searchForMore = true;
			while ($searchForMore) {		
				$url = "http://gdata.youtube.com/feeds/api/users/".$user."/subscriptions?max-results=50&start-index=".$index;	
				if ($content = @file_get_contents($url)) {
					if ($total == 0) {
						$total = $this->findPart("<openSearch:totalResults>", "</openSearch:totalResults>", $content);
					}
					$searchForMore = true;			
					if (eregi("Activity of : ". $this->youtubeUser, $content)) {
						$foundChannel = true;
						$searchForMore = false;
						if ($this->mysqlEnabled) {
							mysql_query("insert into subs (user) values ('".$user."')");
						}
					} else {
						$index += 50;
						if ($index > $total) {
							$searchForMore = false;
						}
					}
				} else {
					$searchForMore = false;
				}
			}
			return $foundChannel;
		} else {
			return true; // We dont have enough information. Just say hes a subscriber anyway.
		}
	}
	
	// Checks all commenters for subscribtion.
	public function checkAllSubs ($shouldReturnCount = true, $shouldPrintNumber = false, $prependString = "") {
		$nosub = 0;
		$count = 1;
		foreach($this->comments as $key => $comment) {
			if ($shouldPrintNumber) {
				echo $prependString . " " . $count . "\r";
			}			
			$count++;
			if ($this->mysqlEnabled) {
				if (mysql_num_rows(mysql_query("select * from subs where user='".$comment['author']."'"))) {
					continue;
				}
			}
			if (!$this->checkSubscriber($comment['author'])) {
				$nosub++;
				unset($comments[$key]);
			}
		}
		$this->comments = array_reverse(array_reverse($this->comments)); // Fixes the indexes destoyed by the filtration.
		if ($shouldReturnCount) {
			return $nosub;
		}
	}
}
?>