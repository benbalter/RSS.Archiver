<?php
/* -----------------[  Initialization ] ----------------- */
function gimme_time(){
		$time = explode(' ', microtime());
		return $time[1] + $time[0];
	} 
$GLOBALS['startTime'] = gimme_time();

function load_time($start){
	$finish = gimme_time();
	return round(($finish - $start), 4);
}
 
//debug mode
if ($_GET['debug'] != 1) ob_start();

//grab mysql_functions
require_once('config.php');
require_once('mysql_functions.php');

//Grab Magpie
define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');
require_once('magpierss-0.72/rss_fetch.inc');

//Grab MySQL
$connection = mysql_connect(MYSQL_SERVER, MYSQL_USER, MYSQL_PASS) or die("Could not connect: $mysql_error()");
$db_select = mysql_select_db(MYSQL_DB) or die("database select error");

/* -----------------[  RSS Functions ] ----------------- */

function grab_newest_post($feedID) {
	$rss = fetch_rss(get_url($feedID));
	$posts = $rss->items;
	$title = $posts[0]['title'];
	$link = $posts[0]['link'];
	$content = $posts[0]['content']['encoded'];
	if (strlen($content)==0) $content = $posts[0]['summary'];
	if (strlen($content)==0) $content = $posts[0]['description'];
	
	$timestamp = $posts[0]['atom']['updated'];
	if (strlen($timestamp) == 0) $timestamp = $posts[0]['pubdate'];
	if (strlen($timestamp) == 0) $timestamp = date('Y-m-d H:i:s');
	else $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
	
	return array("Title" => $title, "Link" => $link, "Content" => $content, "TimeStamp" => $timestamp);
}

function check_feed($feedID) {
	$npost = grab_newest_post($feedID);
	$lpost = grab_last_post($feedID);
	if (strlen($npost['Title']) > 0 && !same_post($npost,$lpost)) {
		$postID = add_post($feedID, $npost);
		notify($postID);
		return true;
	}
	return false;
} 

function snapshot($url) {
	$curl_handle=curl_init();
	curl_setopt($curl_handle,CURLOPT_URL,"$url");
	curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
	$content = curl_exec($curl_handle);
	curl_close($curl_handle);
	return getTextBetweenTags("body",$content);
}

function getTextBetweenTags($tag, $html) {

	$start = strpos($html, "<$tag");
	$html = substr($html, $start);
	$start = strpos($html, ">")+1;
	$stop = strrpos($html,"</$tag>");
	return substr($html,$start,$stop-$start);

}
 
/* -----------------[  Revisioning Functions ] ----------------- */

function get_parent($url) {
	$parent = mysql_row_array(mysql_select("Storage",array("Link"=>$url),null,array("PostID"=>"ASC"),1));
	if ($parent) return $parent['PostID'];
	return false;
	}

function get_children($postID) {
	return mysql_array(mysql_select("Storage",array("ParentID"=>$postID),null,array("PostID"=>"DESC")));
}

function get_family($postID) {
	$post = get_post($postID);
	$parent = get_post(get_parent($post['Link']));
	if (sizeof($parent) == 0) return false;
	$sibs = get_children($parent['PostID']);
	unset($sibs[$postID]);  
	if ($post['ParentID'] != null) $sibs[$post['ParentID']] = get_post($post['ParentID']);
	return $sibs;
}

function get_oldest_sibling($postID) {
	$sib = get_family($postID);
	$keys = array_keys($sib);
	return $sib[$keys[0]];
}

function set_parent($postID) {
	$post=get_post($postID);
	$parent = get_parent($post['Link']);
	if ($parent == $postID) {
			$sql = "UPDATE `Storage` SET `ParentID` = NULL WHERE `PostID` = '$postID'";
			mysql_query($sql);
	} else {
		mysql_update("Storage",array("ParentID"=>$parent),array("PostID"=>$postID));
		echo "<p>$postID set as $parent.</p>";
	}
}

/* -----------------[  Comparison Functions ] ----------------- */

