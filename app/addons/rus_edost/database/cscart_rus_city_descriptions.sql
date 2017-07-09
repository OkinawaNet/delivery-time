
CREATE TABLE IF NOT EXISTS `?:rus_city_descriptions` (
  `city_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `lang_code` char(2) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`city_id`,`lang_code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
