SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `bikes`;
CREATE TABLE `bikes` (
  `bikeNum` int(11) NOT NULL,
  `currentUser` int(11) DEFAULT NULL,
  `currentStand` int(11) DEFAULT NULL,
  `currentCode` int(11) NOT NULL,
  `note` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `geolocation`;
CREATE TABLE `geolocation` (
  `userId` int(10) unsigned NOT NULL,
  `longitude` float NOT NULL,
  `latitude` float NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `history`;
CREATE TABLE `history` (
  `userId` int(11) NOT NULL,
  `bikeNum` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `action` varchar(20) NOT NULL,
  `parameter` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `limits`;
CREATE TABLE `limits` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `userLimit` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `received`;
CREATE TABLE `received` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sms_uuid` varchar(60) NOT NULL,
  `sender` varchar(20) NOT NULL,
  `receive_time` varchar(20) NOT NULL,
  `sms_text` varchar(200) NOT NULL,
  `IP` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `registration`;
CREATE TABLE `registration` (
  `userId` int(11) NOT NULL,
  `userKey` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sent`;
CREATE TABLE `sent` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `number` varchar(20) NOT NULL,
  `text` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `userId` int(10) unsigned NOT NULL,
  `sessionId` varchar(256) NOT NULL,
  `timeStamp` varchar(256) NOT NULL,
  UNIQUE KEY `userId` (`userId`),
  KEY `sessionId` (`sessionId`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `stands`;
CREATE TABLE `stands` (
  `standId` int(11) NOT NULL AUTO_INCREMENT,
  `standName` varchar(50) NOT NULL,
  `standDescription` varchar(255) NOT NULL,
  `standPhoto` varchar(255) NOT NULL,
  `serviceTag` int(10) NOT NULL,
  `placeName` varchar(50) NOT NULL,
  `longitude` float NOT NULL,
  `latitude` float NOT NULL,
  PRIMARY KEY (`standId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `userName` varchar(50) NOT NULL,
  `password` text NOT NULL,
  `mail` varchar(30) NOT NULL,
  `number` varchar(30) NOT NULL,
  `privileges` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`userId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- 2014-10-05 21:13:47
