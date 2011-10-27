-- Update von altem Newsletter-System
ALTER TABLE  `huffle_members` CHANGE  `uid`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `huffle_members` CHANGE  `first`  `registerdate` DATETIME NOT NULL;
ALTER TABLE  `huffle_members` ADD UNIQUE  (`mail`);
ALTER TABLE  `huffle_members` DROP  `last`;
ALTER TABLE  `huffle_news` CHANGE  `nid`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;