function compare($post1,$post2) {
	include_once "text_diff/Diff.php";
    include_once "text_diff/Renderer.php";
    include_once "text_diff/inline.php";	
	$diff = &new Text_Diff("auto",array(explode("\n",$post2),explode("\n",$post1)));
	$renderer = &new Text_Diff_Renderer_inline();
	$compare = $renderer->render($diff);
	$content = (strlen($compare)>0) ? $compare : $post1; 
	return html_entity_decode($content);
}
	
function compare_to_last($postID) {
	$post = get_post($postID);
	$sib = get_post(get_oldest_sibling($postID));
	return compare($post['Content'],$sib['Content']);
}

function same_post($npost,$lpost) {
	if (	substr($lpost['Content'],0,strlen('<!-- NO REFRESH -->')) == "<!-- NO REFRESH -->" &&  $lpost['Title'] == $npost['Title']) return true;
	foreach ($npost as $key => $value) {
		if (strcmp($value, $lpost[$key]) != 0) {
			echo "<p>$value != " . $lpost[$key] . "</p";
			return false;
		}
	}
	return true;
}

/* -----------------[  Data Retrival Functions ] ----------------- */

function grab_last_post($feedID) {
	return mysql_row_array(mysql_select("Storage",array("FeedID"=>$feedID),null,array("PostID"=>"DESC"),1));	
}

function get_posts($feedID,$thread=0) {
	$posts = mysql_array(mysql_select("Storage",array("FeedID"=>$feedID),null,array("PostID"=>"DESC")));
	if ($thread==0) return $posts;
	$removes = array();
	foreach ($posts as &$post) {
		$sibs = get_family($post['PostID']);
		foreach ($sibs as $sib) unset($posts[$sib['PostID']]);
	}
	return $posts;
}

function get_feed($feedID) {
	return mysql_row_array(mysql_select("Feeds", array("FeedID"=>$feedID)));
}

function get_title($feedID) {
	$feed = get_feed($feedID);
	if ($feed) return $feed['Title'];
	return false;
}

function get_post($postID) {
	return mysql_row_array(mysql_select("Storage",array("PostID"=>$postID)));
}

function add_post($feedID, $npost) {
	$data = array(	"FeedID" => $feedID,
					"Timestamp" => $npost['TimeStamp'],
					"Title" =>	$npost['Title'],
					"Link" => $npost['Link'],
					"Content" => $npost['Content']
				);
	if ($parentID = get_parent($npost['Link'])) $data = array_merge($data,array("ParentID"=>$parentID));	
	if (strlen($npost['Content']) == 0) $data['Content'] = "<!-- NO REFRESH -->" . snapshot($npost['Link']);
	if ($ID = mysql_insert("Storage",$data)) {
		echo "<a href='". $npost['Link'] . "'>" . $npost['Title'] . "</a> Added! <br />";
		return $ID;
	} else {
		return false;
	}
}

function get_url($feedID) {
	$feed = get_feed($feedID);
	if ($feed) return $feed['Feed'];
	return false;
}

function get_feeds() {
	return mysql_array(mysql_select("Feeds"));
}

/* -----------------[  Timestamp Functions ] ----------------- */

function ap_style_month_abbr($date) {
  switch(intval(date("n", $date))) {
    case 1; case 2; case 3; case 4; case 8; case 10; case 11; case 12;
      return date("M", $date) . ".";
      break;
    case 5; case 6; case 7;
      return date("F", $date);
      break;
    case 9;
      return "Sept.";
      break;
  }
}
function ap_style_time_suffix($me_want_time) {
   $me_want_time = str_replace('am', 'a.m.', $me_want_time);
   $me_want_time = str_replace('pm', 'p.m.', $me_want_time);
	return $me_want_time;
}

