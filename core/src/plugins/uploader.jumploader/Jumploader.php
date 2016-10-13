<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Uploader\Processor;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsulation of the Jumploader Java applet (must be downloaded separately).
 * @package AjaXplorer_Plugins
 * @subpackage Uploader
 */
class Jumploader extends Plugin
{
    /**
     * Handle UTF8 Decoding
     *
     * @var bool
     */
    private static $skipDecoding = false;
    private static $remote = false;
    private static $wrapperIsRemote = false;
    private static $partitions = array();

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function getTemplate(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response){

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $confMaxSize = StatHelper::convertBytes(ConfService::getContextConf($ctx, "UPLOAD_MAX_SIZE", "uploader"));
        $UploadMaxSize = min(StatHelper::convertBytes(ini_get('upload_max_filesize')), StatHelper::convertBytes(ini_get('post_max_size')));
        if($confMaxSize != 0) $UploadMaxSize = min ($UploadMaxSize, $confMaxSize);
        $confTotalSize = ConfService::getContextConf($ctx, "UPLOAD_MAX_SIZE_TOTAL", "uploader");
        $confTotalNumber = ConfService::getContextConf($ctx, "UPLOAD_MAX_NUMBER", "uploader");

        $repository = $ctx->getRepository();
        $accessType = $repository->getAccessType();

        $partitionLength = $UploadMaxSize - 1000;

        if($accessType == "remotefs"){
            $maxFileLength = $UploadMaxSize;
        }else if($accessType == "ftp"){
            $partitionLength = $UploadMaxSize - 1000;
        }

        include($this->getBaseDir()."/jumploader_tpl.html");

    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Exception
     */
    public function preProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        $httpVars = $request->getParsedBody();

        if(!count($request->getUploadedFiles()) || isSet($httpVars["simple_uploader"]) || isset($httpVars["xhr_uploader"]) || isSet($httpVars["Filename"])){
            return;
        }
        
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $repository = $ctx->getRepository();
        $driver = $repository->getDriverInstance($ctx);

        if (method_exists($driver, "storeFileToCopy")) {
            self::$remote = true;
        }

        $wrapperName = MetaStreamWrapper::actualRepositoryWrapperClass(new AJXP_Node($ctx->getUrlBase()));
        if ($wrapperName == "ajxp.ftp" || $wrapperName == "ajxp.remotefs") {
            $this->logDebug("Skip decoding");
            self::$skipDecoding = true;
        }
        $this->logDebug("Stream is",$wrapperName);
        self::$wrapperIsRemote = call_user_func(array($wrapperName, "isRemote"));
        
        $this->logDebug("Jumploader HttpVars", $httpVars);


        $httpVars["dir"] = base64_decode(str_replace(" ","+",$httpVars["dir"]));
        $index = $httpVars["partitionIndex"];
        /** @var \Psr\Http\Message\UploadedFileInterface $uploadedFile */
        $uploadedFile = $request->getUploadedFiles()["userfile_0"];
        $realName = $uploadedFile->getClientFilename();
        //$realName = $fileVars["userfile_0"]["name"];

        /* if fileId is not set, request for cross-session resume (only if the protocol is not ftp)*/
        if (!isSet($httpVars["fileId"])) {
            $this->logDebug("Trying Cross-Session Resume request");

            $dir = InputFilter::decodeSecureMagic($httpVars["dir"]);
            $context = UserSelection::fromContext($ctx, $httpVars);
            $destStreamURL = $context->currentBaseUrl().$dir;
            $fileHash = md5($httpVars["fileName"]);

            if (!self::$remote) {
                $resumeIndexes = array ();
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destStreamURL));
                $it->setMaxDepth(0);
                while ($it->valid()) {
                    if (!$it->isDot()) {
                        $subPathName = $it->getSubPathName();
                        Logger::debug("Iterator SubPathName: " . $it->getSubPathName());
                        if (strstr($subPathName, $fileHash) != false) {
                            $explodedSubPathName = explode('.', $subPathName);
                            $resumeFileId = $explodedSubPathName[1];
                            $resumeIndexes[] = $explodedSubPathName[2];

                            $this->logDebug("Current Index: " . $explodedSubPathName[2]);
                        }
                    }
                    $it->next();
                }

                /* no valid temp file found. return. */
                if (empty ($resumeIndexes)){
                    $this->logDebug("No Cross-Session Resume request");
                    return;
                }

                Logger::debug("ResumeFileID: " . $resumeFileId);
                Logger::debug("Max Resume Index: " . max($resumeIndexes));
                $nextResumeIndex = max($resumeIndexes) + 1;
                Logger::debug("Next Resume Index: " . $nextResumeIndex);

                if (isSet($resumeFileId)) {
                    $this->logDebug("ResumeFileId is set. Returning values: fileId: " . $resumeFileId . ", partitionIndex: " . $nextResumeIndex);
                    $httpVars["resumeFileId"] = $resumeFileId;
                    $httpVars["resumePartitionIndex"] = $nextResumeIndex;
                }
            }
            return;
        }

        /* if the file has to be partitioned */
        if (isSet($httpVars["partitionCount"]) && intval($httpVars["partitionCount"]) > 1) {
            $this->logDebug("Partitioned upload");
            $fileId = $httpVars["fileId"];
            $fileHash = md5($realName);

            /* In order to enable cross-session resume, temp files must not depend on session.
             * Now named after and md5() of the original file name.
             */
            $this->logDebug("Filename: " . $realName . ", File hash: " . $fileHash);
            //$fileVars["userfile_0"]["name"] = "$fileHash.$fileId.$index";
            $newUp = new \Zend\Diactoros\UploadedFile($_FILES["userfile_0"]["tmp_name"], $uploadedFile->getSize(), $uploadedFile->getError(), "$fileHash.$fileId.$index");
            $request = $request->withUploadedFiles(["userfile_0" => $newUp]);

            $httpVars["lastPartition"] = false;
        }else{
            /*
             * If we wan to upload a folderUpload to folderServer
             * Temporarily,put all files in this folder to folderServer.
             * But a same file name may be existed in folderServer,
             * this can cause error of uploading.
             *
             * We rename this file by his relativePath. At the postProcess session, we will use this name
             * to copy to right location
             *
             */
            $file_tmp_md5 = md5($httpVars["relativePath"]);
            //$fileVars["userfile_0"]["name"] = $file_tmp_md5;
            $newUp = new \Zend\Diactoros\UploadedFile($_FILES["userfile_0"]["tmp_name"], $uploadedFile->getSize(), $uploadedFile->getError(), $file_tmp_md5);
            $request = $request->withUploadedFiles(["userfile_0" => $newUp]);

        }



        /* if we received the last partition */
        if (intval($index) == intval($httpVars["partitionCount"])-1) {
            $httpVars["lastPartition"] = true;
            $httpVars["partitionRealName"] = $realName;
        }

        $request = $request->withParsedBody($httpVars);

    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return void
     * @throws \Exception
     */
    public function postProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        $httpVars = $request->getParsedBody();
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) {
            return;
        }
        $response = $response->withHeader("Content-type", "text/plain");

        /* If set resumeFileId and resumePartitionIndex, cross-session resume is requested. */
        if (isSet($httpVars["resumeFileId"]) && isSet($httpVars["resumePartitionIndex"])) {
            $response->getBody()->write("fileId: " . $httpVars["resumeFileId"] . "\n");
            $response->getBody()->write("partitionIndex: " . $httpVars["resumePartitionIndex"]);
            return;
        }

        /*if (self::$skipDecoding) {

        }*/

        $result = $request->getAttribute("upload_process_result");

        if (isset($result["ERROR"])) {
            if (isset($httpVars["lastPartition"]) && isset($httpVars["partitionCount"])) {
                /* we get the stream url (where all the partitions have been uploaded so far) */

                $dir = InputFilter::decodeSecureMagic($httpVars["dir"]);
                $context = UserSelection::fromContext($request->getAttribute("ctx"), []);
                $destStreamURL = $context->currentBaseUrl().$dir."/";

                if ($httpVars["partitionCount"] > 1) {
                    /* we fetch the information that help us to construct the temp files name */
                    $fileId = $httpVars["fileId"];
                    $fileHash = md5($httpVars["fileName"]);

                    /* deletion of all the partitions that have been uploaded */
                    for ($i = 0; $i < $httpVars["partitionCount"]; $i++) {
                        if (file_exists($destStreamURL."$fileHash.$fileId.$i")) {
                            unlink($destStreamURL."$fileHash.$fileId.$i");
                        }
                    }
                } else {
                    $fileName = $httpVars["fileName"];
                    unlink($destStreamURL.$fileName);
                }
            }
            $response->getBody()->write("Error: ".$result["ERROR"]["MESSAGE"]);
            return;
        }

        if (!isSet($httpVars["partitionRealName"]) && !isSet($httpVars["lastPartition"])) {
            return ;
        }

        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $repository = $ctx->getRepository();
        $driver = $repository->getDriverInstance($ctx);

        if ($httpVars["lastPartition"]) {
            $dir = InputFilter::decodeSecureMagic($httpVars["dir"]);
            $context = UserSelection::fromContext($ctx, $httpVars);
            $destStreamURL = $context->currentBaseUrl().$dir."/";

            /* we check if the current file has a relative path (aka we want to upload an entire directory) */
            $this->logDebug("Now dispatching relativePath dest:", $httpVars["relativePath"]);
            $subs = explode("/", $httpVars["relativePath"]);
            $userfile_name = array_pop($subs);

            $folderForbidden = false;
            $all_in_place = true;
            $partitions_length = 0;
            $fileId = $httpVars["fileId"];
            $fileHash = md5($userfile_name);
            $partitionCount = $httpVars["partitionCount"];
            $fileLength = $_POST["fileLength"];

            /*
             *
             * Now, we supposed that access driver has already saved uploaded file in to
             * folderServer with file name is md5 relativePath value.
             * We try to copy this file to right location in recovery his name.
             *
             */
            $userfile_name = md5($httpVars["relativePath"]);

            if (self::$remote) {
                $partitions = array();
                $newPartitions = array();
                $index_first_partition = -1;
                $i = 0;
                do {
                    $currentFileName = $driver->getFileNameToCopy();
                    $partitions[] = $driver->getNextFileToCopy();

                    if ($index_first_partition < 0 && strstr($currentFileName, $fileHash) != false) {
                        $index_first_partition = $i;
                    } else if ($index_first_partition < 0) {
                        $newPartitions[] = array_pop($partitions);
                    }
                } while ($driver->hasFilesToCopy());
            }

            /* if partitionned */
            if ($partitionCount > 1) {
                if (self::$remote) {
                    for ($i = 0; $all_in_place && $i < $partitionCount; $i++) {
                        $partition_file = "$fileHash.$fileId.$i";
                        if ( strstr($partitions[$i]["name"], $partition_file) != false ) {
                            $partitions_length += filesize( $partitions[$i]["tmp_name"] );
                        } else { $all_in_place = false; }
                    }
                } else {
                    for ($i = 0; $all_in_place && $i < $partitionCount; $i++) {
                        $partition_file = $destStreamURL."$fileHash.$fileId.$i";
                        if ( file_exists( $partition_file ) ) {
                            $partitions_length += filesize( $partition_file );
                        } else { $all_in_place = false; }
                    }
                }
            } else {
                if (self::$remote) {
                    if ( strstr($newPartitions[count($newPartitions)-1]["name"], $userfile_name) != false) {
                        $partitions_length += filesize( $newPartitions[count($newPartitions)-1]["tmp_name"] );
                    }
                } else {
                    if (file_exists($destStreamURL.$userfile_name)) {
                        $partitions_length += filesize($destStreamURL.$userfile_name);
                    }
                }
            }

            if ( (!$all_in_place || $partitions_length != floatval($fileLength))) {
                echo "Error: Upload validation error!";
                /* we delete all the uploaded partitions */
                if ($httpVars["partitionCount"] > 1) {
                    for ($i = 0; $i < $partitionCount; $i++) {
                        if (file_exists($destStreamURL."$fileHash.$fileId.$i")) {
                            unlink($destStreamURL."$fileHash.$fileId.$i");
                        }
                    }
                } else {
                    $fileName = $httpVars["partitionRealName"];
                    unlink($destStreamURL.$fileName);
                }
                return;
            }

            if (count($subs) > 0 && !self::$remote) {
                $curDir = "";

                if (substr($curDir, -1) == "/") {
                    $curDir = substr($curDir, 0, -1);
                }

                // Create the folder tree as necessary
                foreach ($subs as $key => $spath) {
                    $messtmp="";
                    $dirname= InputFilter::decodeSecureMagic($spath, InputFilter::SANITIZE_FILENAME);
                    $dirname = substr($dirname, 0, ConfService::getContextConf($ctx, "NODENAME_MAX_LENGTH"));
                    //$this->filterUserSelectionToHidden(array($dirname));
                    if (StatHelper::isHidden($dirname)) {
                        $folderForbidden = true;
                        break;
                    }

                    if (file_exists($destStreamURL."$curDir/$dirname")) {
                        // if the folder exists, traverse
                        $this->logDebug("$curDir/$dirname existing, traversing for $userfile_name out of", $httpVars["relativePath"]);
                        $curDir .= "/".$dirname;
                        continue;
                    }

                    $this->logDebug($destStreamURL.$curDir);
                    $dirMode = 0775;
                    $chmodValue = $repository->getContextOption($ctx, "CHMOD_VALUE");
                    if (isSet($chmodValue) && $chmodValue != "") {
                        $dirMode = octdec(ltrim($chmodValue, "0"));
                        if ($dirMode & 0400) $dirMode |= 0100; // Owner is allowed to read, allow to list the directory
                        if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
                        if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
                    }
                    $url = $destStreamURL.$curDir."/".$dirname;
                    $old = umask(0);
                    mkdir($url, $dirMode);
                    umask($old);
                    Controller::applyHook("node.change", array(null, new AJXP_Node($url), false));
                    $curDir .= "/".$dirname;
                }
            }

            if (!$folderForbidden) {
                $fileId = $httpVars["fileId"];
                $this->logDebug("Should now rebuild file!", $httpVars);
                // Now move the final file to the right folder
                // Currently the file is at the base of the current
                $this->logDebug("PartitionRealName", $destStreamURL.$httpVars["partitionRealName"]);

                // Get file by name (md5 value)
                $relPath_md5 = InputFilter::decodeSecureMagic(md5($httpVars["relativePath"]));

                // original file name
                $relPath = InputFilter::decodeSecureMagic($httpVars["relativePath"]);

                $target = $destStreamURL;
                $target .= (self::$remote)? basename($relPath) : $relPath;

                /*
                *   $current is uploaded file with md5 value as his name
                *   we copy to $relPath and delete md5 file
                */

                $current = $destStreamURL.basename($relPath_md5);

                if ($httpVars["partitionCount"] > 1) {
                    if (self::$remote) {
                        $test = ApplicationState::getTemporaryFolder() ."/".$httpVars["partitionRealName"];
                        $newDest = fopen(ApplicationState::getTemporaryFolder() ."/".$httpVars["partitionRealName"], "w");
                        $newFile = array();
                        $length = 0;
                        for ($i = 0, $count = count($partitions); $i < $count; $i++) {
                            $currentFile = $partitions[$i];
                            $currentFileName = $currentFile["tmp_name"];
                            $part = fopen($currentFileName, "r");
                            if(is_resource($part)){
                                while (!feof($part)) {
                                    $length += fwrite($newDest, fread($part, 4096));
                                }
                                fclose($part);
                            }
                            unlink($currentFileName);
                        }
                        $newFile["type"] = $partitions[0]["type"];
                        $newFile["name"] = $httpVars["partitionRealName"];
                        $newFile["error"] = 0;
                        $newFile["size"] = $length;
                        $newFile["tmp_name"] = ApplicationState::getTemporaryFolder() ."/".$httpVars["partitionRealName"];
                        $newFile["destination"] = $partitions[0]["destination"];
                        $newPartitions[] = $newFile;
                    } else {
                        $current = $destStreamURL.$httpVars["partitionRealName"];
                        $newDest = fopen($current, "w");
                        $fileHash = md5($httpVars["partitionRealName"]);

                        for ($i = 0; $i < $httpVars["partitionCount"] ; $i++) {
                            $part = fopen($destStreamURL."$fileHash.$fileId.$i", "r");
                            if(is_resource($part)){
                                while (!feof($part)) {
                                    fwrite($newDest, fread($part, 4096));
                                }
                                fclose($part);
                            }
                            unlink($destStreamURL."$fileHash.$fileId.$i");
                        }

                    }
                    fclose($newDest);
                }

                if (!self::$remote && (!self::$wrapperIsRemote || $relPath != $httpVars["partitionRealName"])) {
                    if($current != $target){
                        $err = copy($current, $target);
                    }
                    else $err = true;
                } else {
                    for ($i=0, $count=count($newPartitions); $i<$count; $i++) {
                        $driver->storeFileToCopy($newPartitions[$i]);
                    }
                }

                if ($current != $target && $err !== false) {
                    if(!self::$remote) unlink($current);
                    Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                } else if ($current == $target) {
                    Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                }
            } else {
                // Remove the file, as it should not have been uploaded!
                //if(!self::$remote) unlink($current);
            }
        }
    }

    /**
     * @param $params
     * @return string
     */
    public function jumploaderInstallApplet($params)
    {
        if (is_file($this->getBaseDir()."/jumploader_z.jar")) {
            return "ERROR: The applet is already installed!";
        }
        $fileData = FileHelper::getRemoteContent("http://jumploader.com/jar/jumploader_z.jar");
        if (!is_writable($this->getBaseDir())) {
            file_put_contents(AJXP_CACHE_DIR."/jumploader_z.jar", $fileData);
            return "ERROR: The applet was downloaded, but the folder plugins/uploader.jumploader is not writeable. Applet is located in the cache folder, please put it manually in the plugin folder.";
        } else {
            file_put_contents($this->getBaseDir()."/jumploader_z.jar", $fileData);
            return "SUCCESS: Installed applet successfully!";
        }
    }
}