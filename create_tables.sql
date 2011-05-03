--
-- Table structure for table `feeds`
--

CREATE TABLE IF NOT EXISTS `feeds` (
  `FeedID` int(11) NOT NULL AUTO_INCREMENT,
  `Feed` tinytext NOT NULL,
  `Title` tinytext NOT NULL,
  PRIMARY KEY (`FeedID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8 ;

-- --------------------------------------------------------

--
-- Table structure for table `storage`
--

CREATE TABLE IF NOT EXISTS `storage` (
  `PostID` int(11) NOT NULL AUTO_INCREMENT,
  `FeedID` int(11) NOT NULL DEFAULT '0',
  `TimeStamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `Title` tinytext NOT NULL,
  `Link` tinytext NOT NULL,
  `Content` text NOT NULL,
  `ParentID` int(11) DEFAULT NULL,
  PRIMARY KEY (`PostID`),
  KEY `ParentID` (`ParentID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=31 ;
