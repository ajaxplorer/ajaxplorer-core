<?php
/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Core\Utils\Reflection;
use Pydio\Core\Model\Context;
use Pydio\Core\PluginFramework\PluginsService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsulation of the javascript/css packing library
 * @package Pydio
 * @subpackage Core
 */
class JSPacker
{
    /**
     * Static function for packing all js and css into big files
     * Auto detect /js/*_list.txt files and /css/*_list.txt files and pack them.
     */
    public static function pack()
    {
        // Make sure that the gui.* plugin is loaded
        PluginsService::getInstance(Context::emptyContext())->getPluginsByType("gui");

        $sList = glob(CLIENT_RESOURCES_FOLDER."/js/*_list.txt");
        foreach ($sList as $list) {
            $scriptName = str_replace("_list.txt", ".js", $list);
            JSPacker::concatListAndPack($list,
                                             $scriptName,
                                            "Normal");
            if(isSet($_GET["separate"])){
                self::compactEach($list, "Normal");
            }
        }
    }

    /**
     * Perform actual compression
     * @param $src
     * @param $out
     * @param $mode
     * @return bool
     */
    public static function concatListAndPack($src, $out, $mode)
    {
        if (!is_file($src) || !is_readable($src)) {
            return false;
        }

        // Concat List into one big string
        $jscode = '' ;
        $noMiniCode = '';
        $lines = file($src);
        foreach($lines as $jsline){
            if(trim($jsline) == '') continue;
            $noMini = false;
            if(strpos($jsline, "#NO_MINI") !== FALSE){
                $jsline = str_replace("#NO_MINI", "", $jsline);
                $noMini = true;
            }
            $code = file_get_contents(AJXP_INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/".rtrim($jsline,"\n\r")) ;
            if ($code) {
                if($noMini) $noMiniCode .= $code;
                else $jscode .= $code ;
            }

        }

        $packer = new \JavaScriptPacker($jscode, $mode , true, false);
        $packed = $packer->pack();
        if ($mode == "None") { // css case, hack for I.E.
            $packed = str_replace("solid#", "solid #", $packed);
        }
        if(!empty($noMiniCode)){
            $packed.="\n".$noMiniCode;
        }
        @file_put_contents($out, $packed);

        return true;
    }

    /**
     * @param $list
     * @param $mode
     */
    private function compactEach($list, $mode){
        $lines = file($list);
        $fullcode = '';
        foreach($lines as $line){
            $in = AJXP_INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/".rtrim($line,"\n\r");
            $out = str_replace("/js/", "/js/min/", $in);
            $outfull = str_replace(".js", ".full.js", $out);
            $jscode = file_get_contents($in);
            $fullcode .= $jscode;
            // Pack and write to file
            $packer = new \JavaScriptPacker($jscode, $mode , true, false);
            $packed = $packer->pack();
            file_put_contents($out, $packed);

            // Pack and write to file
            $packer = new \JavaScriptPacker($fullcode, $mode , true, false);
            $packed = $packer->pack();
            file_put_contents($outfull, $packed);
        }
    }

}
