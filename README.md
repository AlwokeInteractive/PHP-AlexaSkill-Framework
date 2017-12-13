# PHP-AlexaSkill-Framework
Allows you to create Skills for Alexa utilizing PHP

## Installtion and usage
* Create a Domain that is secured via HTTPS
* Put the index.php in the rootfolder of this webdirectory.
* Create 2 Folders: "certs" and "intents" right beside the index.php
* Make sure the certs Folder is writeable by the PHP process
* Create the a Skill in the Amazon Developers Section https://developer.amazon.com/edw/home.html#/skills
* Get the SkillID of the newly created Skill and create a Folder IN the intents Folder that is named after the SkillID.
* Put another "index.php" named File into that Folder and develop your Skill. (See the example in this Repo)
