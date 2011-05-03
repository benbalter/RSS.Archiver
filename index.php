<?php include('functions.php'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		<title>RSS.Archiver <?php
		if ($_GET['feedID']) {
			echo " | " . get_title($_GET['feedID']);
		} else if ($_GET['postID']) {
			$post = get_post($_GET['postID']);
			echo " | " . get_title($post['FeedID']) . " | " . $post['Title'];		
		}
		?></title>
		<link rel="stylesheet" type="text/css" href="style.css" media="all" />
		<script type="text/JavaScript">
			<!--
			function timedRefresh(timeoutPeriod) {
				setTimeout("location.reload(true);",timeoutPeriod);
			}
			//   -->
		</script>
		<script>
			<!--
			function wopen(url, name, w, h)
			{
			// Fudge factors for window decoration space.
			 // In my tests these work well on all platforms & browsers.
			w += 32;
			h += 96;
			 var win = window.open(url,
			  name,
			  'width=' + w + ', height=' + h + ', ' +
			  'location=no, menubar=no, ' +
			  'status=no, toolbar=no, scrollbars=no, resizable=no');
			 win.resizeTo(w, h);
			 win.focus();
			}
			// -->
		</script> 
	</head>
	<body>
	<h1><a href='./'>RSS<span class='dot'>.</span>Archiver</a></h1>
	<div class='refresh'><a href="fetch.php?debug=1" target="popup" onClick="wopen('fetch.php?debug=1', 'popup', 640, 180); return false;">refresh</a></div>
	<?php 

	$feedID = $_GET['feedID'];
	$postID = $_GET['postID'];
	
	if (!is_null($feedID)) { 
	$feed= get_feed($feedID);
	?>
		<h2><a href='?feedID=<?php echo $feed['FeedID']; ?>'><?php echo $feed['Title']; ?></a></h2>
		<ul>
		<?php
		$posts = get_posts($feedID,1);
		foreach ($posts as $post) { ?>
			<li>
				<a href='?postID=<?php echo $post['PostID']; ?>' title='View Cache of &quot;<?php echo $post['Title']; ?>&quot;'>
					<span class='title'><?php echo substr($post['Title'],0,100); ?><?php if (strlen($post['Title']) >100) echo "..."; ?></a></span>
				<span class='link'>[<a href='<?php echo $post['Link']; ?>' title='View Live Version of &quot;<?php echo $post['Title']; ?>&quot;'>L</a>]</span>
				<?php 
				$family = get_family($post['PostID']);
				if (sizeof($family) >0) {?>
				<span class='rev-thumb'>[<?php echo sizeof($family); ?>]</span>
				<?php }?>
				- <?php echo timestamp($post['PostID']); ?>
			</li>
		<?php } ?>
		</ul>
		<?php
	} elseif (!is_null($postID)) {
		$post = get_post($postID); ?>
		<h2><a href='<?php echo $post['Link']; ?>'><?php echo $post['Title']; ?></a></h2>
		<?php $family = get_family($postID); ?>
		<?php if (sizeof($family) > 0) { ?>
			<div class='revision-label'>
				<span class='number'>
					<?php echo sizeof($family); ?>
				</span> Revisions: 
			</div>
			<div class='revisions'>
				<?php 
				foreach ($family as $sibling) { ?>
				<span class='rev-date'>
					<?php echo timestamp($sibling['PostID'],1); ?></a>
				</span>
				<span class='rev-link'>
					[<a href='?postID=<?php echo $sibling['PostID']; ?>'>View</a>] 
					[<a href='?postID=<?php echo $post['PostID']; ?>&compare_to=<?php echo $sibling['PostID']; ?>'>Compare</a>] 
				</span>
				<?php } ?>
			</div>
			
		<?php } ?>
		<p class='byline'><a href='?feedID=<?php echo $post['FeedID']; ?>'><?php echo get_title($post['FeedID']); ?></a> <span class='pipe'>|</span> <?php echo timestamp($postID); ?></p>
		<div class='clear'> &nbsp </div>
		<?php 
		if (!$_GET['compare_to']) {
			echo $post['Content']; 
		} else {
			$sib = get_post($_GET['compare_to']);
			echo compare($post['Content'],$sib['Content']);
		}		
		?>
		<?php
	} else {
		$feeds = get_feeds();
		foreach ($feeds as $feed) { 
		?>
		<h2><a href='?feedID=<?php echo $feed['FeedID']; ?>'><?php echo $feed['Title']; ?></a></h2>
			<ul>
		<?php
			$posts = get_posts($feed['FeedID'],1);
			reset($posts);
			$i =0;
			while ($i<5 && list(, $post) = each($posts)) {
				?> 
			<li>
				<a href='?postID=<?php echo $post['PostID']; ?>' title='View Cache of &quot;<?php echo $post['Title']; ?>&quot;'>
					<span class='title'><?php echo substr($post['Title'],0,100); ?><?php if (strlen($post['Title']) >100) echo "..."; ?></a></span>
				<span class='link'>[<a href='<?php echo $post['Link']; ?>' title='View Live Version of &quot;<?php echo $post['Title']; ?>&quot;'>L</a>]</span>
				<?php 
				$family = get_family($post['PostID']);
				if (sizeof($family) >0) {?>
				<span class='rev-thumb'>[<?php echo sizeof($family); ?>]</span>
				<?php }?>
				- <?php echo timestamp($post['PostID']); ?>
			</li>
		<?php
			$i++;
			} ?>
		</ul>
		<?php
		}
	}
	?>
	</ul>
	<div class='footer'> 
	<p class='stats'>Generated in <?php echo load_time($GLOBALS['startTime']); ?> seconds using <?php echo $GLOBALS['qCount']; ?> queries</p>
	</div>
	<script type="text/JavaScript">
		timedRefresh(60000);
	</script>
	</body>
</html>