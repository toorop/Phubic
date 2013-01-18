<?php
/*
Copyright 2013 Stéphane Depierrepont (aka Toorop) toorop@toorop.fr

Licensed under the Apache License, Version 2.0 (the "License"); you may not
use this file except in compliance with the License. You may obtain a copy of
the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
License for the specific language governing permissions and limitations under
the License.
*/


############################################################################
#  Edit  config below and run :
#  php removeFile.php
############################################################################

$hubicLogin='Your login here';
$hubicPasswd='Your hubic passwd here';
$fileToRemove='path to file (on Hubic) to remove';

// If your are not running this script under *nix change $tempdir to writtable dir
$tempDir='/tmp';

############################################################################
#  DO NOT EDIT AFTER
############################################################################

require_once'../src/phubic.php';
$config = array('login'=>$hubicLogin,'passwd'=>$hubicPasswd, 'tempDir'=>$tempDir);
$hubic=new Phubic($config);
if($hubic->removeFile($fileToRemove))
    echo "\n".$fileToRemove.' removed !';
