ALTER TABLE `statuses` ADD `Address` VARCHAR(128) NOT NULL DEFAULT '' AFTER `DateTime`;
ALTER TABLE `statuses` CHANGE `ObjectID` `ParentID` INT(11) NOT NULL;
ALTER TABLE `statuses` ADD `ParentName` VARCHAR( 32 ) NOT NULL ;

ALTER TABLE `objects` ADD `GiftsGiven` TINYINT(4) NOT NULL DEFAULT '0' AFTER `Handshake`;
ALTER TABLE `objects` ADD `HasCertificate` TINYINT NOT NULL DEFAULT '0' AFTER `GiftsGiven`;
ALTER TABLE `objects` ADD `AgentFee` DECIMAL(10,2) NOT NULL DEFAULT '0' ;
ALTER TABLE `objects` ADD `MediatorID` INT NOT NULL DEFAULT '1' ;

ALTER TABLE `newbuildings` ADD `RightsSourceID` INT NOT NULL DEFAULT '1' , ADD `RightsTransmissionID` INT NOT NULL DEFAULT '1' ;
