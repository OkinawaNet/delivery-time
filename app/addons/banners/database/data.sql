REPLACE INTO ?:banners (`banner_id`, `status`, `type`, `target`, `localization`, `timestamp`) VALUES(7, 'A', 'G', 'T', '', UNIX_TIMESTAMP(NOW()) - 3000);
REPLACE INTO ?:banners (`banner_id`, `status`, `type`, `target`, `localization`, `timestamp`) VALUES(8, 'A', 'G', 'T', '', UNIX_TIMESTAMP(NOW()) - 3000);

REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(16, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()), 10);
REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(17, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()), 20);
REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(18, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()), 30);
REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(19, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()), 40);
REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(6, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()) - 1000, 0);
REPLACE INTO ?:banners (banner_id, status, type, target, timestamp, position) VALUES(9, 'A', 'G', 'T', UNIX_TIMESTAMP(NOW()) - 1000, 0);

REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1076, 'gift_certificate.png', 1200, 136);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1077, 'holiday_gift.png', 900, 175);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1300, 'banner-en-sale-40-80.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1301, 'banner-en-xbox360.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1302, 'banner-en-point.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1303, 'banner-en-girl.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1304, 'banner_en_free_ship_lies-pz.png', 434, 185);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1305, 'banner_en_pickup_ok56-7h.png', 434, 185);

REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(16, 'promo', 1076, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(18, 'promo', 1077, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(35, 'promo', 1300, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(36, 'promo', 1301, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(37, 'promo', 1302, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(38, 'promo', 1303, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(39, 'promo', 1304, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(40, 'promo', 1305, 0, 'M', 0);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1300, 'banner-en-sale-40-80.ru.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1301, 'banner-en-xbox360.ru.jpg', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1302, 'banner-en-point.ru.jpg', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1303, 'banner-en-girl.ru.jpg', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1304, 'banner_en_free_ship_lies-pz.ru.png', 434, 185);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1305, 'banner_en_pickup_ok56-7h.ru.png', 434, 185);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1360, 'banner-ru-sale-40-80.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1361, 'banner-ru-xbox360.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1362, 'banner-ru-point.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1363, 'banner-ru-girl.png', 740, 395);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1364, 'banner_ru_free_ship_lies-pz.png', 434, 185);
REPLACE INTO ?:images (`image_id`, `image_path`, `image_x`, `image_y`) VALUES(1365, 'banner_ru_pickup_ok56-7h.png', 434, 185);

REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(41, 'promo', 1360, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(42, 'promo', 1361, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(43, 'promo', 1362, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(44, 'promo', 1363, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(45, 'promo', 1364, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(46, 'promo', 1365, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(47, 'promo', 1076, 0, 'M', 0);
REPLACE INTO ?:images_links (`object_id`, `object_type`, `image_id`, `detailed_id`, `type`, `position`) VALUES(48, 'promo', 1077, 0, 'M', 0);
