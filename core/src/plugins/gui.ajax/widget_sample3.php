<?php

// Refer to manifest.xml
//$ajaxPart = "ajxp_widget";
//$ajaxPart = "ajxp_film_strip";
//$ajaxPart = "ajxp_dropbox_template";
$ajaxPart = "ajxp_desktop";

?>

<html xmlns:ajxp>
<head>
    <title>Pydio Sample</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    <link rel="icon" type="image/x-png" href="res/themes/vision/images/html-folder.png">

    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/ajaxplorer.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/allz.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/animate-custom.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/chosen.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/fontfaces.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/media.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/screen.css">
    <link rel="stylesheet" type="text/css" href="res/themes/orbit/css/xtree.css">


    <style type="text/css">
        #<?php echo $ajaxPart; ?> {
            width: 90%;
            height: 90%;
            border: 1px solid #999;
            -moz-box-shadow: 3px 4px 4px #666;
            -webkit-box-shadow: 3px 4px 4px #666;
            box-shadow: 3px 4px 4px #666;
            text-align: left;
        /* THESE ONE ARE IMPORTANT */
            overflow: hidden;
            position: relative;
        }

        /* Patch - bouton ajouté droite toolbar décale */
        .action_bar > .toolbarGroup {
            height: 32px;
        }

        .widget_title {
            font-family: "Trebuchet MS", Arial, Helvetica;
            font-size: 20px;
            font-weight: bold;
            color: white;
            padding: 5px;
        }

    </style>
    <script language="javascript" type="text/javascript" src="res/js/ajaxplorer_boot.js"></script>
    <script type="text/javascript">
        // Initialize booter. Do not remove the commented line AJXP_JSON_START_PARAMETERS, as it is
        // dynamically replaced by the application!
        var ajaxplorer, startParameters = {}, MessageHash = {};
        startParameters = {
            //"BOOTER_URL": "index.php?get_action=get_boot_conf&goto=default/Organizations",
            "BOOTER_URL": "../../index.php?get_action=get_boot_conf",
            "FORCE_REGISTRY_RELOAD": true,
            "EXT_REP": "\/",
            "MAIN_ELEMENT": "<?php echo $ajaxPart; ?>",
            "SERVER_PREFIX_URI": "../../"
        };
        document.observe("ajaxplorer:before_gui_load", function (e) {
            ajaxplorer.currentThemeUsesIconFonts = true;
            document.documentElement.className += " ajxp_theme_vision";
        });
        window.ajxpBootstrap = new AjxpBootstrap(startParameters);
    </script>
</head>

<body bgcolor="#cccccc" text="#000000" marginheight="0" marginwidth="0" leftmargin="0" topmargin="0">
<div class="widget_title">Pydio Widget Sample</div>
<div align="center">
    <div id="<?php echo $ajaxPart; ?>" ajxpClass="AjxpPane" ajxpOptions="{}"></div>
</div>
</body>
</html>
