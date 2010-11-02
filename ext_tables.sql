#
# Table structure for table 'tx_dropboxapi_cache'
#
CREATE TABLE tx_dropboxapi_cache (
    crdate int(11) DEFAULT '0' NOT NULL,
    email varchar(80) DEFAULT '' NOT NULL,
    tokens tinyblob,

    PRIMARY KEY (email),
);