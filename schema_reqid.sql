DROP TABLE IF EXISTS groups;
	CREATE TABLE groups (
	    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	    name VARCHAR(255) NOT NULL,
	    PRIMARY KEY (id),
	    UNIQUE KEY uk_groups_name (name)
	) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AUTO_INCREMENT=1
	  COMMENT 'Usenet groups for request tracking';

	INSERT INTO groups (name) VALUES ('alt.binaries.boneless');
	INSERT INTO groups (name) VALUES ('alt.binaries.cd.image');
	INSERT INTO groups (name) VALUES ('alt.binaries.console.ps3');
	INSERT INTO groups (name) VALUES ('alt.binaries.erotica');
	INSERT INTO groups (name) VALUES ('alt.binaries.games.nintendods');
	INSERT INTO groups (name) VALUES ('alt.binaries.games.wii');
	INSERT INTO groups (name) VALUES ('alt.binaries.games.xbox360');
	INSERT INTO groups (name) VALUES ('alt.binaries.inner-sanctum');
	INSERT INTO groups (name) VALUES ('alt.binaries.mom');
	INSERT INTO groups (name) VALUES ('alt.binaries.moovee');
	INSERT INTO groups (name) VALUES ('alt.binaries.movies.divx');
	INSERT INTO groups (name) VALUES ('alt.binaries.sony.psp');
	INSERT INTO groups (name) VALUES ('alt.binaries.sounds.mp3.complete_cd');
	INSERT INTO groups (name) VALUES ('alt.binaries.sounds.flac');
	INSERT INTO groups (name) VALUES ('alt.binaries.teevee');
	INSERT INTO groups (name) VALUES ('alt.binaries.warez');

	DROP TABLE IF EXISTS predb;
	CREATE TABLE predb (
	    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	    title VARCHAR(255) NOT NULL,
	    groupname VARCHAR(255) NOT NULL,
	    reqid INT UNSIGNED NOT NULL DEFAULT 0,
	    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	    PRIMARY KEY (id),
	    KEY idx_predb_title (title),
	    KEY idx_predb_reqid (reqid),
	    KEY idx_predb_groupname (groupname),
	    CONSTRAINT fk_predb_groupname FOREIGN KEY (groupname) REFERENCES groups (name)
	        ON DELETE CASCADE ON UPDATE CASCADE
	) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AUTO_INCREMENT=1
	  COMMENT 'Pre-database request IDs';
