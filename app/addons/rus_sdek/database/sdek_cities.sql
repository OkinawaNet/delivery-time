
DROP TABLE IF EXISTS rus_cities_sdek;
CREATE TABLE `?:rus_cities_sdek` (
  `city_id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` varchar(2) NOT NULL,
  `state_code` varchar(8) NOT NULL DEFAULT '',
  `city_code` varchar(32) NOT NULL DEFAULT '',
  `status` char(1) NOT NULL DEFAULT 'A',
  PRIMARY KEY (`city_id`),
  KEY `state_code` (`state_code`)
) ENGINE=MyISAM AUTO_INCREMENT=4423 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `?:rus_city_sdek_descriptions`;
CREATE TABLE `?:rus_city_sdek_descriptions` (
  `city_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lang_code` char(2) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`city_id`,`lang_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
