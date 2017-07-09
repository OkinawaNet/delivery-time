
CREATE TABLE IF NOT EXISTS `?:rus_cities` (
  `city_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` varchar(2) NOT NULL,
  `state_code` varchar(8) NOT NULL DEFAULT '',
  `city_code` varchar(32) NOT NULL DEFAULT '',
  `status` char(1) NOT NULL DEFAULT 'A',
  `sdek_city_code` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`city_id`),
  KEY `state_code` (`state_code`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1019 ;
