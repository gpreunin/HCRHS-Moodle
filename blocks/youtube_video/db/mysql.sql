CREATE TABLE prefix_block_youtube_video (
 `id` int(11) NOT NULL auto_increment,
 `courseid` int(11) NOT NULL default 0,
 `title` varchar(64) NOT NULL default '',
 `description` text NOT NULL default '',
 `url` varchar(255) NOT NULL default '',
 `shared` int(1) NOT NULL default 0,
  PRIMARY KEY (`id`)
) TYPE=MyISAM COMMENT='Contains youtube video data used by the youtube video block.';
