# PHP-AlexaSkill-Framework
Allows you to create Skills for Alexa utilizing PHP

## Installation and usage
* Create a Domain that is secured via HTTPS
* Put the /src/index.php in the rootfolder of this webdirectory.
* Create 2 Folders: "certs" and "intents" right beside the index.php
* Make sure the certs Folder is writeable by the PHP process as it is needed to cache amazons certificates.
* Create the a Skill in the Amazon Developers Section https://developer.amazon.com/edw/home.html#/skills
* Get the SkillID of the newly created Skill and create a Folder IN the intents Folder that is named after the SkillID.
* Put another "index.php" named File into that Folder and develop your Skill. (See the example in this Repo /examples)

## Notes
* You can run multiple Skills on one single Framework, because the Framework routes the Requests via the SkillID into the correct Folder.
* This Framework is written with the newest Amazon Guidelines in mind and does validate the SSL Requests as well as the Chain URL and the Timestamps, that it receives from the Amazon Alexa API.
* This Framework is fairly minimal and has almost no overhead. If you need Databases or anything else for that matter, you will need to implement it yourself.