function timestamp($postID, $format=1) {
	$post = get_post($postID);
	$timestamp = strtotime($post['TimeStamp']);
	
	if ($format) $fdate = "<span class='date'>";
	if (date('m.d.y') == date('m.d.y',$timestamp)) { // Today
		$fdate .= "Today";
	} else if (date('m.d.y',strtotime('-1 day')) == date('m.d.y',$timestamp)) { // Yesterday
		$fdate .= "Yesterday";
	} else if (date('m.d.y',strtotime('-2 days')) == date('m.d.y',$timestamp)) { //two days ago
		$fdate .= date('l',$timestamp);
	} else {
		$fdate .= ap_style_month_abbr($timestamp).date('  j',$timestamp);
		if (date('Y') != date('Y',$timestamp)) $fdate .= ", " . date('Y',$timestamp);
	}
	if ($format) $fdate .= "</span>";
	
	if ($format) $fdate .= " <span class='pipe'>|</span>";
    $fdate .= " ";
	if ($format) $fdate .= "<span class='time'>";
	$fdate .= date('g:i a',$timestamp);
	if ($format) $fdate .= "</span>";
	$fdate = ap_style_time_suffix($fdate);
	return $fdate;
}

/* -----------------[  E-Mail Functions ] ----------------- */

function notify($postID) {
	
	if ( !NOTIFICATIONS )
		return false;
		
	$subject = $feed . " Update: " . stripslashes($post['Title']);
	$post = get_post($postID);
	$feed = get_title($post['FeedID']);
	$body = "<b>Feed:</b> " . $feed . "<br />";
	$body .= "<b>Title:</b> " . $post['Title'] . "<br />";
	$body .= "<b>Link:</b> <a href='" . $post['Link'] . "'>" . $post['Link'] . "</a><br />";
	$body .= "<b>Cache:</b> <a href=". SCRIPT_URL ."'?postID=" . $post['PostID'] . "'>". SCRIPT_URL ."?postID=" . $post['PostID'] . "</a><br />";
	$body .= "<b>Posted:</b> " . timestamp($postID) . "<br />";
	$body .= "<hr />";
	$body .= nl2br(compare_to_last($postID));
	
	if ( GMAIL ) {

		require_once("phpgmailer/class.phpgmailer.php");
		$mail = new PHPGMailer();
		
		$mail->Username = EMAIL_USERNAME;
		$mail->Password = EMAIL_PASSWORD;
		$mail->From = EMAIL_USERNAME;
		$mail->FromName = EMAIL_NAME;
		$mail->Subject = $subject;
		$mail->AddAddress(EMAIL_TO);
		$mail->Body = $body;
		$mail->IsHTML(true);
		$mail->Send();
		
	} else {
	
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers .= 'From: ' . EMAIL_FROM .' <' . EMAIL_USERNAME . '>' . "\r\n";
		mail( EMAIL_TO, $subject, $body, $headers);

	}
	
	echo "Notification for <a href='". $post['Link'] . "'>" . $post['Title'] . "</a> Sent! <br />";
}

function debug_me($message) {

	$subject = "RSS Archiver Debug Info";
	
	if ( GMAIL ) {

		require_once("phpgmailer/class.phpgmailer.php");
		$mail = new PHPGMailer();

		$mail->Username = EMAIL_USERNAME;
		$mail->Password = EMAIL_PASSWORD;
		$mail->From = EMAIL_USERNAME;
		$mail->FromName = EMAIL_NAME;
		$mail->Subject = $subject;
		$mail->AddAddress(EMAIL_TO);
		$mail->Body = $message;
		$mail->IsHTML(true);
		$mail->Send(); 

	} else {
	
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers .= 'From: ' . EMAIL_FROM .' <' . EMAIL_USERNAME . '>' . "\r\n";
		mail( EMAIL_TO, $subject, $message, $headers);

	}
	
	echo "Debug information Sent. <br />";
}

/* -----------------[  Main Function ] ----------------- */

function fetch() {
	$results = 0; 
	$feeds = get_feeds();
	foreach ($feeds as $feed) {
		if (check_feed($feed['FeedID']) == TRUE) $results++;
	}
	echo $results . " new post";
	if ($results != 1) echo "s";
	echo " found.  <a href=''>fetch again</a>?<br />";
	//debug_me(ob_get_contents());
	if ($_GET['debug'] != 1) 
		ob_end_clean();
}
?>