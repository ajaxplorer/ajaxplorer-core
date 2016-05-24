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
 * The latest code can be found at <http://pyd.io/>.
 *
 */
namespace Pydio\Access\Driver\StreamProvider\SMB;

use DOMNode;
use PclZip;
use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a samba server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class smbAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $smbclientPath = $this->driverConf["SMBCLIENT"];
        define ('SMB4PHP_SMBCLIENT', $smbclientPath);

        $smbtmpPath = $this->driverConf["SMB_PATH_TMP"];
        define ('SMB4PHP_SMBTMP', $smbtmpPath);
		
        require_once($this->getBaseDir()."/smb.php");

        //$create = $this->repository->getOption("CREATE");
        $recycle = $this->repository->getOption("RECYCLE_BIN");

        $this->detectStreamWrapper(true);
        $this->urlBase = "pydio://".$this->repository->getId();

        if ($recycle!= "" && !is_dir($this->urlBase."/".$recycle)) {
            @mkdir($this->urlBase."/".$recycle);
            if (!is_dir($this->urlBase."/".$recycle)) {
                throw new PydioException("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
            }
        }
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }

    }

    public function detectStreamWrapper($register = false)
    {
        if ($register) {
            require_once($this->getBaseDir()."/smb.php");
        }
        return parent::detectStreamWrapper($register);
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if ($contribNode->nodeName != "actions" || (isSet($this->pluginConf["SMB_ENABLE_ZIP"]) && $this->pluginConf["SMB_ENABLE_ZIP"] == true)) {
            return ;
        }
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function isWriteable($dir, $type="dir")
    {
        if(substr_count($dir, '/') <= 3) $rc = true;
    	else $rc = is_writable($dir);
    	return $rc;
    }
}
