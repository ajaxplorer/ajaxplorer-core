(function(global){

    class Loader{

        hookAfterDelete(){
            // Modify the Delete window
            // Uses only pure-JS
            document.observe("ajaxplorer:afterApply-delete", function(){
                try{
                    var u = pydio.getContextHolder().getUniqueNode();
                    if(u.getMetadata().get("ajxp_shared")){
                        var f = document.querySelectorAll("#generic_dialog_box #delete_message")[0];
                        var alert = f.querySelectorAll("#share_delete_alert");
                        if(!alert.length){
                            var message;
                            if(u.isLeaf()){
                                message = global.MessageHash["share_center.158"];
                            }else{
                                message = global.MessageHash["share_center.157"];
                            }
                            f.innerHTML += "<div id='share_delete_alert' style='padding-top: 10px;color: rgb(192, 0, 0);'><span style='float: left;display: block;height: 60px;margin: 4px 7px 4px 0;font-size: 2.4em;' class='icon-warning-sign'></span>"+message+"</div>";
                        }
                    }
                }catch(e){
                    if(console) console.log(e);
                }
            });
        }

        static loadInfoPanel(container, node){
            if(!Loader.INSTANCE){
                Loader.INSTANCE = new Loader();
                Loader.INSTANCE.hookAfterDelete();
            }
            var mainCont = container.querySelectorAll("#ajxp_shared_info_panel .infoPanelTable")[0];
            mainCont.destroy = function(){
                React.unmountComponentAtNode(mainCont);
            };
            mainCont.className += (mainCont.className ? ' ' : '') + 'infopanel-destroyable-pane';
            React.render(
                React.createElement(InfoPanel, {pydio:global.pydio, node:node}),
                mainCont
            );
        }
    }

    var InfoPanelInputRow = React.createClass({

        propTypes: {
            inputTitle: React.PropTypes.string,
            inputValue: React.PropTypes.string,
            inputClassName: React.PropTypes.string,
            getMessage: React.PropTypes.func,
            inputCopyMessage: React.PropTypes.object
        },

        getInitialState: function(){
            return {copyMessage: null};
        },

        componentDidMount:function(){
            this.attachClipboard();
        },
        componentDidUpdate:function(){
            this.attachClipboard();
        },

        attachClipboard:function(){
            if(this._clip){
                this._clip.destroy();
            }
            if(!this.refs['copy-button']) {
                return;
            }
            this._clip = new Clipboard(this.refs['copy-button'].getDOMNode(), {
                text: function(trigger) {
                    return this.props.inputValue;
                }.bind(this)
            });
            this._clip.on('success', function(){
                this.setState({copyMessage:this.props.getMessage(this.props.inputCopyMessage)}, this.clearCopyMessage);
            }.bind(this));
            this._clip.on('error', function(){
                var copyMessage;
                if( global.navigator.platform.indexOf("Mac") === 0 ){
                    copyMessage = this.props.getMessage('144');
                }else{
                    copyMessage = this.props.getMessage('143');
                }
                this.refs['input'].getDOMNode().focus();
                this.setState({copyMessage:copyMessage}, this.clearCopyMessage);
            }.bind(this));
        },

        clearCopyMessage:function(){
            global.setTimeout(function(){
                this.setState({copyMessage:''});
            }.bind(this), 3000);
        },

        render: function(){

            let select = function(e){
                e.currentTarget.select();
            };

            let copyMessage = null;
            if(this.state.copyMessage){
                var setHtml = function(){
                    return {__html:this.state.copyMessage};
                }.bind(this);
                copyMessage = <div className="copy-message" dangerouslySetInnerHTML={setHtml()}/>;
            }
            return (
                <div className="infoPanelRow">
                    <div className="infoPanelLabel">{this.props.getMessage(this.props.inputTitle)}</div>
                    <div className="infoPanelValue" style={{position:'relative'}}>
                        <input
                            ref="input"
                            type="text"
                            className={this.props.inputClassName}
                            readOnly={true}
                            onClick={select}
                            value={this.props.inputValue}
                        />
                        <span ref="copy-button" title={this.props.getMessage('191')} className="copy-button icon-paste"/>
                        {copyMessage}
                    </div>
                </div>
            );

        }

    });

    var TemplatePanel = React.createClass({

        propTypes: {
            node:React.PropTypes.instanceOf(AjxpNode),
            pydio:React.PropTypes.instanceOf(Pydio),
            getMessage:React.PropTypes.func,
            publicLink:React.PropTypes.string
        },

        getInitialState:function(){
            return {show: false};
        },

        generateTplHTML: function(){

            let editors = this.props.pydio.Registry.findEditorsForMime(this.props.node.getAjxpMime(), true);
            if(!editors.length){
                return null;
            }

            let tplString ;
            let messKey = "61";
            let newlink = ReactModel.Share.buildDirectDownloadUrl(this.props.node, this.props.publicLink, true);
            let template = global.pydio.UI.getSharedPreviewTemplateForEditor(editors[0], this.props.node);
            if(template){
                tplString = template.evaluate({WIDTH:350, HEIGHT:350, DL_CT_LINK:newlink});
            }else{
                tplString = newlink;
                messKey = "60";
            }
            return {messageKey:messKey, templateString:tplString};

        },

        render : function(){
            let data = this.generateTplHTML();
            if(!data){
                return null;
            }
            return <InfoPanelInputRow
                inputTitle={data.messageKey}
                inputValue={data.templateString}
                inputClassName="share_info_panel_link"
                getMessage={this.props.getMessage}
                inputCopyMessage="229"
            />;
        }

    });

    var InfoPanel = React.createClass({

        propTypes: {
            node:React.PropTypes.instanceOf(AjxpNode),
            pydio:React.PropTypes.instanceOf(Pydio)
        },

        getInitialState: function(){
            return {
                status:'loading',
                model : new ReactModel.Share(this.props.pydio, this.props.node)
            };
        },
        componentDidMount:function(){
            this.state.model.observe("status_changed", this.modelUpdated);
        },

        modelUpdated: function(){
            if(this.isMounted()){
                this.setState({status:this.state.model.getStatus()});
            }
        },

        getMessage: function(id){
            try{
                return this.props.pydio.MessageHash['share_center.' + id];
            }catch(e){
                return id;
            }
        },

        render: function(){
            if(this.state.model.hasPublicLink()){
                var linkData = this.state.model.getPublicLinks()[0];
                var isExpired = linkData["is_expired"];

                // Main Link Field
                var linkField = (<InfoPanelInputRow
                    inputTitle="121"
                    inputValue={linkData['public_link']}
                    inputClassName={"share_info_panel_link" + (isExpired?" share_info_panel_link_expired":"")}
                    getMessage={this.getMessage}
                    inputCopyMessage="192"
                />);
                if(this.props.node.isLeaf() && this.props.pydio.getPluginConfigs("action.share").get("INFOPANEL_DISPLAY_DIRECT_DOWNLOAD")){
                    // Direct Download Field
                    var downloadField = <InfoPanelInputRow
                        inputTitle="60"
                        inputValue={ReactModel.Share.buildDirectDownloadUrl(this.props.node, linkData['public_link'])}
                        inputClassName="share_info_panel_link"
                        getMessage={this.getMessage}
                        inputCopyMessage="192"
                    />;
                }
                if(this.props.node.isLeaf() && this.props.pydio.getPluginConfigs("action.share").get("INFOPANEL_DISPLAY_HTML_EMBED")){
                    // HTML Code Snippet (may be empty)
                    var templateField = <TemplatePanel
                        {...this.props}
                        getMessage={this.getMessage}
                        publicLink={linkData.public_link}
                    />;
                }
            }
            var users = this.state.model.getSharedUsers();
            var sharedUsersEntries = [], remoteUsersEntries = [];
            if(users.length){
                sharedUsersEntries = users.map(function(u){
                    var rights = [];
                    if(u.RIGHT.indexOf('r') !== -1) rights.push(global.MessageHash["share_center.31"]);
                    if(u.RIGHT.indexOf('w') !== -1) rights.push(global.MessageHash["share_center.181"]);
                    return (
                        <div key={u.ID} className="uUserEntry">
                            <span className="uLabel">{u.LABEL}</span>
                            <span className="uRight">{rights.join(' & ')}</span>
                        </div>
                    );
                });
            }
            var ocsLinks = this.state.model.getOcsLinks();
            if(ocsLinks.length){
                remoteUsersEntries = ocsLinks.map(function(link){
                    var i = link['invitation'];
                    var status;
                    if(!i){
                        status = '214';
                    }else {
                        if(i.STATUS == 1){
                            status = '211';
                        }else if(i.STATUS == 2){
                            status = '212';
                        }else if(i.STATUS == 4){
                            status = '213';
                        }
                    }
                    status = this.getMessage(status);

                    return (
                        <div key={"remote-"+link.hash} className="uUserEntry">
                            <span className="uLabel">{i.USER} @ {i.HOST}</span>
                            <span className="uStatus">{status}</span>
                        </div>
                    );
                }.bind(this));
            }
            if(sharedUsersEntries.length || remoteUsersEntries.length){
                var sharedUsersBlock = (
                    <div className="infoPanelRow">
                        <div className="infoPanelLabel">{this.getMessage('54')}</div>
                        <div className="infoPanelValue">
                            {sharedUsersEntries}
                            {remoteUsersEntries}
                        </div>
                    </div>
                );
            }
            if(this.state.model.getStatus() !== 'loading' && !sharedUsersEntries.length
                && !remoteUsersEntries.length && !this.state.model.hasPublicLink()){
                let func = function(){
                    this.state.model.stopSharing();
                }.bind(this);
                var noEntriesFoundBlock = (
                    <div className="infoPanelRow">
                        <div className="infoPanelValue">{this.getMessage(232)} <a style={{textDecoration:'underline',cursor:'pointer'}} onClick={func}>{this.getMessage(233)}</a></div>
                    </div>
                );
            }

            return (
                <div>
                    {linkField}
                    {downloadField}
                    {templateField}
                    {sharedUsersBlock}
                    {noEntriesFoundBlock}
                </div>
            );
        }

    });

    global.ShareInfoPanel = {};
    global.ShareInfoPanel.loader = Loader.loadInfoPanel;


})(window);
